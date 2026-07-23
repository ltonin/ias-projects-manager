<?php
declare(strict_types=1);
namespace App\Repositories;

use App\Database\ConnectionFactory;
use App\Exceptions\AuthorizationException;
use App\Exceptions\DuplicateWorkPackageCodeException;
use App\Models\ProjectParticipant;
use App\Models\WorkPackage;
use App\Models\WorkPackagePage;
use DateTimeImmutable;
use PDO;
use PDOException;
use App\Support\NaturalCodeOrder;

final class PdoWorkPackageRepository implements WorkPackageRepository
{
    private ?PDO $pdo=null;
    public function __construct(private readonly ConnectionFactory $connections){}
    public function findById(int$id):?WorkPackage
    {
        $s=$this->connection()->prepare($this->selectSql().' WHERE wp.id=:id');$s->execute(['id'=>$id]);$r=$s->fetch();
        return is_array($r)?$this->hydrate($r):null;
    }
    public function listForProject(int$p,array$f,int$page,int$per):WorkPackagePage
    {
        [$extra,$a]=$this->filters($f);$a=['project_id'=>$p]+$a;$where=' WHERE wp.project_id=:project_id'.$extra;
        $joins=$this->joins();
        $c=$this->connection()->prepare('SELECT COUNT(*)'.$joins.$where);$c->execute($a);$total=(int)$c->fetchColumn();
        $s=$this->connection()->prepare($this->selectSql().$where.' ORDER BY wp.id');
        $s->execute($a);$items=NaturalCodeOrder::sort(array_map(fn(array$r)=>$this->hydrate($r)->withoutNotes(),$s->fetchAll()));
        return new WorkPackagePage(array_slice($items,($page-1)*$per,$per),$total,$page,$per);
    }
    public function summaryForProject(int$p,int$limit=5):array
    {
        $s=$this->connection()->prepare($this->selectSql().' WHERE wp.project_id=:p ORDER BY wp.id');
        $s->execute(['p'=>$p]);return array_slice(NaturalCodeOrder::sort(array_map(fn(array$r)=>$this->hydrate($r)->withoutNotes(),$s->fetchAll())),0,$limit);
    }
    public function optionsForProject(int$p):array{$s=$this->connection()->prepare($this->selectSql().' WHERE wp.project_id=:p ORDER BY wp.id');$s->execute(['p'=>$p]);return NaturalCodeOrder::sort(array_map(fn(array$r)=>$this->hydrate($r)->withoutNotes(),$s->fetchAll()));}
    public function listByResponsibleParticipant(int$id):array
    {
        $s=$this->connection()->prepare($this->selectSql().' WHERE wp.responsible_participant_id=:id ORDER BY wp.id');$s->execute(['id'=>$id]);
        return NaturalCodeOrder::sort(array_map(fn(array$r)=>$this->hydrate($r)->withoutNotes(),$s->fetchAll()));
    }
    public function countForProject(int$p,?bool$active=null):int
    {
        $sql='SELECT COUNT(*) FROM work_packages WHERE project_id=:p';$a=['p'=>$p];
        if($active!==null){$sql.=' AND is_active=:a';$a['a']=$active?1:0;}$s=$this->connection()->prepare($sql);$s->execute($a);return(int)$s->fetchColumn();
    }
    public function countWithoutResponsibleForProject(int$p):int{$s=$this->connection()->prepare('SELECT COUNT(*) FROM work_packages WHERE project_id=:p AND responsible_participant_id IS NULL');$s->execute(['p'=>$p]);return(int)$s->fetchColumn();}
    public function responsibleOptions(int$p):array
    {
        $sql='SELECT pp.*,pe.first_name person_first_name,pe.last_name person_last_name,pe.institutional_email,pe.affiliation,
        pe.position_type,pe.is_internal person_is_internal,pe.is_active person_is_active,pe.active_from person_active_from,
        pe.active_to person_active_to,u.username linked_username,u.is_active linked_user_is_active,
        pr.acronym project_acronym,pr.title project_title,pr.status project_status
        FROM project_participants pp JOIN people pe ON pe.id=pp.person_id LEFT JOIN users u ON u.id=pe.user_id
        JOIN projects pr ON pr.id=pp.project_id WHERE pp.project_id=:p ORDER BY pe.last_name,pe.first_name,pp.id';
        $s=$this->connection()->prepare($sql);$s->execute(['p'=>$p]);
        return array_map(fn(array$r)=>$this->hydrateParticipant($r),$s->fetchAll());
    }
    public function create(array$d,?int$m=null):WorkPackage
    {
        $sql='INSERT INTO work_packages(project_id,code,title,description,start_date,end_date,responsible_participant_id,is_active,notes)
        SELECT :project_id,:code,:title,:description,:start_date,:end_date,:responsible_participant_id,:is_active,:notes
        FROM projects pr WHERE pr.id=:authorized_project_id
        AND (:responsible_check IS NULL OR EXISTS(SELECT 1 FROM project_participants pp WHERE pp.id=:responsible_exists AND pp.project_id=pr.id))';
        $a=$this->parameters($d)+['authorized_project_id'=>$d['project_id'],'responsible_check'=>$d['responsible_participant_id'],'responsible_exists'=>$d['responsible_participant_id']];
        if($m!==null){$sql.=' AND pr.manager_person_id=:manager';$a['manager']=$m;}
        try{$s=$this->connection()->prepare($sql);$s->execute($a);}catch(PDOException$e){$this->translate($e);throw$e;}
        if($s->rowCount()!==1){$this->assertProjectAuthorization((int)$d['project_id'],$m,$d['responsible_participant_id']);throw new \RuntimeException('Work Package write failed.');}
        return$this->findById((int)$this->connection()->lastInsertId())??throw new \RuntimeException('Created Work Package could not be loaded.');
    }
    public function update(int$id,int$p,array$d,?int$m=null):WorkPackage
    {
        $sql='UPDATE work_packages wp JOIN projects pr ON pr.id=wp.project_id
        SET wp.code=:code,wp.title=:title,wp.description=:description,wp.start_date=:start_date,wp.end_date=:end_date,
        wp.responsible_participant_id=:responsible_participant_id,wp.is_active=:is_active,wp.notes=:notes
        WHERE wp.id=:id AND wp.project_id=:project_id
        AND (:responsible_check IS NULL OR EXISTS(SELECT 1 FROM project_participants pp WHERE pp.id=:responsible_exists AND pp.project_id=wp.project_id))';
        $a=['id'=>$id,'project_id'=>$p]+$this->parameters($d);unset($a['project_id']);
        $a=['id'=>$id,'project_id'=>$p]+$a+['responsible_check'=>$d['responsible_participant_id'],'responsible_exists'=>$d['responsible_participant_id']];
        if($m!==null){$sql.=' AND pr.manager_person_id=:manager';$a['manager']=$m;}
        try{$s=$this->connection()->prepare($sql);$s->execute($a);}catch(PDOException$e){$this->translate($e);throw$e;}
        if($s->rowCount()===0)$this->assertTarget($id,$p,$m,$d['responsible_participant_id']);
        return$this->findById($id)??throw new \OutOfBoundsException('Work Package not found.');
    }
    public function setActive(int$id,int$p,bool$a,?int$m=null):WorkPackage
    {
        $sql='UPDATE work_packages wp JOIN projects pr ON pr.id=wp.project_id SET wp.is_active=:a WHERE wp.id=:id AND wp.project_id=:p';
        $v=['a'=>$a?1:0,'id'=>$id,'p'=>$p];if($m!==null){$sql.=' AND pr.manager_person_id=:m';$v['m']=$m;}
        $s=$this->connection()->prepare($sql);$s->execute($v);if($s->rowCount()===0)$this->assertTarget($id,$p,$m,null);
        return$this->findById($id)??throw new \OutOfBoundsException('Work Package not found.');
    }
    public function delete(int$id,int$p,?int$m=null):void
    {
        $sql='DELETE wp FROM work_packages wp JOIN projects pr ON pr.id=wp.project_id WHERE wp.id=:id AND wp.project_id=:p';$a=['id'=>$id,'p'=>$p];
        if($m!==null){$sql.=' AND pr.manager_person_id=:m';$a['m']=$m;}$s=$this->connection()->prepare($sql);$s->execute($a);
        if($s->rowCount()!==1)$this->assertTarget($id,$p,$m,null);
    }
    public function codeExistsForProject(int$p,string$c,?int$x=null):bool{$sql='SELECT COUNT(*) FROM work_packages WHERE project_id=:p AND code=:c';$a=['p'=>$p,'c'=>$c];if($x!==null){$sql.=' AND id<>:x';$a['x']=$x;}$s=$this->connection()->prepare($sql);$s->execute($a);return(int)$s->fetchColumn()>0;}
    public function countByResponsibleParticipant(int$id):int{$s=$this->connection()->prepare('SELECT COUNT(*) FROM work_packages WHERE responsible_participant_id=:id');$s->execute(['id'=>$id]);return(int)$s->fetchColumn();}
    public function hasResponsibleParticipantReference(int$id):bool{return$this->countByResponsibleParticipant($id)>0;}
    public function hasDateConflictForProject(int$p,?string$start,?string$end):bool
    {
        $conditions=[];$a=['p'=>$p];if($start!==null){$conditions[]='(start_date IS NOT NULL AND start_date<:start OR end_date IS NOT NULL AND end_date<:start2)';$a['start']=$start;$a['start2']=$start;}
        if($end!==null){$conditions[]='(end_date IS NOT NULL AND end_date>:end OR start_date IS NOT NULL AND start_date>:end2)';$a['end']=$end;$a['end2']=$end;}
        if($conditions===[])return false;$s=$this->connection()->prepare('SELECT COUNT(*) FROM work_packages WHERE project_id=:p AND ('.implode(' OR ',$conditions).')');$s->execute($a);return(int)$s->fetchColumn()>0;
    }
    private function connection():PDO{return$this->pdo??=$this->connections->create();}
    private function joins():string{return' FROM work_packages wp JOIN projects pr ON pr.id=wp.project_id LEFT JOIN project_participants pp ON pp.id=wp.responsible_participant_id LEFT JOIN people pe ON pe.id=pp.person_id LEFT JOIN users u ON u.id=pe.user_id';}
    private function selectSql():string{return'SELECT wp.*,pr.acronym project_acronym,pr.title project_title,pe.first_name responsible_first_name,pe.last_name responsible_last_name,pp.project_role responsible_role,pp.is_active responsible_participant_active,pe.is_active responsible_person_active,u.is_active responsible_user_active'.$this->joins();}
    private function filters(array$f):array
    {
        $c=[];$a=[];if($f['search']!==''){$q='%'.strtr($f['search'],['='=>'==','%'=>'=%','_'=>'=_']).'%';$c[]="(wp.code LIKE :sc ESCAPE '=' OR wp.title LIKE :st ESCAPE '=' OR pe.first_name LIKE :sf ESCAPE '=' OR pe.last_name LIKE :sl ESCAPE '=')";foreach(['sc','st','sf','sl']as$k)$a[$k]=$q;}
        if($f['active']!=='all'){$c[]='wp.is_active=:active';$a['active']=$f['active']==='active'?1:0;}
        if($f['responsibility']!=='all')$c[]=$f['responsibility']==='assigned'?'wp.responsible_participant_id IS NOT NULL':'wp.responsible_participant_id IS NULL';
        if($f['responsible_participant_id']!==''){$c[]='wp.responsible_participant_id=:rp';$a['rp']=(int)$f['responsible_participant_id'];}
        if($f['year']!==''){$c[]='(wp.start_date IS NULL OR wp.start_date<=:year_end) AND (wp.end_date IS NULL OR wp.end_date>=:year_start)';$a['year_start']=$f['year'].'-01-01';$a['year_end']=$f['year'].'-12-31';}
        return[$c===[]?'':' AND '.implode(' AND ',$c),$a];
    }
    private function parameters(array$d):array{return['project_id'=>$d['project_id'],'code'=>$d['code'],'title'=>$d['title'],'description'=>$d['description'],'start_date'=>$d['start_date'],'end_date'=>$d['end_date'],'responsible_participant_id'=>$d['responsible_participant_id'],'is_active'=>$d['is_active']?1:0,'notes'=>$d['notes']];}
    private function assertProjectAuthorization(int$p,?int$m,mixed$r):void
    {
        $s=$this->connection()->prepare('SELECT manager_person_id FROM projects WHERE id=:p');$s->execute(['p'=>$p]);$owner=$s->fetchColumn();
        if($owner===false)throw new \OutOfBoundsException('Project not found.');if($m!==null&&(int)$owner!==$m)throw new AuthorizationException('Project ownership changed.');
        if($r!==null){$s=$this->connection()->prepare('SELECT COUNT(*) FROM project_participants WHERE id=:r AND project_id=:p');$s->execute(['r'=>$r,'p'=>$p]);if((int)$s->fetchColumn()!==1)throw new \InvalidArgumentException('Responsible participant must belong to this project.');}
    }
    private function assertTarget(int$id,int$p,?int$m,mixed$r):void
    {
        $wp=$this->findById($id);if($wp===null||$wp->projectId!==$p)throw new \OutOfBoundsException('Work Package not found.');
        $this->assertProjectAuthorization($p,$m,$r);
    }
    private function translate(PDOException$e):void{if(($e->errorInfo[0]??'')==='23000'&&str_contains(strtolower((string)($e->errorInfo[2]??'')),'work_packages_project_code_unique'))throw new DuplicateWorkPackageCodeException('That Work Package code is already used in this project.',0,$e);}
    private function hydrate(array$r):WorkPackage{$d=static fn($v)=>$v===null?null:new DateTimeImmutable((string)$v);return new WorkPackage((int)$r['id'],(int)$r['project_id'],(string)$r['code'],(string)$r['title'],$r['description']===null?null:(string)$r['description'],$d($r['start_date']),$d($r['end_date']),$r['responsible_participant_id']===null?null:(int)$r['responsible_participant_id'],(bool)$r['is_active'],$r['notes']===null?null:(string)$r['notes'],new DateTimeImmutable((string)$r['created_at']),new DateTimeImmutable((string)$r['updated_at']),(string)$r['project_acronym'],(string)$r['project_title'],$r['responsible_first_name']===null?null:(string)$r['responsible_first_name'],$r['responsible_last_name']===null?null:(string)$r['responsible_last_name'],$r['responsible_role']===null?null:(string)$r['responsible_role'],$r['responsible_participant_active']===null?null:(bool)$r['responsible_participant_active'],$r['responsible_person_active']===null?null:(bool)$r['responsible_person_active'],$r['responsible_user_active']===null?null:(bool)$r['responsible_user_active']);}
    private function hydrateParticipant(array$r):ProjectParticipant{$d=static fn($v)=>$v===null?null:new DateTimeImmutable((string)$v);return new ProjectParticipant((int)$r['id'],(int)$r['project_id'],(int)$r['person_id'],(string)$r['project_role'],$d($r['participation_start']),$d($r['participation_end']),(bool)$r['is_active'],$r['notes']===null?null:(string)$r['notes'],new DateTimeImmutable((string)$r['created_at']),new DateTimeImmutable((string)$r['updated_at']),(string)$r['person_first_name'],(string)$r['person_last_name'],$r['institutional_email']===null?null:(string)$r['institutional_email'],$r['affiliation']===null?null:(string)$r['affiliation'],(string)$r['position_type'],(bool)$r['person_is_internal'],(bool)$r['person_is_active'],$d($r['person_active_from']),$d($r['person_active_to']),$r['linked_username']===null?null:(string)$r['linked_username'],$r['linked_user_is_active']===null?null:(bool)$r['linked_user_is_active'],(string)$r['project_acronym'],(string)$r['project_title'],(string)$r['project_status']);}
}
