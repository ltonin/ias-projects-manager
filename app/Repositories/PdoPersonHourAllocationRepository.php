<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Database\ConnectionFactory;
use App\Exceptions\AuthorizationException;
use App\Exceptions\DuplicatePersonHourAllocationException;
use App\Models\HourTotals;
use App\Models\PersonHourAllocation;
use App\Models\PersonHourAllocationPage;
use DateTimeImmutable;
use PDO;
use PDOException;

final class PdoPersonHourAllocationRepository implements PersonHourAllocationRepository
{
    private ?PDO $pdo = null;
    public function __construct(private readonly ConnectionFactory $connections) {}

    public function findById(int $id): ?PersonHourAllocation
    {
        $statement=$this->connection()->prepare($this->selectSql().' WHERE a.id=:id');
        $statement->execute(['id'=>$id]);$row=$statement->fetch();
        return is_array($row)?$this->hydrate($row):null;
    }
    public function findByParticipantAndMonth(int $participantId,int $year,int $month):?PersonHourAllocation
    {
        $statement=$this->connection()->prepare($this->selectSql().' WHERE a.project_participant_id=:participant AND a.year=:year AND a.month=:month');
        $statement->execute(['participant'=>$participantId,'year'=>$year,'month'=>$month]);$row=$statement->fetch();
        return is_array($row)?$this->hydrate($row):null;
    }
    public function findByParticipantWorkPackageAndMonth(int$p,?int$wp,int$y,int$m):?PersonHourAllocation
    {
        $s=$this->connection()->prepare($this->selectSql().' WHERE a.project_participant_id=:p AND a.work_package_key=:wp AND a.year=:y AND a.month=:m');
        $s->execute(['p'=>$p,'wp'=>$wp??0,'y'=>$y,'m'=>$m]);$r=$s->fetch();return is_array($r)?$this->hydrate($r):null;
    }
    public function listForParticipant(int $participantId,array $filters,int $page,int $perPage):PersonHourAllocationPage
    {
        [$extra,$parameters]=$this->filters($filters);$parameters=['participant'=>$participantId]+$parameters;
        $where=' WHERE a.project_participant_id=:participant'.$extra;
        $count=$this->connection()->prepare('SELECT COUNT(*) FROM person_hour_allocations a'.$where);$count->execute($parameters);
        $statement=$this->connection()->prepare($this->selectSql().$where.' ORDER BY a.year DESC,a.month DESC,(a.work_package_id IS NOT NULL) ASC,wp.code ASC,a.id ASC LIMIT :limit OFFSET :offset');
        foreach($parameters as$key=>$value)$statement->bindValue(':'.$key,$value);
        $statement->bindValue(':limit',$perPage,PDO::PARAM_INT);$statement->bindValue(':offset',($page-1)*$perPage,PDO::PARAM_INT);$statement->execute();
        return new PersonHourAllocationPage(array_map(fn(array$row):PersonHourAllocation=>$this->hydrate($row)->withoutNotes(),$statement->fetchAll()),(int)$count->fetchColumn(),$page,$perPage);
    }
    public function recentForParticipant(int $participantId,int $limit=12):array
    {
        $statement=$this->connection()->prepare($this->selectSql().' WHERE a.project_participant_id=:participant ORDER BY a.year DESC,a.month DESC,(a.work_package_id IS NOT NULL) ASC,wp.code ASC,a.id ASC LIMIT :limit');
        $statement->bindValue(':participant',$participantId,PDO::PARAM_INT);$statement->bindValue(':limit',$limit,PDO::PARAM_INT);$statement->execute();
        return array_map(fn(array$row):PersonHourAllocation=>$this->hydrate($row)->withoutNotes(),$statement->fetchAll());
    }
    public function countForParticipant(int $participantId):int
    {
        $statement=$this->connection()->prepare('SELECT COUNT(*) FROM person_hour_allocations WHERE project_participant_id=:id');$statement->execute(['id'=>$participantId]);return(int)$statement->fetchColumn();
    }
    public function create(array $data,?int $requiredManagerPersonId=null):PersonHourAllocation
    {
        if(!isset($data['work_package_id'])||!is_int($data['work_package_id'])||$data['work_package_id']<1)throw new \InvalidArgumentException('A Work Package is required.');
        $sql='INSERT INTO person_hour_allocations(project_participant_id,work_package_id,work_package_key,year,month,planned_hours,actual_hours,notes)
            SELECT :participant,:work_package_id,:work_package_key,:year,:month,:planned,:actual,:notes FROM project_participants pp JOIN projects pr ON pr.id=pp.project_id
            WHERE pp.id=:authorized_participant AND pr.deleted_at IS NULL AND EXISTS(SELECT 1 FROM work_packages wp WHERE wp.id=:wp_exists AND wp.project_id=pp.project_id)';
        $parameters=$this->parameters($data)+['authorized_participant'=>$data['project_participant_id']];
        $parameters['wp_exists']=$data['work_package_id'];
        if($requiredManagerPersonId!==null){$sql.=' AND pr.manager_person_id=:manager';$parameters['manager']=$requiredManagerPersonId;}
        try{$statement=$this->connection()->prepare($sql);$statement->execute($parameters);}
        catch(PDOException$exception){$this->translate($exception);throw$exception;}
        if($statement->rowCount()!==1)throw new AuthorizationException('Project ownership changed.');
        return$this->findById((int)$this->connection()->lastInsertId())??throw new \RuntimeException('Created allocation could not be loaded.');
    }
    public function update(int$id,int$participantId,array$data,?int$requiredManagerPersonId=null):PersonHourAllocation
    {
        if(!isset($data['work_package_id'])||!is_int($data['work_package_id'])||$data['work_package_id']<1)throw new \InvalidArgumentException('A Work Package is required.');
        $existing=$this->findById($id);if($existing!==null&&$existing->projectParticipantId===$participantId&&$existing->workPackageId===null)throw new \InvalidArgumentException('Legacy unassigned effort must use the reclassification workflow.');
        $sql='UPDATE person_hour_allocations a JOIN project_participants pp ON pp.id=a.project_participant_id JOIN projects pr ON pr.id=pp.project_id
            SET a.work_package_id=:work_package_id,a.work_package_key=:work_package_key,a.year=:year,a.month=:month,a.planned_hours=:planned,a.actual_hours=:actual,a.notes=:notes
            WHERE a.id=:id AND a.project_participant_id=:participant AND pr.deleted_at IS NULL
            AND a.work_package_id IS NOT NULL
            AND EXISTS(SELECT 1 FROM work_packages wp WHERE wp.id=:wp_exists AND wp.project_id=pp.project_id)';
        $parameters=['id'=>$id,'participant'=>$participantId]+$this->parameters($data);
        $parameters['wp_exists']=$data['work_package_id'];
        if($requiredManagerPersonId!==null){$sql.=' AND pr.manager_person_id=:manager';$parameters['manager']=$requiredManagerPersonId;}
        try{$statement=$this->connection()->prepare($sql);$statement->execute($parameters);}
        catch(PDOException$exception){$this->translate($exception);throw$exception;}
        if($statement->rowCount()===0)$this->assertTarget($id,$participantId,$requiredManagerPersonId,$data['work_package_id']);
        return$this->findById($id)??throw new \OutOfBoundsException('Allocation not found.');
    }
    public function delete(int$id,int$participantId,?int$requiredManagerPersonId=null):void
    {
        $sql='DELETE a FROM person_hour_allocations a JOIN project_participants pp ON pp.id=a.project_participant_id JOIN projects pr ON pr.id=pp.project_id
            WHERE a.id=:id AND a.project_participant_id=:participant AND pr.deleted_at IS NULL';
        $parameters=['id'=>$id,'participant'=>$participantId];
        if($requiredManagerPersonId!==null){$sql.=' AND pr.manager_person_id=:manager';$parameters['manager']=$requiredManagerPersonId;}
        $statement=$this->connection()->prepare($sql);$statement->execute($parameters);
        if($statement->rowCount()!==1)$this->assertTarget($id,$participantId,$requiredManagerPersonId);
    }
    public function periodExists(int$participantId,int$year,int$month,?int$exceptId=null):bool
    {
        $sql='SELECT COUNT(*) FROM person_hour_allocations WHERE project_participant_id=:participant AND year=:year AND month=:month';
        $parameters=['participant'=>$participantId,'year'=>$year,'month'=>$month];
        if($exceptId!==null){$sql.=' AND id<>:id';$parameters['id']=$exceptId;}
        $statement=$this->connection()->prepare($sql);$statement->execute($parameters);return(int)$statement->fetchColumn()>0;
    }
    public function participantWorkPackagePeriodExists(int$p,?int$wp,int$y,int$m,?int$except=null):bool
    {
        $sql='SELECT COUNT(*) FROM person_hour_allocations WHERE project_participant_id=:p AND work_package_key=:wp AND year=:y AND month=:m';
        $a=['p'=>$p,'wp'=>$wp??0,'y'=>$y,'m'=>$m];if($except!==null){$sql.=' AND id<>:id';$a['id']=$except;}$s=$this->connection()->prepare($sql);$s->execute($a);return(int)$s->fetchColumn()>0;
    }
    public function hasAllocationsForParticipant(int$participantId):bool{return$this->countForParticipant($participantId)>0;}
    public function totalsForParticipant(int$participantId):HourTotals{return$this->totals('WHERE a.project_participant_id=:id',['id'=>$participantId]);}
    public function totalsForProject(int$projectId):HourTotals{return$this->totals('WHERE pp.project_id=:id',['id'=>$projectId]);}
    public function unifiedTotalsForProject(int$id):HourTotals{return$this->unifiedTotals('WHERE pp.project_id=:id',['id'=>$id]);}
    public function divergentCountForProject(int$id):int{return$this->divergentCount('WHERE pp.project_id=:id',['id'=>$id]);}
    public function totalsForPersonAndMonth(int$personId,int$year,int$month):HourTotals{return$this->totals('WHERE pp.person_id=:id AND a.year=:year AND a.month=:month',['id'=>$personId,'year'=>$year,'month'=>$month]);}
    public function totalsForWorkPackage(int$id):HourTotals{return$this->totals('WHERE a.work_package_id=:id',['id'=>$id]);}
    public function unifiedTotalsForWorkPackage(int$id):HourTotals{return$this->unifiedTotals('WHERE a.work_package_id=:id',['id'=>$id]);}
    public function divergentCountForWorkPackage(int$id):int{return$this->divergentCount('WHERE a.work_package_id=:id',['id'=>$id]);}
    public function unifiedTotalsForParticipant(int$id):HourTotals{return$this->unifiedTotals('WHERE a.project_participant_id=:id',['id'=>$id]);}
    public function divergentCountForParticipant(int$id):int{return$this->divergentCount('WHERE a.project_participant_id=:id',['id'=>$id]);}
    public function totalsForUnassignedProject(int$id):HourTotals{return$this->totals('WHERE pp.project_id=:id AND a.work_package_id IS NULL',['id'=>$id]);}
    public function findLegacyUnassignedByProject(int$id):array
    {
        $s=$this->connection()->prepare($this->selectSql().' WHERE pp.project_id=:id AND a.work_package_id IS NULL AND a.work_package_key=0 ORDER BY a.year,a.month,pe.last_name,pe.first_name,a.id');
        $s->execute(['id'=>$id]);return array_map(fn(array$r)=>$this->hydrate($r),$s->fetchAll());
    }
    public function reclassifyLegacy(int$id,int$participantId,int$workPackageId,?int$requiredManagerPersonId=null):PersonHourAllocation
    {
        if($workPackageId<1)throw new \InvalidArgumentException('A Work Package is required.');
        $sql='UPDATE person_hour_allocations a JOIN project_participants pp ON pp.id=a.project_participant_id JOIN projects pr ON pr.id=pp.project_id
            SET a.work_package_id=:wp,a.work_package_key=:wp_key
            WHERE a.id=:id AND a.project_participant_id=:participant AND pr.deleted_at IS NULL AND a.work_package_id IS NULL AND a.work_package_key=0
            AND EXISTS(SELECT 1 FROM work_packages target WHERE target.id=:wp_exists AND target.project_id=pp.project_id)';
        $parameters=['wp'=>$workPackageId,'wp_key'=>$workPackageId,'wp_exists'=>$workPackageId,'id'=>$id,'participant'=>$participantId];
        if($requiredManagerPersonId!==null){$sql.=' AND pr.manager_person_id=:manager';$parameters['manager']=$requiredManagerPersonId;}
        try{$s=$this->connection()->prepare($sql);$s->execute($parameters);}
        catch(PDOException$exception){$this->translate($exception);throw$exception;}
        if($s->rowCount()!==1)$this->assertLegacyTarget($id,$participantId,$requiredManagerPersonId,$workPackageId);
        return$this->findById($id)??throw new \OutOfBoundsException('Allocation not found.');
    }
    public function listForWorkPackage(int$id,int$limit=10):array{$s=$this->connection()->prepare($this->selectSql().' WHERE a.work_package_id=:id ORDER BY a.year DESC,a.month DESC,pe.last_name,pe.first_name,a.id LIMIT :limit');$s->bindValue(':id',$id,PDO::PARAM_INT);$s->bindValue(':limit',$limit,PDO::PARAM_INT);$s->execute();return array_map(fn(array$r)=>$this->hydrate($r)->withoutNotes(),$s->fetchAll());}
    public function listForProjectAndPeriod(int$p,int$y,int$start=1,int$end=12):array{$s=$this->connection()->prepare($this->selectSql().' WHERE pp.project_id=:p AND a.year=:y AND a.month BETWEEN :start AND :end ORDER BY pp.id,a.month,(a.work_package_id IS NOT NULL),wp.code,a.id');$s->execute(['p'=>$p,'y'=>$y,'start'=>$start,'end'=>$end]);return array_map(fn(array$r)=>$this->hydrate($r)->withoutNotes(),$s->fetchAll());}
    public function hasAllocationsForWorkPackage(int$id):bool{$s=$this->connection()->prepare('SELECT COUNT(*) FROM person_hour_allocations WHERE work_package_id=:id');$s->execute(['id'=>$id]);return(int)$s->fetchColumn()>0;}
    public function totalsByWorkPackageForProject(int$id):array
    {
        $s=$this->connection()->prepare("SELECT COALESCE(a.work_package_id,0) wp_key,COALESCE(SUM(a.planned_hours),0.00) planned,COALESCE(SUM(a.actual_hours),0.00) actual,COUNT(*) rows_count,COUNT(DISTINCT a.project_participant_id) participants,COUNT(DISTINCT CONCAT(a.year,'-',LPAD(a.month,2,'0'))) months FROM person_hour_allocations a JOIN project_participants pp ON pp.id=a.project_participant_id WHERE pp.project_id=:id GROUP BY COALESCE(a.work_package_id,0)");
        $s->execute(['id'=>$id]);$out=[];foreach($s->fetchAll()as$r)$out[(int)$r['wp_key']]=new HourTotals((string)$r['planned'],(string)$r['actual'],(int)$r['rows_count'],(int)$r['participants'],(int)$r['months'],(int)$r['months']);return$out;
    }

