<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Database\ConnectionFactory;
use App\Exceptions\AuthorizationException;
use App\Exceptions\DuplicateProjectFieldException;
use App\Models\Person;
use App\Models\Project;
use App\Models\ProjectManagerOption;
use App\Models\ProjectPage;
use DateTimeImmutable;
use PDO;
use PDOException;

final class PdoProjectRepository implements ProjectRepository
{
    private ?PDO $pdo = null;
    public function __construct(private readonly ConnectionFactory $connections) {}

    public function findById(int $id): ?Project
    {
        $statement = $this->connection()->prepare($this->selectSql() . ' WHERE pr.id = :id');
        $statement->execute(['id'=>$id]);
        $row=$statement->fetch();
        return is_array($row)?$this->hydrate($row):null;
    }

    public function accessibleFor(string $role,?int $personId,int $limit=200):array
    {
        return $this->accessible($role,$personId,null,$limit);
    }

    public function accessibleForYear(string $role,?int $personId,int $year,int $limit=200):array
    {
        return $this->accessible($role,$personId,$year,$limit);
    }

    private function accessible(string $role,?int $personId,?int $year,int $limit):array
    {
        $sql=$this->selectSql();
        $parameters=[];$conditions=[];
        if($role!=='admin'&&$role!=='viewer'){
            if($personId===null)return[];
            $conditions[]='(pr.manager_person_id=:person_id OR EXISTS (
                SELECT 1 FROM project_participants pp WHERE pp.project_id=pr.id AND pp.person_id=:participant_person_id
            ))';
            $parameters=['person_id'=>$personId,'participant_person_id'=>$personId];
        }
        if($year!==null){
            $conditions[]='(pr.start_date IS NULL OR pr.start_date < :year_end_exclusive)
                AND (pr.end_date IS NULL OR pr.end_date >= :year_start)';
            $parameters['year_start']=sprintf('%04d-01-01',$year);
            $parameters['year_end_exclusive']=sprintf('%04d-01-01',$year+1);
        }
        if($conditions!==[])$sql.=' WHERE '.implode(' AND ',$conditions);
        $sql.=" ORDER BY CASE pr.status WHEN 'active' THEN 0 WHEN 'planned' THEN 1 WHEN 'suspended' THEN 2 ELSE 3 END,
            pr.start_date IS NULL,pr.start_date,pr.acronym,pr.id LIMIT :limit";
        $statement=$this->connection()->prepare($sql);
        foreach($parameters as$key=>$value)$statement->bindValue(':'.$key,$value,is_int($value)?PDO::PARAM_INT:PDO::PARAM_STR);
        $statement->bindValue(':limit',$limit,PDO::PARAM_INT);
        $statement->execute();
        return array_map(fn(array$row):Project=>$this->hydrate($row)->withoutNotes(),$statement->fetchAll());
    }

    public function search(array $filters, int $page, int $perPage): ProjectPage
    {
        [$where,$parameters]=$this->where($filters);
        $count=$this->connection()->prepare('SELECT COUNT(*) FROM projects pr LEFT JOIN people pe ON pe.id=pr.manager_person_id'.$where);
        $count->execute($parameters);
        $total=(int)$count->fetchColumn();
        $statement=$this->connection()->prepare($this->selectSql().$where.' ORDER BY pr.start_date IS NULL ASC, pr.start_date DESC, pr.acronym ASC, pr.id ASC LIMIT :limit OFFSET :offset');
        foreach($parameters as $key=>$value)$statement->bindValue(':'.$key,$value);
        $statement->bindValue(':limit',$perPage,PDO::PARAM_INT);
        $statement->bindValue(':offset',($page-1)*$perPage,PDO::PARAM_INT);
        $statement->execute();
        return new ProjectPage(array_map(fn(array $row):Project=>$this->hydrate($row)->withoutNotes(),$statement->fetchAll()),$total,$page,$perPage);
    }

    public function create(array $data): Project
    {
        try {
            $statement=$this->connection()->prepare('INSERT INTO projects
                (acronym,title,description,internal_code,grant_agreement_number,funding_agency,funding_programme,
                 coordinator_organization,manager_person_id,start_date,end_date,status,total_budget,currency,hours_per_pm,website_url,notes)
                VALUES (:acronym,:title,:description,:internal_code,:grant_agreement_number,:funding_agency,:funding_programme,
                 :coordinator_organization,:manager_person_id,:start_date,:end_date,:status,:total_budget,:currency,:hours_per_pm,:website_url,:notes)');
            $statement->execute($this->parameters($data));
        } catch(PDOException $exception){$this->translate($exception);throw $exception;}
        return $this->findById((int)$this->connection()->lastInsertId())??throw new \RuntimeException('Created project could not be loaded.');
    }

    public function update(int $id,array $data,?int $requiredManagerPersonId=null):Project
    {
        $sql='UPDATE projects SET acronym=:acronym,title=:title,description=:description,internal_code=:internal_code,
            grant_agreement_number=:grant_agreement_number,funding_agency=:funding_agency,funding_programme=:funding_programme,
            coordinator_organization=:coordinator_organization,manager_person_id=:manager_person_id,start_date=:start_date,
            end_date=:end_date,status=:status,total_budget=:total_budget,currency=:currency,hours_per_pm=:hours_per_pm,website_url=:website_url,notes=:notes WHERE id=:id';
        $parameters=['id'=>$id]+$this->parameters($data);
        if($requiredManagerPersonId!==null){$sql.=' AND manager_person_id=:required_manager';$parameters['required_manager']=$requiredManagerPersonId;}
        try{$statement=$this->connection()->prepare($sql);$statement->execute($parameters);}
        catch(PDOException $exception){$this->translate($exception);throw $exception;}
        if($statement->rowCount()===0){
            $current=$this->findById($id);
            if($current===null)throw new \OutOfBoundsException('Project not found.');
            if($requiredManagerPersonId!==null&&!$current->isOwnedBy($requiredManagerPersonId))throw new AuthorizationException('Project ownership changed.');
        }
        return $this->findById($id)??throw new \OutOfBoundsException('Project not found.');
    }

    public function updateStatus(int $id,string $status,?int $requiredManagerPersonId=null):Project
    {
        $sql='UPDATE projects SET status=:status WHERE id=:id';
        $parameters=['id'=>$id,'status'=>$status];
        if($requiredManagerPersonId!==null){$sql.=' AND manager_person_id=:manager';$parameters['manager']=$requiredManagerPersonId;}
        $statement=$this->connection()->prepare($sql);$statement->execute($parameters);
        if($statement->rowCount()===0){
            $current=$this->findById($id);
            if($current===null)throw new \OutOfBoundsException('Project not found.');
            if($requiredManagerPersonId!==null&&!$current->isOwnedBy($requiredManagerPersonId))throw new AuthorizationException('Project ownership changed.');
        }
        return $this->findById($id)??throw new \OutOfBoundsException('Project not found.');
    }

    public function acronymExists(string $value,?int $exceptId=null):bool{return $this->exists('acronym',$value,$exceptId);}
    public function internalCodeExists(string $value,?int $exceptId=null):bool{return $this->exists('internal_code',$value,$exceptId);}
    public function grantAgreementNumberExists(string $value,?int $exceptId=null):bool{return $this->exists('grant_agreement_number',$value,$exceptId);}
    public function personExists(int $id):bool{$s=$this->connection()->prepare('SELECT COUNT(*) FROM people WHERE id=:id');$s->execute(['id'=>$id]);return(int)$s->fetchColumn()>0;}

    public function managerOptions():array
    {
        $statement=$this->connection()->query('SELECT pe.id,pe.first_name,pe.last_name,pe.position_type,pe.affiliation,pe.is_active,u.username
            FROM people pe LEFT JOIN users u ON u.id=pe.user_id ORDER BY pe.last_name,pe.first_name,pe.id');
        return array_map(static fn(array $r):ProjectManagerOption=>new ProjectManagerOption(
            (int)$r['id'],(string)$r['first_name'].' '.(string)$r['last_name'],
            Person::POSITION_LABELS[(string)$r['position_type']]??(string)$r['position_type'],
            $r['affiliation']===null?null:(string)$r['affiliation'],(bool)$r['is_active'],
            $r['username']===null?null:(string)$r['username']
        ),$statement->fetchAll());
    }

    private function connection():PDO{return $this->pdo??=$this->connections->create();}
    private function selectSql():string{return "SELECT pr.*,CONCAT(pe.first_name,' ',pe.last_name) manager_name,pe.institutional_email manager_email FROM projects pr LEFT JOIN people pe ON pe.id=pr.manager_person_id";}
    private function parameters(array $d):array{return[
        'acronym'=>$d['acronym'],'title'=>$d['title'],'description'=>$d['description'],'internal_code'=>$d['internal_code'],
        'grant_agreement_number'=>$d['grant_agreement_number'],'funding_agency'=>$d['funding_agency'],'funding_programme'=>$d['funding_programme'],
        'coordinator_organization'=>$d['coordinator_organization'],'manager_person_id'=>$d['manager_person_id'],
        'start_date'=>$d['start_date'],'end_date'=>$d['end_date'],'status'=>$d['status'],'total_budget'=>$d['total_budget'],
        'currency'=>$d['currency'],'hours_per_pm'=>$d['hours_per_pm'],'website_url'=>$d['website_url'],'notes'=>$d['notes'],
    ];}
    private function exists(string $column,string $value,?int $exceptId):bool{$sql="SELECT COUNT(*) FROM projects WHERE $column=:value";$p=['value'=>$value];if($exceptId!==null){$sql.=' AND id<>:id';$p['id']=$exceptId;}$s=$this->connection()->prepare($sql);$s->execute($p);return(int)$s->fetchColumn()>0;}
    private function where(array $f):array
    {
        $c=[];$p=[];
        if($f['search']!==''){
            $search='%'.strtr($f['search'],['='=>'==','%'=>'=%','_'=>'=_']).'%';
            $fields=['acronym','title','internal_code','grant_agreement_number','funding_agency','funding_programme','coordinator_organization'];
            foreach($fields as $field){$key='s_'.$field;$c[]="pr.$field LIKE :$key ESCAPE '='";$p[$key]=$search;}
            foreach(['first_name','last_name','institutional_email'] as $field){$key='s_manager_'.$field;$c[]="pe.$field LIKE :$key ESCAPE '='";$p[$key]=$search;}
            $c=['('.implode(' OR ',$c).')'];
        }
        if($f['status']!==''){$c[]='pr.status=:status';$p['status']=$f['status'];}
        if($f['manager_person_id']!==''){$c[]='pr.manager_person_id=:manager_person_id';$p['manager_person_id']=(int)$f['manager_person_id'];}
        if($f['funding_agency']!==''){$c[]='pr.funding_agency=:funding_agency';$p['funding_agency']=$f['funding_agency'];}
        if($f['funding_programme']!==''){$c[]='pr.funding_programme=:funding_programme';$p['funding_programme']=$f['funding_programme'];}
        return[$c===[]?'':' WHERE '.implode(' AND ',$c),$p];
    }
    private function hydrate(array $r):Project{return new Project(
        (int)$r['id'],(string)$r['acronym'],(string)$r['title'],$r['description']===null?null:(string)$r['description'],
        $r['internal_code']===null?null:(string)$r['internal_code'],$r['grant_agreement_number']===null?null:(string)$r['grant_agreement_number'],
        $r['funding_agency']===null?null:(string)$r['funding_agency'],$r['funding_programme']===null?null:(string)$r['funding_programme'],
        $r['coordinator_organization']===null?null:(string)$r['coordinator_organization'],$r['manager_person_id']===null?null:(int)$r['manager_person_id'],
        $r['start_date']===null?null:new DateTimeImmutable((string)$r['start_date']),$r['end_date']===null?null:new DateTimeImmutable((string)$r['end_date']),
        (string)$r['status'],$r['total_budget']===null?null:(string)$r['total_budget'],$r['currency']===null?null:(string)$r['currency'],
        $r['website_url']===null?null:(string)$r['website_url'],$r['notes']===null?null:(string)$r['notes'],
        new DateTimeImmutable((string)$r['created_at']),new DateTimeImmutable((string)$r['updated_at']),
        $r['manager_name']===null?null:(string)$r['manager_name'],$r['manager_email']===null?null:(string)$r['manager_email'],
        (string)($r['hours_per_pm']??'125.00')
    );}
    private function translate(PDOException $e):void
    {
        if(($e->errorInfo[0]??'')!=='23000')return;$m=strtolower((string)($e->errorInfo[2]??''));
        if(str_contains($m,'projects_acronym_unique'))throw new DuplicateProjectFieldException('acronym','That acronym is already in use.');
        if(str_contains($m,'projects_internal_code_unique'))throw new DuplicateProjectFieldException('internal_code','That internal code is already in use.');
        if(str_contains($m,'projects_grant_number_unique'))throw new DuplicateProjectFieldException('grant_agreement_number','That grant agreement number is already in use.');
    }
}
