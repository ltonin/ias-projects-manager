<?php

declare(strict_types=1);

namespace Tests\Fakes;

use App\Exceptions\AuthorizationException;
use App\Exceptions\DuplicatePersonHourAllocationException;
use App\Models\HourTotals;
use App\Models\PersonHourAllocation;
use App\Models\PersonHourAllocationPage;
use App\Repositories\PersonHourAllocationRepository;
use DateTimeImmutable;

final class InMemoryPersonHourAllocationRepository implements PersonHourAllocationRepository
{
    /** @var array<int,PersonHourAllocation> */public array$allocations=[];
    /** @var array<int,array{project_id:int,person_id:int,manager_id:?int,hours_per_pm:string}> */public array$contexts=[];
    private int$nextId=1;
    /** @param array<string,mixed> $d Test-only fixture support for transitional legacy rows. */
    public function seedLegacy(array$d):PersonHourAllocation{return$this->allocations[$this->nextId]=$this->make($this->nextId++,$d+['work_package_id'=>null]);}
    public function findById(int$id):?PersonHourAllocation{return$this->allocations[$id]??null;}
    public function findByParticipantAndMonth(int$p,int$y,int$m):?PersonHourAllocation{foreach($this->allocations as$a)if($a->projectParticipantId===$p&&$a->year===$y&&$a->month===$m)return$a;return null;}
    public function findByParticipantWorkPackageAndMonth(int$p,?int$wp,int$y,int$m):?PersonHourAllocation{foreach($this->allocations as$a)if($a->projectParticipantId===$p&&$a->workPackageId===$wp&&$a->year===$y&&$a->month===$m)return$a;return null;}
    public function listForParticipant(int$p,array$f,int$page,int$per):PersonHourAllocationPage
    {
        $items=array_values(array_filter($this->allocations,static fn(PersonHourAllocation$a):bool=>$a->projectParticipantId===$p
            &&($f['year']===''||$a->year===(int)$f['year'])&&($f['planned']==='all'||($a->plannedHours!==null)===($f['planned']==='present'))
            &&($f['actual']==='all'||($a->actualHours!==null)===($f['actual']==='present'))
            &&(($f['work_package_id']??'')===''||$a->workPackageId===(int)$f['work_package_id'])
            &&(($f['assignment']??'all')==='all'||($a->workPackageId!==null)===(($f['assignment']??'all')==='assigned'))
            &&($f['variance']==='all'||($a->plannedHours!==null&&$a->actualHours!==null&&($a->plannedHours===$a->actualHours)===($f['variance']==='same')))));
        usort($items,static fn($a,$b)=>[$b->year,$b->month,$b->id]<=>[$a->year,$a->month,$a->id]);
        $items=array_map(static fn($a)=>$a->withoutNotes(),$items);return new PersonHourAllocationPage(array_slice($items,($page-1)*$per,$per),count($items),$page,$per);
    }
    public function recentForParticipant(int$p,int$limit=12):array{return array_slice($this->listForParticipant($p,['year'=>'','planned'=>'all','actual'=>'all','variance'=>'all','work_package_id'=>'','assignment'=>'all'],1,$limit)->items,0,$limit);}
    public function countForParticipant(int$p):int{return count(array_filter($this->allocations,static fn($a)=>$a->projectParticipantId===$p));}
    public function create(array$d,?int$manager=null):PersonHourAllocation{if(!isset($d['work_package_id'])||!is_int($d['work_package_id'])||$d['work_package_id']<1)throw new \InvalidArgumentException('A Work Package is required.');$this->authorize($d['project_participant_id'],$manager);if($this->participantWorkPackagePeriodExists($d['project_participant_id'],$d['work_package_id'],$d['year'],$d['month']))throw new DuplicatePersonHourAllocationException();return$this->allocations[$this->nextId]=$this->make($this->nextId++,$d);}
    public function update(int$id,int$p,array$d,?int$manager=null):PersonHourAllocation{if(!isset($d['work_package_id'])||!is_int($d['work_package_id'])||$d['work_package_id']<1)throw new \InvalidArgumentException('A Work Package is required.');$this->authorize($p,$manager);$current=$this->allocations[$id]??throw new \OutOfBoundsException();if($current->projectParticipantId!==$p||$current->workPackageId===null)throw new \OutOfBoundsException();if($this->participantWorkPackagePeriodExists($p,$d['work_package_id'],$d['year'],$d['month'],$id))throw new DuplicatePersonHourAllocationException();return$this->allocations[$id]=$this->make($id,$d);}
    public function delete(int$id,int$p,?int$manager=null):void{$this->authorize($p,$manager);if(!isset($this->allocations[$id])||$this->allocations[$id]->projectParticipantId!==$p)throw new \OutOfBoundsException();unset($this->allocations[$id]);}
    public function periodExists(int$p,int$y,int$m,?int$except=null):bool{foreach($this->allocations as$a)if($a->id!==$except&&$a->projectParticipantId===$p&&$a->year===$y&&$a->month===$m)return true;return false;}
    public function participantWorkPackagePeriodExists(int$p,?int$wp,int$y,int$m,?int$except=null):bool{foreach($this->allocations as$a)if($a->id!==$except&&$a->projectParticipantId===$p&&$a->workPackageId===$wp&&$a->year===$y&&$a->month===$m)return true;return false;}
    public function hasAllocationsForParticipant(int$p):bool{return$this->countForParticipant($p)>0;}
    public function totalsForParticipant(int$p):HourTotals{return$this->totals(static fn($a)=>$a->projectParticipantId===$p);}
    public function totalsForProject(int$p):HourTotals{return$this->totals(fn($a)=>($this->contexts[$a->projectParticipantId]['project_id']??0)===$p);}
    public function unifiedTotalsForProject(int$p):HourTotals{return$this->totals(fn($a)=>($this->contexts[$a->projectParticipantId]['project_id']??0)===$p&&$a->plannedHours===$a->actualHours&&$a->plannedHours!==null);}
    public function divergentCountForProject(int$p):int{return$this->divergentCount(fn($a)=>($this->contexts[$a->projectParticipantId]['project_id']??0)===$p);}
    public function totalsForPersonAndMonth(int$p,int$y,int$m):HourTotals{return$this->totals(fn($a)=>($this->contexts[$a->projectParticipantId]['person_id']??0)===$p&&$a->year===$y&&$a->month===$m);}
    public function totalsForWorkPackage(int$id):HourTotals{return$this->totals(fn($a)=>$a->workPackageId===$id);}
    public function unifiedTotalsForWorkPackage(int$id):HourTotals{return$this->totals(fn($a)=>$a->workPackageId===$id&&$a->plannedHours===$a->actualHours&&$a->plannedHours!==null);}
    public function divergentCountForWorkPackage(int$id):int{return$this->divergentCount(fn($a)=>$a->workPackageId===$id);}
    public function unifiedTotalsForParticipant(int$id):HourTotals{return$this->totals(fn($a)=>$a->projectParticipantId===$id&&$a->plannedHours===$a->actualHours&&$a->plannedHours!==null);}
    public function divergentCountForParticipant(int$id):int{return$this->divergentCount(fn($a)=>$a->projectParticipantId===$id);}
    public function totalsForUnassignedProject(int$id):HourTotals{return$this->totals(fn($a)=>$a->workPackageId===null&&($this->contexts[$a->projectParticipantId]['project_id']??0)===$id);}
    public function findLegacyUnassignedByProject(int$id):array{$items=array_values(array_filter($this->allocations,fn($a)=>$a->workPackageId===null&&($this->contexts[$a->projectParticipantId]['project_id']??0)===$id));usort($items,fn($a,$b)=>[$a->year,$a->month,$a->personName,$a->id]<=>[$b->year,$b->month,$b->personName,$b->id]);return$items;}
    public function reclassifyLegacy(int$id,int$p,int$wp,?int$manager=null):PersonHourAllocation{$this->authorize($p,$manager);$a=$this->allocations[$id]??throw new \OutOfBoundsException();if($a->projectParticipantId!==$p||$a->workPackageId!==null)throw new \InvalidArgumentException();if($this->participantWorkPackagePeriodExists($p,$wp,$a->year,$a->month,$id))throw new DuplicatePersonHourAllocationException();return$this->allocations[$id]=$this->make($id,['project_participant_id'=>$p,'work_package_id'=>$wp,'year'=>$a->year,'month'=>$a->month,'planned_hours'=>$a->plannedHours,'actual_hours'=>$a->actualHours,'notes'=>$a->notes]);}
    public function listForWorkPackage(int$id,int$limit=10):array{return array_slice(array_values(array_filter($this->allocations,fn($a)=>$a->workPackageId===$id)),0,$limit);}
    public function listForProjectAndPeriod(int$p,int$y,int$start=1,int$end=12):array{return array_values(array_filter($this->allocations,fn($a)=>($this->contexts[$a->projectParticipantId]['project_id']??0)===$p&&$a->year===$y&&$a->month>=$start&&$a->month<=$end));}
    public function hasAllocationsForWorkPackage(int$id):bool{return$this->totalsForWorkPackage($id)->allocationCount>0;}
    public function totalsByWorkPackageForProject(int$id):array{$keys=[];foreach($this->allocations as$a)if(($this->contexts[$a->projectParticipantId]['project_id']??0)===$id)$keys[$a->workPackageId??0]=true;$out=[];foreach(array_keys($keys)as$key)$out[$key]=$key===0?$this->totalsForUnassignedProject($id):$this->totalsForWorkPackage($key);return$out;}
    private function authorize(int$p,?int$m):void{if($m!==null&&($this->contexts[$p]['manager_id']??null)!==$m)throw new AuthorizationException();}
    private function totals(callable$filter):HourTotals{$planned=0;$actual=0;$count=0;$participants=[];$months=[];$projectMonths=[];foreach($this->allocations as$a)if($filter($a)){$planned+=$this->cents($a->plannedHours);$actual+=$this->cents($a->actualHours);$count++;$participants[$a->projectParticipantId]=true;$months[$a->year.'-'.$a->month]=true;$projectMonths[($this->contexts[$a->projectParticipantId]['project_id']??0).'-'.$a->year.'-'.$a->month]=true;}return new HourTotals($this->format($planned),$this->format($actual),$count,count($participants),count($months),count($projectMonths));}
    private function divergentCount(callable$scope):int{return count(array_filter($this->allocations,fn($a)=>$scope($a)&&$a->plannedHours!==$a->actualHours));}
    private function cents(?string$v):int{if($v===null)return 0;[$whole,$fraction]=array_pad(explode('.',$v,2),2,'');return(int)$whole*100+(int)str_pad($fraction,2,'0');}
    private function format(int$v):string{return intdiv($v,100).'.'.str_pad((string)($v%100),2,'0',STR_PAD_LEFT);}
    private function make(int$id,array$d):PersonHourAllocation{$c=$this->contexts[$d['project_participant_id']];$now=new DateTimeImmutable('2026-01-01');return new PersonHourAllocation($id,$d['project_participant_id'],$d['year'],$d['month'],$d['planned_hours'],$d['actual_hours'],$d['notes'],$now,$now,$c['project_id'],$c['person_id'],'Test Person','researcher','TEST','Test project','active',$c['hours_per_pm'],$d['work_package_id']??null);}
}
