<?php
declare(strict_types=1);
namespace App\Repositories;

use App\Database\ConnectionFactory;
use App\Exceptions\AuthorizationException;
use App\Exceptions\StaleAnnualEffortException;
use App\Models\PersonHourAllocation;
use DateTimeImmutable;
use PDO;
use Throwable;

final class PdoAnnualEffortRepository implements AnnualEffortRepository
{
    private ?PDO$pdo=null;
    public function __construct(private readonly ConnectionFactory$connections){}
    public function classifiedForProjectYear(int$p,int$y):array
    {
        $s=$this->connection()->prepare($this->selectSql().' WHERE pp.project_id=:p AND a.year=:y AND a.work_package_id IS NOT NULL ORDER BY wp.code,pp.id,a.month,a.id');
        $s->execute(['p'=>$p,'y'=>$y]);return array_map(fn(array$r)=>$this->hydrate($r)->withoutNotes(),$s->fetchAll());
    }
    public function forProjectYear(int$p,int$y):array
    {
        $s=$this->connection()->prepare($this->selectSql().' WHERE pp.project_id=:p AND a.year=:y ORDER BY a.work_package_id IS NULL DESC,wp.code,pp.id,a.month,a.id');
        $s->execute(['p'=>$p,'y'=>$y]);return array_map(fn(array$r)=>$this->hydrate($r)->withoutNotes(),$s->fetchAll());
    }
    public function unassignedSummary(int$p,int$y):array
    {
        $s=$this->connection()->prepare('SELECT COUNT(*) c,COALESCE(SUM(a.planned_hours),0.00) planned,COALESCE(SUM(a.actual_hours),0.00) actual FROM person_hour_allocations a JOIN project_participants pp ON pp.id=a.project_participant_id WHERE pp.project_id=:p AND a.year=:y AND a.work_package_id IS NULL');$s->execute(['p'=>$p,'y'=>$y]);$r=$s->fetch();return['count'=>(int)$r['c'],'planned'=>(string)$r['planned'],'actual'=>(string)$r['actual']];
    }
    public function snapshotToken(array$rows):string
    {
        $parts=[];foreach($rows as$r)$parts[]=implode(':',[$r->id,$r->updatedAt->format('Y-m-d H:i:s'),$r->plannedHours??'',$r->actualHours??'',$r->workPackageId??0]);
        return hash('sha256',implode('|',$parts));
    }
    public function capacityData(array$ids,int$year):array
    {
        if($ids===[])return[];$ids=array_values(array_unique($ids));$marks=implode(',',array_fill(0,count($ids),'?'));$out=[];
        $s=$this->connection()->prepare("SELECT id,ROUND(annual_capacity_hours/12,2) monthly_capacity FROM people WHERE id IN ($marks)");$s->execute($ids);foreach($s->fetchAll()as$r)$out[(int)$r['id']]=['standard'=>(string)$r['monthly_capacity'],'overrides'=>[],'months'=>[]];
        $s=$this->connection()->prepare("SELECT person_id,month,available_hours FROM person_month_capacity_overrides WHERE year=? AND person_id IN ($marks)");$s->execute([$year,...$ids]);foreach($s->fetchAll()as$r)$out[(int)$r['person_id']]['overrides'][(int)$r['month']]=(string)$r['available_hours'];
        $s=$this->connection()->prepare("SELECT pp.person_id,a.month,COALESCE(SUM(a.planned_hours),0.00) hours FROM person_hour_allocations a JOIN project_participants pp ON pp.id=a.project_participant_id JOIN projects pr ON pr.id=pp.project_id AND pr.deleted_at IS NULL WHERE a.year=? AND a.planned_hours <=> a.actual_hours AND a.planned_hours IS NOT NULL AND pp.person_id IN ($marks) GROUP BY pp.person_id,a.month");$s->execute([$year,...$ids]);foreach($s->fetchAll()as$r)$out[(int)$r['person_id']]['months'][(int)$r['month']]=['hours'=>(string)$r['hours']];
        $s=$this->connection()->prepare("SELECT pp.person_id,COUNT(*) divergent FROM person_hour_allocations a JOIN project_participants pp ON pp.id=a.project_participant_id JOIN projects pr ON pr.id=pp.project_id AND pr.deleted_at IS NULL WHERE a.year=? AND NOT(a.planned_hours <=> a.actual_hours) AND pp.person_id IN ($marks) GROUP BY pp.person_id");$s->execute([$year,...$ids]);foreach($s->fetchAll()as$r)$out[(int)$r['person_id']]['divergent']=(int)$r['divergent'];
        return$out;
    }
    public function projectPersonTotals(int$p,int$year):array{$s=$this->connection()->prepare('SELECT pp.person_id,COALESCE(SUM(a.planned_hours),0.00) planned,COALESCE(SUM(a.actual_hours),0.00) actual FROM person_hour_allocations a JOIN project_participants pp ON pp.id=a.project_participant_id WHERE pp.project_id=:p AND a.year=:y GROUP BY pp.person_id');$s->execute(['p'=>$p,'y'=>$year]);$out=[];foreach($s->fetchAll()as$r)$out[(int)$r['person_id']]=['planned'=>(string)$r['planned'],'actual'=>(string)$r['actual']];return$out;}
    public function save(int$p,int$y,array$changes,string$expected,?int$manager):int
    {
        $pdo=$this->connection();$pdo->beginTransaction();
        try{
            $owner=$pdo->prepare('SELECT manager_person_id FROM projects WHERE id=:p FOR UPDATE');$owner->execute(['p'=>$p]);$ownerId=$owner->fetchColumn();
            if($ownerId===false)throw new \OutOfBoundsException('Project not found.');
            if($manager!==null&&(int)$ownerId!==$manager)throw new AuthorizationException('Project ownership changed. Refresh the grid.');
            $lock=$pdo->prepare($this->selectSql().' WHERE pp.project_id=:p AND a.year=:y ORDER BY a.work_package_id IS NULL DESC,wp.code,pp.id,a.month,a.id FOR UPDATE');$lock->execute(['p'=>$p,'y'=>$y]);$rows=array_map(fn(array$r)=>$this->hydrate($r),$lock->fetchAll());
            if(!hash_equals($this->snapshotToken($rows),$expected))throw new StaleAnnualEffortException('Effort changed after this grid was loaded. Refresh and reapply your changes.');
            $by=[];foreach($rows as$r)$by[($r->workPackageId??0).'-'.$r->projectParticipantId.'-'.$r->month]=$r;$changed=0;
            foreach($changes as$c){$key=($c['work_package_id']??0).'-'.$c['participant_id'].'-'.$c['month'];$row=$by[$key]??null;if($row!==null&&$row->plannedHours!==$row->actualHours)throw new \InvalidArgumentException('A divergent allocation must be resolved in its detailed record.');$planned=$c['value'];$actual=$c['value'];
                if($row===null&&$planned===null&&$actual===null)continue;
                if($row===null&&$c['work_package_id']===null){$s=$pdo->prepare('INSERT INTO person_hour_allocations(project_participant_id,work_package_id,work_package_key,year,month,planned_hours,actual_hours) SELECT pp.id,NULL,0,:y,:m,:planned,:actual FROM project_participants pp WHERE pp.id=:participant AND pp.project_id=:project');$s->execute(['y'=>$y,'m'=>$c['month'],'planned'=>$planned,'actual'=>$actual,'participant'=>$c['participant_id'],'project'=>$p]);if($s->rowCount()!==1)throw new \InvalidArgumentException('Grid hierarchy changed. Refresh the page.');$changed++;continue;}
                if($row===null){$s=$pdo->prepare('INSERT INTO person_hour_allocations(project_participant_id,work_package_id,work_package_key,year,month,planned_hours,actual_hours) SELECT pp.id,wp.id,wp.id,:y,:m,:planned,:actual FROM project_participants pp JOIN work_packages wp ON wp.project_id=pp.project_id WHERE pp.id=:participant AND wp.id=:wp AND pp.project_id=:project');$s->execute(['y'=>$y,'m'=>$c['month'],'planned'=>$planned,'actual'=>$actual,'participant'=>$c['participant_id'],'wp'=>$c['work_package_id'],'project'=>$p]);if($s->rowCount()!==1)throw new \InvalidArgumentException('Grid hierarchy changed. Refresh the page.');$changed++;continue;}
                if($planned===$row->plannedHours&&$actual===$row->actualHours)continue;
                if($planned===null&&$actual===null){$s=$pdo->prepare('DELETE FROM person_hour_allocations WHERE id=:id');$s->execute(['id'=>$row->id]);$changed++;continue;}
                $s=$pdo->prepare('UPDATE person_hour_allocations SET planned_hours=:planned,actual_hours=:actual WHERE id=:id');$s->execute(['planned'=>$planned,'actual'=>$actual,'id'=>$row->id]);$changed++;
            }
            $pdo->commit();return$changed;
        }catch(Throwable$e){if($pdo->inTransaction())$pdo->rollBack();throw$e;}
    }
    private function connection():PDO{return$this->pdo??=$this->connections->create();}
    private function selectSql():string{return'SELECT a.*,pp.project_id,pp.person_id,pp.project_role,CONCAT(pe.first_name," ",pe.last_name) person_name,pr.acronym project_acronym,pr.title project_title,pr.status project_status,pr.hours_per_pm,wp.code work_package_code,wp.title work_package_title,wp.is_active work_package_is_active,wp.start_date work_package_start_date,wp.end_date work_package_end_date FROM person_hour_allocations a JOIN project_participants pp ON pp.id=a.project_participant_id JOIN people pe ON pe.id=pp.person_id JOIN projects pr ON pr.id=pp.project_id LEFT JOIN work_packages wp ON wp.id=a.work_package_id';}
    private function hydrate(array$r):PersonHourAllocation{$d=static fn($v)=>$v===null?null:new DateTimeImmutable((string)$v);return new PersonHourAllocation((int)$r['id'],(int)$r['project_participant_id'],(int)$r['year'],(int)$r['month'],$r['planned_hours']===null?null:(string)$r['planned_hours'],$r['actual_hours']===null?null:(string)$r['actual_hours'],$r['notes']===null?null:(string)$r['notes'],new DateTimeImmutable((string)$r['created_at']),new DateTimeImmutable((string)$r['updated_at']),(int)$r['project_id'],(int)$r['person_id'],(string)$r['person_name'],(string)$r['project_role'],(string)$r['project_acronym'],(string)$r['project_title'],(string)$r['project_status'],(string)$r['hours_per_pm'],$r['work_package_id']===null?null:(int)$r['work_package_id'],$r['work_package_code']===null?null:(string)$r['work_package_code'],$r['work_package_title']===null?null:(string)$r['work_package_title'],$r['work_package_is_active']===null?null:(bool)$r['work_package_is_active'],$d($r['work_package_start_date']),$d($r['work_package_end_date']));}
}
