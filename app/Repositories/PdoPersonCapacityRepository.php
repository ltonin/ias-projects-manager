<?php
declare(strict_types=1);
namespace App\Repositories;
use App\Database\ConnectionFactory;
use App\Exceptions\DuplicateCapacityOverrideException;
use App\Models\PersonCapacityOverride;
use DateTimeImmutable;
use PDO;
use PDOException;
final class PdoPersonCapacityRepository implements PersonCapacityRepository
{
    private?PDO$pdo=null;public function __construct(private readonly ConnectionFactory$connections){}
    public function findOverrideById(int$id):?PersonCapacityOverride{$s=$this->connection()->prepare('SELECT * FROM person_month_capacity_overrides WHERE id=:id');$s->execute(['id'=>$id]);$r=$s->fetch();return is_array($r)?$this->hydrate($r):null;}
    public function findOverrideForPersonAndMonth(int$p,int$y,int$m):?PersonCapacityOverride{$s=$this->connection()->prepare('SELECT * FROM person_month_capacity_overrides WHERE person_id=:p AND year=:y AND month=:m');$s->execute(['p'=>$p,'y'=>$y,'m'=>$m]);$r=$s->fetch();return is_array($r)?$this->hydrate($r):null;}
    public function listOverridesForPersonAndYear(int$p,int$y):array{$s=$this->connection()->prepare('SELECT * FROM person_month_capacity_overrides WHERE person_id=:p AND year=:y ORDER BY month,id');$s->execute(['p'=>$p,'y'=>$y]);return array_map(fn($r)=>$this->hydrate($r)->withoutNotes(),$s->fetchAll());}
    public function monthlyAllocationTotalsForPerson(int$p,int$y):array
    {
        $s=$this->connection()->prepare('SELECT a.month,COALESCE(SUM(a.planned_hours),0.00) planned,COALESCE(SUM(a.actual_hours),0.00) actual FROM person_hour_allocations a JOIN project_participants pp ON pp.id=a.project_participant_id JOIN projects pr ON pr.id=pp.project_id AND pr.deleted_at IS NULL WHERE pp.person_id=:p AND a.year=:y GROUP BY a.month ORDER BY a.month');$s->execute(['p'=>$p,'y'=>$y]);$out=[];foreach($s->fetchAll()as$r)$out[(int)$r['month']]=['planned'=>(string)$r['planned'],'actual'=>(string)$r['actual']];return$out;
    }
    public function createOverride(array$d):PersonCapacityOverride{try{$s=$this->connection()->prepare('INSERT INTO person_month_capacity_overrides(person_id,year,month,available_hours,notes) VALUES(:person_id,:year,:month,:available_hours,:notes)');$s->execute($d);}catch(PDOException$e){$this->translate($e);throw$e;}return$this->findOverrideById((int)$this->connection()->lastInsertId())??throw new \RuntimeException('Override not loaded.');}
    public function updateOverride(int$id,int$p,array$d):PersonCapacityOverride{try{$s=$this->connection()->prepare('UPDATE person_month_capacity_overrides SET year=:year,month=:month,available_hours=:available_hours,notes=:notes WHERE id=:id AND person_id=:person_id');$s->execute(['id'=>$id,'person_id'=>$p]+$d);if($s->rowCount()===0){$o=$this->findOverrideById($id);if($o===null||$o->personId!==$p)throw new \OutOfBoundsException('Override not found.');}}catch(PDOException$e){$this->translate($e);throw$e;}return$this->findOverrideById($id)??throw new \OutOfBoundsException('Override not found.');}
    public function deleteOverride(int$id,int$p):void{$s=$this->connection()->prepare('DELETE FROM person_month_capacity_overrides WHERE id=:id AND person_id=:p');$s->execute(['id'=>$id,'p'=>$p]);if($s->rowCount()!==1)throw new \OutOfBoundsException('Override not found.');}
    public function overrideExists(int$p,int$y,int$m,?int$except=null):bool{$sql='SELECT COUNT(*) FROM person_month_capacity_overrides WHERE person_id=:p AND year=:y AND month=:m';$a=['p'=>$p,'y'=>$y,'m'=>$m];if($except!==null){$sql.=' AND id<>:id';$a['id']=$except;}$s=$this->connection()->prepare($sql);$s->execute($a);return(int)$s->fetchColumn()>0;}
    public function hasOverridesForPerson(int$p):bool{$s=$this->connection()->prepare('SELECT COUNT(*) FROM person_month_capacity_overrides WHERE person_id=:p');$s->execute(['p'=>$p]);return(int)$s->fetchColumn()>0;}
    public function overviewOverrides(array$ids,int$year):array
    {
        if($ids===[])return[];$marks=implode(',',array_fill(0,count($ids),'?'));
        $s=$this->connection()->prepare("SELECT * FROM person_month_capacity_overrides WHERE year=? AND person_id IN ($marks) ORDER BY person_id,month,id");$s->execute([$year,...$ids]);$out=[];
        foreach($s->fetchAll()as$r){$o=$this->hydrate($r)->withoutNotes();$out[$o->personId][$o->month]=$o;}return$out;
    }
    public function overviewAllocationTotals(array$ids,int$year):array
    {
        if($ids===[])return[];$marks=implode(',',array_fill(0,count($ids),'?'));
        $s=$this->connection()->prepare("SELECT pp.person_id,a.month,COALESCE(SUM(a.planned_hours),0.00) planned,COALESCE(SUM(a.actual_hours),0.00) actual
            FROM person_hour_allocations a JOIN project_participants pp ON pp.id=a.project_participant_id JOIN projects pr ON pr.id=pp.project_id AND pr.deleted_at IS NULL
            WHERE a.year=? AND pp.person_id IN ($marks) GROUP BY pp.person_id,a.month ORDER BY pp.person_id,a.month");$s->execute([$year,...$ids]);$out=[];
        foreach($s->fetchAll()as$r)$out[(int)$r['person_id']][(int)$r['month']]=['planned'=>(string)$r['planned'],'actual'=>(string)$r['actual']];return$out;
    }
    private function connection():PDO{return$this->pdo??=$this->connections->create();}
    private function hydrate(array$r):PersonCapacityOverride{return new PersonCapacityOverride((int)$r['id'],(int)$r['person_id'],(int)$r['year'],(int)$r['month'],(string)$r['available_hours'],$r['notes']===null?null:(string)$r['notes'],new DateTimeImmutable((string)$r['created_at']),new DateTimeImmutable((string)$r['updated_at']));}
    private function translate(PDOException$e):void{if(($e->errorInfo[0]??'')==='23000'&&str_contains(strtolower((string)($e->errorInfo[2]??'')),'person_capacity_overrides_person_period_unique'))throw new DuplicateCapacityOverrideException('A capacity override already exists for that month.',0,$e);}
}
