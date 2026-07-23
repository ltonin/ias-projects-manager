<?php
declare(strict_types=1);
namespace App\Services;

use App\Auth\ProjectPolicy;
use App\Models\AnnualEffortPage;
use App\Models\Person;
use App\Models\Project;
use App\Models\ProjectParticipant;
use App\Models\User;
use App\Models\WorkPackage;
use App\Repositories\AnnualEffortRepository;
use App\Repositories\ProjectParticipantRepository;
use App\Repositories\ProjectRepository;
use App\Repositories\WorkPackageRepository;
use App\Support\DecimalHours;
use App\Validation\PersonHourAllocationValidator;

final class AnnualEffortService
{
    public const MAX_CELLS=24000;
    public function __construct(private readonly ProjectRepository$projects,private readonly ProjectParticipantRepository$participants,private readonly WorkPackageRepository$workPackages,private readonly AnnualEffortRepository$effort,private readonly PersonHourAllocationValidator$validator,private readonly ProjectPolicy$policy,private readonly DecimalHours$decimals){}
    public function defaultYear(Project$p,int$current):int{$min=$p->startDate?->format('Y');$max=$p->endDate?->format('Y');if($min!==null&&$current<(int)$min)return(int)$min;if($max!==null&&$current>(int)$max)return(int)$max;return$current;}
    public function page(Project$p,int$year,User$u,?Person$person):AnnualEffortPage
    {
        $participants=$this->participants->allForProject($p->id);$allWps=$this->workPackages->optionsForProject($p->id);$rows=$this->effort->classifiedForProjectYear($p->id,$year);
        $rowsBy=[];$allocatedWp=[];foreach($rows as$r){$rowsBy[$r->workPackageId][$r->projectParticipantId][$r->month]=$r;$allocatedWp[$r->workPackageId]=true;}
        $wps=array_values(array_filter($allWps,fn(WorkPackage$wp)=>$this->yearOverlap($wp,$year)||isset($allocatedWp[$wp->id])));
        $sections=[];$projectMonthly=array_fill(1,12,'0.00');$peopleWith=[];$wpsWith=0;$divergentCount=0;$projectPerson=[];
        foreach($wps as$wp){
            $ordered=$participants;usort($ordered,fn($a,$b)=>[$a->id===$wp->responsibleParticipantId?0:1,mb_strtolower($a->personName()),$a->id]<=>[$b->id===$wp->responsibleParticipantId?0:1,mb_strtolower($b->personName()),$b->id]);
            $participantRows=[];$monthly=array_fill(1,12,'0.00');$has=false;$wpDivergent=0;
            foreach($ordered as$participant){
                $months=[];$annual='0.00';$participantDivergent=0;
                for($m=1;$m<=12;$m++){
                    $allocation=$rowsBy[$wp->id][$participant->id][$m]??null;$divergent=$allocation!==null&&$allocation->plannedHours!==$allocation->actualHours;$value=$allocation!==null&&!$divergent?$allocation->plannedHours:null;
                    $months[$m]=['allocation'=>$allocation,'allowed'=>$this->cellAllowed($p,$participant,$wp,$year,$m),'divergent'=>$divergent,'value'=>$value];
                    if($divergent){$divergentCount++;$wpDivergent++;$participantDivergent++;continue;}
                    if($value!==null){$monthly[$m]=$this->add($monthly[$m],$value);$annual=$this->add($annual,$value);$projectPerson[$participant->personId]=$this->add($projectPerson[$participant->personId]??'0.00',$value);$has=true;$peopleWith[$participant->id]=true;}
                }
                $participantRows[]=['participant'=>$participant,'months'=>$months,'annualHours'=>$annual,'divergentCount'=>$participantDivergent];
            }
            if($has)$wpsWith++;for($m=1;$m<=12;$m++)$projectMonthly[$m]=$this->add($projectMonthly[$m],$monthly[$m]);
            $warnings=$wp->warnings();if(!$this->yearOverlap($wp,$year)&&isset($allocatedWp[$wp->id]))$warnings[]='Existing allocations are outside the current Work Package year boundaries.';
            $sections[]=['workPackage'=>$wp,'participants'=>$participantRows,'monthlyHours'=>$monthly,'annualHours'=>$this->sum($monthly),'divergentCount'=>$wpDivergent,'warnings'=>$warnings];
        }
        $capacityRaw=$this->effort->capacityData(array_map(fn($participant)=>$participant->personId,$participants),$year);$capacity=[];
        foreach($participants as$participant){
            $d=$capacityRaw[$participant->personId]??['standard'=>'0.00','overrides'=>[],'months'=>[],'divergent'=>0];$over=0;
            foreach(range(1,12)as$m){$cap=$d['overrides'][$m]??$d['standard'];$hours=$d['months'][$m]['hours']??'0.00';if($this->decimals->compare($hours,$cap)>0)$over++;}
            $capacity[]=['participant'=>$participant,'projectHours'=>$projectPerson[$participant->personId]??'0.00','crossProjectHours'=>$this->sum(array_column($d['months'],'hours')),'monthsOver'=>$over,'divergentCount'=>(int)($d['divergent']??0)];
        }
        $currentYear=(int)date('Y');return new AnnualEffortPage($p,$year,$this->policy->canManageAllocations($u,$person,$p),$sections,$projectMonthly,$this->sum($projectMonthly),$wpsWith,count($peopleWith),$capacity,$this->effort->unassignedSummary($p->id,$year),$this->effort->snapshotToken($rows),$divergentCount,$year===$currentYear?(int)date('n'):null);
    }
    /** @param array<string,mixed>$payload @return array{changed:int} */
    public function save(Project$p,int$year,array$payload,string$token,User$u,?Person$person):array
    {
        $current=$this->projects->findById($p->id)??throw new \OutOfBoundsException('Project not found.');$this->policy->requireManageParticipants($u,$person,$current);
        $participants=[];foreach($this->participants->allForProject($p->id)as$x)$participants[$x->id]=$x;$wps=[];foreach($this->workPackages->optionsForProject($p->id)as$x)$wps[$x->id]=$x;
        if(count($payload,COUNT_RECURSIVE)>self::MAX_CELLS)throw new \InvalidArgumentException('The submitted grid is unexpectedly large.');
        $changes=[];foreach($payload as$wpKey=>$participantCells){$wpId=$this->positive($wpKey);if($wpId===null||!isset($wps[$wpId])||!is_array($participantCells))throw new \InvalidArgumentException('Invalid Work Package grid key.');foreach($participantCells as$participantKey=>$months){$participantId=$this->positive($participantKey);if($participantId===null||!isset($participants[$participantId])||!is_array($months))throw new \InvalidArgumentException('Invalid participant grid key.');foreach($months as$monthKey=>$raw){$month=$this->positive($monthKey);if($month===null||$month>12||is_array($raw))throw new \InvalidArgumentException('Invalid grid month.');$value=trim((string)$raw);$input=['work_package_id'=>(string)$wpId,'year'=>(string)$year,'month'=>(string)$month,'planned_hours'=>'0','actual_hours'=>'0','notes'=>''];$errors=$this->validator->validate($input,$p,$participants[$participantId],$wps[$wpId]);if($value!==''&&!preg_match('/^(?:0|[1-9]\d{0,5})(?:\.\d{1,2})?$/',$value))$errors['hours']='Person-hours must be a non-negative decimal with at most two decimal places.';if($errors!==[])throw new \InvalidArgumentException($participants[$participantId]->personName().' — '.$wps[$wpId]->code.' — '.(new \DateTimeImmutable("$year-$month-01"))->format('F').': '.reset($errors));$changes[]=['participant_id'=>$participantId,'work_package_id'=>$wpId,'month'=>$month,'value'=>$value===''?null:$this->canonical($value)];}}}
        return['changed'=>$this->effort->save($p->id,$year,$changes,$token,$u->isProjectManager()?$person?->id:null)];
    }
    private function yearOverlap(WorkPackage$wp,int$y):bool{$start=new \DateTimeImmutable("$y-01-01");$end=new \DateTimeImmutable("$y-12-31");return!($wp->startDate!==null&&$wp->startDate>$end)&&!($wp->endDate!==null&&$wp->endDate<$start);}
    private function cellAllowed(Project$p,ProjectParticipant$participant,WorkPackage$wp,int$year,int$month):bool{$start=new \DateTimeImmutable(sprintf('%04d-%02d-01',$year,$month));$end=$start->modify('last day of this month');foreach([[$p->startDate,$p->endDate],[$participant->participationStart,$participant->participationEnd],[$participant->personActiveFrom,$participant->personActiveTo],[$wp->startDate,$wp->endDate]]as[$from,$to])if(($from!==null&&$end<$from)||($to!==null&&$start>$to))return false;return true;}
    private function add(string$a,string$b):string{return$this->decimals->format($this->decimals->cents($a)+$this->decimals->cents($b));}
    private function sum(array$v):string{$c=0;foreach($v as$x)$c+=$this->decimals->cents((string)$x);return$this->decimals->format($c);}
    private function positive(mixed$v):?int{$id=filter_var($v,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);return$id===false?null:(int)$id;}
    private function canonical(string$v):string{return$this->decimals->canonical($v);}
}
