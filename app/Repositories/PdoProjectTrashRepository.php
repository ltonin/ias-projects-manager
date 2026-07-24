<?php
declare(strict_types=1);
namespace App\Repositories;

use App\Database\ConnectionFactory;
use App\Exceptions\AuthorizationException;
use App\Models\Project;
use PDO;
use Throwable;

final class PdoProjectTrashRepository implements ProjectTrashRepository
{
    private?PDO$pdo=null;
    public function __construct(private readonly ConnectionFactory$connections){}
    public function listDeleted():array
    {
        $sql=$this->summarySql().' WHERE pr.deleted_at IS NOT NULL GROUP BY pr.id ORDER BY pr.deleted_at DESC,pr.id DESC';
        return$this->connection()->query($sql)->fetchAll();
    }
    public function summary(int$id):?array
    {
        $s=$this->connection()->prepare($this->summarySql().' WHERE pr.id=:id GROUP BY pr.id');$s->execute(['id'=>$id]);$r=$s->fetch();
        if(!is_array($r))return null;
        $years=$this->connection()->prepare('SELECT DISTINCT a.year FROM person_hour_allocations a JOIN project_participants pp ON pp.id=a.project_participant_id WHERE pp.project_id=:id ORDER BY a.year');
        $years->execute(['id'=>$id]);$r['related_years']=array_map('intval',$years->fetchAll(PDO::FETCH_COLUMN));return$r;
    }
    public function softDelete(Project$p,int$userId,?int$requiredManager):void
    {
        $pdo=$this->connection();$pdo->beginTransaction();
        try{$sql='UPDATE projects SET deleted_at=CURRENT_TIMESTAMP,deleted_by_user_id=:user WHERE id=:id AND deleted_at IS NULL';$a=['user'=>$userId,'id'=>$p->id];
            if($requiredManager!==null){$sql.=' AND manager_person_id=:manager';$a['manager']=$requiredManager;}
            $s=$pdo->prepare($sql);$s->execute($a);if($s->rowCount()!==1)throw new AuthorizationException('Project ownership changed or the project is already in Trash.');
            $this->audit($pdo,$p,$userId,'project_soft_deleted',[]);$pdo->commit();
        }catch(Throwable$e){if($pdo->inTransaction())$pdo->rollBack();throw$e;}
    }
    public function restore(Project$p,int$userId):void
    {
        $pdo=$this->connection();$pdo->beginTransaction();
        try{
            $valid=$pdo->prepare('SELECT pr.id FROM projects pr LEFT JOIN people pe ON pe.id=pr.manager_person_id WHERE pr.id=:id AND pr.deleted_at IS NOT NULL AND (pr.manager_person_id IS NULL OR pe.id IS NOT NULL) AND (pr.start_date IS NULL OR pr.end_date IS NULL OR pr.start_date<=pr.end_date) FOR UPDATE');
            $valid->execute(['id'=>$p->id]);if($valid->fetchColumn()===false)throw new \DomainException('The project cannot be restored because its responsible person or dates are no longer valid.');
            $s=$pdo->prepare('UPDATE projects SET deleted_at=NULL,deleted_by_user_id=NULL WHERE id=:id AND deleted_at IS NOT NULL');$s->execute(['id'=>$p->id]);if($s->rowCount()!==1)throw new \OutOfBoundsException('Deleted project not found.');
            $this->audit($pdo,$p,$userId,'project_restored',[]);$pdo->commit();
        }catch(Throwable$e){if($pdo->inTransaction())$pdo->rollBack();throw$e;}
    }
    public function permanentlyDelete(Project$p,int$userId):array
    {
        $pdo=$this->connection();$pdo->beginTransaction();
        try{
            $lock=$pdo->prepare('SELECT deleted_at FROM projects WHERE id=:id FOR UPDATE');$lock->execute(['id'=>$p->id]);$deletedAt=$lock->fetchColumn();if($deletedAt===false||$deletedAt===null)throw new \DomainException('Only projects in Trash can be permanently deleted.');
            $summary=$this->counts($pdo,$p->id);
            $s=$pdo->prepare('DELETE a FROM person_hour_allocations a JOIN project_participants pp ON pp.id=a.project_participant_id WHERE pp.project_id=:id');$s->execute(['id'=>$p->id]);
            $s=$pdo->prepare('UPDATE work_packages SET responsible_participant_id=NULL WHERE project_id=:id');$s->execute(['id'=>$p->id]);
            $s=$pdo->prepare('DELETE FROM work_packages WHERE project_id=:id');$s->execute(['id'=>$p->id]);
            $s=$pdo->prepare('DELETE FROM project_participants WHERE project_id=:id');$s->execute(['id'=>$p->id]);
            $s=$pdo->prepare('DELETE FROM projects WHERE id=:id AND deleted_at IS NOT NULL');$s->execute(['id'=>$p->id]);if($s->rowCount()!==1)throw new \DomainException('Only projects in Trash can be permanently deleted.');
            $this->audit($pdo,$p,$userId,'project_permanently_deleted',$summary);$pdo->commit();return$summary;
        }catch(Throwable$e){if($pdo->inTransaction())$pdo->rollBack();throw$e;}
    }
    private function counts(PDO$pdo,int$id):array
    {
        $queries=['work_packages'=>'SELECT COUNT(*) FROM work_packages WHERE project_id=:id','participants'=>'SELECT COUNT(*) FROM project_participants WHERE project_id=:id','allocations'=>'SELECT COUNT(*) FROM person_hour_allocations a JOIN project_participants pp ON pp.id=a.project_participant_id WHERE pp.project_id=:id'];
        $out=[];foreach($queries as$key=>$sql){$s=$pdo->prepare($sql);$s->execute(['id'=>$id]);$out[$key]=(int)$s->fetchColumn();}return$out;
    }
    private function audit(PDO$pdo,Project$p,int$userId,string$action,array$counts):void
    {
        $s=$pdo->prepare('INSERT INTO project_deletion_audit(project_id,project_name,project_code,action,acting_user_id,dependency_counts) VALUES(:id,:name,:code,:action,:user,:counts)');
        $s->execute(['id'=>$p->id,'name'=>$p->displayTitle(),'code'=>$p->internalCode??$p->acronym,'action'=>$action,'user'=>$userId,'counts'=>$counts===[]?null:json_encode($counts,JSON_THROW_ON_ERROR)]);
    }
    private function summarySql():string{return'SELECT pr.id,pr.acronym,pr.title,pr.status,pr.start_date,pr.end_date,pr.deleted_at,pr.deleted_by_user_id,CONCAT(manager.first_name," ",manager.last_name) manager_name,COALESCE(deleter.username,deleter.email) deleted_by,COUNT(DISTINCT wp.id) work_package_count,COUNT(DISTINCT pp.id) participant_count,COUNT(DISTINCT a.id) allocation_count,(SELECT COALESCE(SUM(a2.planned_hours),0.00) FROM person_hour_allocations a2 JOIN project_participants pp2 ON pp2.id=a2.project_participant_id WHERE pp2.project_id=pr.id) allocated_hours FROM projects pr LEFT JOIN people manager ON manager.id=pr.manager_person_id LEFT JOIN users deleter ON deleter.id=pr.deleted_by_user_id LEFT JOIN work_packages wp ON wp.project_id=pr.id LEFT JOIN project_participants pp ON pp.project_id=pr.id LEFT JOIN person_hour_allocations a ON a.project_participant_id=pp.id';}
    private function connection():PDO{return$this->pdo??=$this->connections->create();}
}
