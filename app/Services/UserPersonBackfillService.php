<?php
declare(strict_types=1);
namespace App\Services;

use PDO;

final class UserPersonBackfillService
{
    public function __construct(private readonly PDO $pdo){}

    /** @return array<string,int> */
    public function run(bool$dryRun=false,?callable$afterInsert=null):array
    {
        $report=['users_inspected'=>(int)$this->pdo->query('SELECT COUNT(*) FROM users')->fetchColumn(),'already_linked'=>(int)$this->pdo->query('SELECT COUNT(DISTINCT user_id) FROM people WHERE user_id IS NOT NULL')->fetchColumn(),
            'unlinked_found'=>0,'people_created'=>0,'links_created'=>0,'ambiguous_skipped'=>0,'excluded'=>0,'failures'=>0,'remaining_unlinked'=>0];
        $this->pdo->beginTransaction();
        try{
            $lock=$this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'?' FOR UPDATE':'';
            $rows=$this->pdo->query('SELECT u.id,u.email,u.first_name,u.last_name,u.is_active
                FROM users u LEFT JOIN people linked ON linked.user_id=u.id WHERE linked.id IS NULL ORDER BY u.id'.$lock)->fetchAll();
            $report['unlinked_found']=count($rows);
            $candidate=$this->pdo->prepare('SELECT id FROM people WHERE institutional_email=:email'.$lock);
            $insert=$this->pdo->prepare('INSERT INTO people(user_id,first_name,last_name,institutional_email,affiliation,position_type,is_internal,active_from,active_to,is_active,default_monthly_capacity_hours,notes)
                VALUES(:user_id,:first_name,:last_name,:email,NULL,\'other\',0,NULL,NULL,:is_active,125.00,NULL)');
            foreach($rows as$row){
                $candidate->execute(['email'=>$row['email']]);if($candidate->fetchColumn()!==false){$report['ambiguous_skipped']++;continue;}
                if($dryRun)continue;
                $insert->execute(['user_id'=>$row['id'],'first_name'=>$row['first_name'],'last_name'=>$row['last_name'],'email'=>$row['email'],'is_active'=>$row['is_active']]);
                $report['people_created']++;$report['links_created']++;if($afterInsert!==null)$afterInsert($report['people_created'],(int)$row['id']);
            }
            if($dryRun)$this->pdo->rollBack();else$this->pdo->commit();
        }catch(\Throwable$exception){$report['failures']++;if($this->pdo->inTransaction())$this->pdo->rollBack();throw$exception;}
        $report['remaining_unlinked']=(int)$this->pdo->query('SELECT COUNT(*) FROM users u LEFT JOIN people p ON p.user_id=u.id WHERE p.id IS NULL')->fetchColumn();
        return$report;
    }
}