    private function connection():PDO{return$this->pdo??=$this->connections->create();}
    private function selectSql():string{return'SELECT a.*,pp.project_id,pp.person_id,pp.project_role,CONCAT(pe.first_name," ",pe.last_name) person_name,
        pr.acronym project_acronym,pr.title project_title,pr.status project_status,pr.hours_per_pm,
        wp.code work_package_code,wp.title work_package_title,wp.is_active work_package_is_active,
        wp.start_date work_package_start_date,wp.end_date work_package_end_date
        FROM person_hour_allocations a JOIN project_participants pp ON pp.id=a.project_participant_id
        JOIN people pe ON pe.id=pp.person_id JOIN projects pr ON pr.id=pp.project_id AND pr.deleted_at IS NULL
        LEFT JOIN work_packages wp ON wp.id=a.work_package_id';}
    private function parameters(array$data):array{return[
        'participant'=>$data['project_participant_id'],
        'work_package_id'=>$data['work_package_id'],'work_package_key'=>$data['work_package_id']??0,
        'year'=>$data['year'],'month'=>$data['month'],'planned'=>$data['planned_hours'],'actual'=>$data['actual_hours'],'notes'=>$data['notes'],
    ];}
    private function filters(array$filters):array
    {
        $conditions=[];$parameters=[];
        if($filters['year']!==''){$conditions[]='a.year=:year';$parameters['year']=(int)$filters['year'];}
        if($filters['work_package_id']!==''){$conditions[]='a.work_package_id=:work_package_id';$parameters['work_package_id']=(int)$filters['work_package_id'];}
        if($filters['assignment']==='assigned')$conditions[]='a.work_package_id IS NOT NULL';elseif($filters['assignment']==='unassigned')$conditions[]='a.work_package_id IS NULL';
        foreach(['planned'=>'planned_hours','actual'=>'actual_hours']as$key=>$column)if($filters[$key]!=='all')$conditions[]='a.'.$column.($filters[$key]==='present'?' IS NOT NULL':' IS NULL');
        if($filters['variance']==='different')$conditions[]='a.planned_hours IS NOT NULL AND a.actual_hours IS NOT NULL AND a.planned_hours<>a.actual_hours';
        elseif($filters['variance']==='same')$conditions[]='a.planned_hours IS NOT NULL AND a.actual_hours IS NOT NULL AND a.planned_hours=a.actual_hours';
        return[$conditions===[]?'':' AND '.implode(' AND ',$conditions),$parameters];
    }
    private function assertTarget(int$id,int$participantId,?int$manager,?int$workPackageId=null):void
    {
        $allocation=$this->findById($id);if($allocation===null||$allocation->projectParticipantId!==$participantId)throw new \OutOfBoundsException('Allocation not found.');
        if($manager!==null){$statement=$this->connection()->prepare('SELECT COUNT(*) FROM project_participants pp JOIN projects pr ON pr.id=pp.project_id WHERE pp.id=:participant AND pr.manager_person_id=:manager');$statement->execute(['participant'=>$participantId,'manager'=>$manager]);if((int)$statement->fetchColumn()!==1)throw new AuthorizationException('Project ownership changed.');}
        if($workPackageId!==null){$statement=$this->connection()->prepare('SELECT COUNT(*) FROM project_participants pp JOIN work_packages wp ON wp.project_id=pp.project_id WHERE pp.id=:participant AND wp.id=:wp');$statement->execute(['participant'=>$participantId,'wp'=>$workPackageId]);if((int)$statement->fetchColumn()!==1)throw new \InvalidArgumentException('Work Package must belong to the participant project.');}
    }
    private function assertLegacyTarget(int$id,int$participantId,?int$manager,int$wp):void
    {
        $a=$this->findById($id);if($a===null||$a->projectParticipantId!==$participantId)throw new \OutOfBoundsException('Allocation not found.');
        if($a->workPackageId!==null)throw new \InvalidArgumentException('Only legacy unassigned allocations may be reclassified.');
        $this->assertTarget($id,$participantId,$manager,$wp);
    }
    private function totals(string$where,array$parameters):HourTotals
    {
        $statement=$this->connection()->prepare("SELECT COALESCE(SUM(a.planned_hours),0.00) planned,COALESCE(SUM(a.actual_hours),0.00) actual,COUNT(*) allocation_count,COUNT(DISTINCT a.project_participant_id) participant_count,COUNT(DISTINCT CONCAT(a.year,'-',LPAD(a.month,2,'0'))) distinct_month_count,COUNT(DISTINCT CONCAT(pp.project_id,'-',a.year,'-',LPAD(a.month,2,'0'))) distinct_project_month_count FROM person_hour_allocations a JOIN project_participants pp ON pp.id=a.project_participant_id JOIN projects pr ON pr.id=pp.project_id AND pr.deleted_at IS NULL ".$where);
        $statement->execute($parameters);$row=$statement->fetch();
        return new HourTotals((string)$row['planned'],(string)$row['actual'],(int)$row['allocation_count'],(int)$row['participant_count'],(int)$row['distinct_month_count'],(int)$row['distinct_project_month_count']);
    }
    private function unifiedTotals(string$where,array$parameters):HourTotals
    {
        $extra=str_contains($where,'WHERE')?' AND ':' WHERE ';return$this->totals($where.$extra.'a.planned_hours <=> a.actual_hours AND a.planned_hours IS NOT NULL',$parameters);
    }
    private function divergentCount(string$where,array$parameters):int
    {
        $extra=str_contains($where,'WHERE')?' AND ':' WHERE ';$s=$this->connection()->prepare('SELECT COUNT(*) FROM person_hour_allocations a JOIN project_participants pp ON pp.id=a.project_participant_id JOIN projects pr ON pr.id=pp.project_id AND pr.deleted_at IS NULL '.$where.$extra.'NOT(a.planned_hours <=> a.actual_hours)');$s->execute($parameters);return(int)$s->fetchColumn();
    }
    private function translate(PDOException$exception):void
    {
        if(($exception->errorInfo[0]??'')==='23000'&&str_contains(strtolower((string)($exception->errorInfo[2]??'')),'person_hour_allocations_participant_wp_period_unique'))throw new DuplicatePersonHourAllocationException('An allocation already exists for that participant, Work Package, and month.',0,$exception);
    }
    private function hydrate(array$row):PersonHourAllocation{return new PersonHourAllocation(
        (int)$row['id'],(int)$row['project_participant_id'],(int)$row['year'],(int)$row['month'],
        $row['planned_hours']===null?null:(string)$row['planned_hours'],$row['actual_hours']===null?null:(string)$row['actual_hours'],
        $row['notes']===null?null:(string)$row['notes'],new DateTimeImmutable((string)$row['created_at']),new DateTimeImmutable((string)$row['updated_at']),
        (int)$row['project_id'],(int)$row['person_id'],(string)$row['person_name'],(string)$row['project_role'],
        (string)$row['project_acronym'],(string)$row['project_title'],(string)$row['project_status'],(string)$row['hours_per_pm'],
        $row['work_package_id']===null?null:(int)$row['work_package_id'],$row['work_package_code']===null?null:(string)$row['work_package_code'],
        $row['work_package_title']===null?null:(string)$row['work_package_title'],$row['work_package_is_active']===null?null:(bool)$row['work_package_is_active'],
        $row['work_package_start_date']===null?null:new DateTimeImmutable((string)$row['work_package_start_date']),
        $row['work_package_end_date']===null?null:new DateTimeImmutable((string)$row['work_package_end_date']));}
}
