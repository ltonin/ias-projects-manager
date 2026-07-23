<?php
declare(strict_types=1);
namespace App\Services;

use App\Models\GlobalAnnualOverviewPage;
use App\Models\Project;
use App\Models\User;
use App\Repositories\GlobalAnnualOverviewRepository;
use App\Repositories\ProjectRepository;
use App\Support\DecimalHours;

final class GlobalAnnualOverviewService
{
    public function __construct(
        private readonly ProjectRepository $projects,
        private readonly GlobalAnnualOverviewRepository $overview,
        private readonly DecimalHours $decimals,
    ){}
    public function page(User$user,?int$personId,int$year):GlobalAnnualOverviewPage
    {
        $projects=$this->projects->accessibleForYear($user->role,$personId,$year);
        $ids=array_map(static fn(Project$p):int=>$p->id,$projects);
        $warnings=$this->overview->warnings($ids,$year);$byProject=[];
        foreach($projects as$project)$byProject[$project->id]=[
            'project'=>$project,'sections'=>[],'monthlyHours'=>array_fill(1,12,'0.00'),'annualHours'=>'0.00',
            'warnings'=>$warnings[$project->id]??['legacy'=>0,'divergent'=>0],
        ];
        foreach($this->overview->hierarchy($ids,$year)as$row){
            $pid=(int)$row['project_id'];$wid=(int)$row['work_package_id'];
            if(!isset($byProject[$pid]['sections'][$wid]))$byProject[$pid]['sections'][$wid]=[
                'id'=>$wid,'code'=>(string)$row['work_package_code'],'title'=>(string)$row['work_package_title'],
                'responsibleParticipantId'=>$row['responsible_participant_id']===null?null:(int)$row['responsible_participant_id'],
                'active'=>(bool)$row['work_package_active'],'participants'=>[],'monthlyHours'=>array_fill(1,12,'0.00'),'annualHours'=>'0.00','divergentCount'=>0,
            ];
            if($row['participant_id']===null)continue;
            $participantId=(int)$row['participant_id'];
            $section=&$byProject[$pid]['sections'][$wid];
            if(!isset($section['participants'][$participantId]))$section['participants'][$participantId]=[
                'id'=>$participantId,'personId'=>(int)$row['person_id'],'name'=>(string)$row['person_name'],
                'role'=>(string)$row['project_role'],'active'=>(bool)$row['participant_active'],
                'months'=>array_fill(1,12,null),'annualHours'=>'0.00','divergentCount'=>0,
            ];
            if($row['allocation_id']!==null){
                $month=(int)$row['month'];$equal=$row['planned_hours']===$row['actual_hours'];
                if(!$equal){$section['participants'][$participantId]['months'][$month]='divergent';$section['participants'][$participantId]['divergentCount']++;$section['divergentCount']++;continue;}
                $value=$row['planned_hours']===null?null:(string)$row['planned_hours'];
                $section['participants'][$participantId]['months'][$month]=$value;
                if($value!==null){
                    $section['participants'][$participantId]['annualHours']=$this->add($section['participants'][$participantId]['annualHours'],$value);
                    $section['monthlyHours'][$month]=$this->add($section['monthlyHours'][$month],$value);
                    $section['annualHours']=$this->add($section['annualHours'],$value);
                    $byProject[$pid]['monthlyHours'][$month]=$this->add($byProject[$pid]['monthlyHours'][$month],$value);
                    $byProject[$pid]['annualHours']=$this->add($byProject[$pid]['annualHours'],$value);
                }
            }
            unset($section);
        }
        foreach($byProject as&$project){$project['sections']=array_values($project['sections']);foreach($project['sections']as&$section)$section['participants']=array_values($section['participants']);unset($section);}unset($project);
        return new GlobalAnnualOverviewPage($year,array_values($byProject),$year===(int)date('Y')?(int)date('n'):null);
    }
    private function add(string$a,string$b):string{return$this->decimals->format($this->decimals->cents($a)+$this->decimals->cents($b));}
}
