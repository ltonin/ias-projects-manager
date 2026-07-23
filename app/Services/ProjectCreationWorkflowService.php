<?php
declare(strict_types=1);
namespace App\Services;

use App\Database\ConnectionFactory;
use PDO;
use Throwable;

final class ProjectCreationWorkflowService
{
    private ?PDO$pdo=null;
    public function __construct(private readonly ConnectionFactory$connections){}
    /** @param array<string,mixed>$details @param list<array<string,mixed>>$wps @param list<array<string,mixed>>$participants */
    public function create(array$details,array$wps,array$participants):int
    {
        $pdo=$this->connection();$pdo->beginTransaction();
        try{
            $manager=$details['manager_person_id']!==''?(int)$details['manager_person_id']:null;
            if($manager!==null&&!$this->peopleExist([$manager]))throw new \InvalidArgumentException('The selected project manager no longer exists.');
            $personIds=array_map(static fn(array$p):int=>(int)$p['person_id'],$participants);
            if(count($personIds)!==count(array_unique($personIds)))throw new \InvalidArgumentException('A person may be selected only once.');
            if(!$this->peopleExist($personIds))throw new \InvalidArgumentException('A selected participant no longer exists.');
            $statement=$pdo->prepare('INSERT INTO projects
                (acronym,title,description,internal_code,grant_agreement_number,funding_agency,funding_programme,
                 coordinator_organization,manager_person_id,start_date,end_date,status,total_budget,currency,hours_per_pm,website_url,notes)
                VALUES(:acronym,:title,:description,NULL,NULL,NULL,NULL,NULL,:manager,:start_date,:end_date,:status,NULL,NULL,:hours,NULL,:notes)');
            $statement->execute([
                'acronym'=>trim((string)$details['acronym']),'title'=>trim((string)$details['title']),
                'description'=>$this->nullable($details['description']??''),'manager'=>$manager,
                'start_date'=>$this->nullable($details['start_date']??''),'end_date'=>$this->nullable($details['end_date']??''),
                'status'=>(string)$details['status'],'hours'=>(string)$details['hours_per_pm'],'notes'=>$this->nullable($details['notes']??''),
            ]);
            $projectId=(int)$pdo->lastInsertId();
            $wpStatement=$pdo->prepare('INSERT INTO work_packages(project_id,code,title,start_date,end_date,is_active) VALUES(:project,:code,:title,:start,:end,1)');
            foreach($wps as$wp)$wpStatement->execute(['project'=>$projectId,'code'=>trim((string)$wp['code']),'title'=>trim((string)$wp['title']),'start'=>$this->nullable($wp['start_date']??''),'end'=>$this->nullable($wp['end_date']??'')]);
            $participantStatement=$pdo->prepare('INSERT INTO project_participants(project_id,person_id,project_role,participation_start,participation_end,is_active) VALUES(:project,:person,:role,:start,:end,1)');
            foreach($participants as$participant)$participantStatement->execute(['project'=>$projectId,'person'=>(int)$participant['person_id'],'role'=>(string)$participant['project_role'],'start'=>$this->nullable($participant['participation_start']??''),'end'=>$this->nullable($participant['participation_end']??'')]);
            $pdo->commit();return$projectId;
        }catch(Throwable$exception){if($pdo->inTransaction())$pdo->rollBack();throw$exception;}
    }
    /** @param list<int>$ids */
    private function peopleExist(array$ids):bool
    {
        if($ids===[])return true;$ids=array_values(array_unique($ids));$marks=implode(',',array_fill(0,count($ids),'?'));
        $statement=$this->connection()->prepare("SELECT COUNT(*) FROM people WHERE id IN ($marks)");$statement->execute($ids);
        return(int)$statement->fetchColumn()===count($ids);
    }
    private function nullable(mixed$value):?string{$value=trim((string)$value);return$value===''?null:$value;}
    private function connection():PDO{return$this->pdo??=$this->connections->create();}
}
