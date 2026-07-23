<?php
declare(strict_types=1);
namespace App\Services;
use App\Models\EffectiveCapacity;
use App\Models\MonthlyCapacitySummary;
use App\Models\Person;
use App\Models\PersonCapacityOverride;
use App\Models\PersonHourAllocation;
use App\Models\GlobalCapacityPage;
use App\Models\User;
use App\Repositories\PersonCapacityRepository;
use App\Repositories\PersonRepository;
use App\Support\DecimalHours;
use App\Validation\PersonCapacityValidator;
final class PersonCapacityService
{
    public function __construct(private readonly PersonCapacityRepository$capacity,private readonly PersonRepository$people,private readonly PersonCapacityValidator$validator,private readonly DecimalHours$decimals){}
    public function effectiveCapacity(Person$p,int$year,int$month):EffectiveCapacity{$o=$this->capacity->findOverrideForPersonAndMonth($p->id,$year,$month);return new EffectiveCapacity($o?->availableHours??$p->defaultMonthlyCapacityHours,$o===null?'standard':'override',$p->defaultMonthlyCapacityHours,$o?->availableHours);}
    /** @return list<MonthlyCapacitySummary> */
    public function annual(Person$p,int$year):array
    {
        $overrides=[];foreach($this->capacity->listOverridesForPersonAndYear($p->id,$year)as$o)$overrides[$o->month]=$o;
        $totals=$this->capacity->monthlyAllocationTotalsForPerson($p->id,$year);$out=[];
        for($month=1;$month<=12;$month++){$o=$overrides[$month]??null;$effective=new EffectiveCapacity($o?->availableHours??$p->defaultMonthlyCapacityHours,$o?'override':'standard',$p->defaultMonthlyCapacityHours,$o?->availableHours);$out[]=new MonthlyCapacitySummary($year,$month,$effective,$totals[$month]['planned']??'0.00',$totals[$month]['actual']??'0.00',$o?->id);}
        return$out;
    }
    public function overview(User$user,?Person$manager,int$year):GlobalCapacityPage
    {
        $people=$this->people->capacityScope($user->role,$manager?->id);$ids=array_map(static fn(Person$p):int=>$p->id,$people);
        $overrides=$this->capacity->overviewOverrides($ids,$year);$totals=$this->capacity->overviewAllocationTotals($ids,$year);$entries=[];
        foreach($people as$p){$months=[];$annual='0.00';$count=0;
            for($month=1;$month<=12;$month++){$o=$overrides[$p->id][$month]??null;if($o!==null)$count++;
                $effective=new EffectiveCapacity($o?->availableHours??$p->defaultMonthlyCapacityHours,$o?'override':'standard',$p->defaultMonthlyCapacityHours,$o?->availableHours);
                $summary=new MonthlyCapacitySummary($year,$month,$effective,$totals[$p->id][$month]['planned']??'0.00',$totals[$p->id][$month]['actual']??'0.00',$o?->id);
                $months[]=$summary;$annual=$this->decimals->format($this->decimals->cents($annual)+$this->decimals->cents($effective->effectiveHours));
            }
            $entries[]=['person'=>$p,'months'=>$months,'annualCapacity'=>$annual,'overrideCount'=>$count];
        }
        return new GlobalCapacityPage($year,$entries,count($entries)<=5,$user->isAdmin());
    }
    public function month(Person$p,int$year,int$month,?PersonHourAllocation$exclude=null):MonthlyCapacitySummary
    {
        $totals=$this->capacity->monthlyAllocationTotalsForPerson($p->id,$year)[$month]??['planned'=>'0.00','actual'=>'0.00'];
        if($exclude!==null&&$exclude->year===$year&&$exclude->month===$month){if($exclude->plannedHours!==null)$totals['planned']=$this->decimals->subtract($totals['planned'],$exclude->plannedHours);if($exclude->actualHours!==null)$totals['actual']=$this->decimals->subtract($totals['actual'],$exclude->actualHours);}
        $effective=$this->effectiveCapacity($p,$year,$month);$override=$this->capacity->findOverrideForPersonAndMonth($p->id,$year,$month);
        return new MonthlyCapacitySummary($year,$month,$effective,$totals['planned'],$totals['actual'],$override?->id);
    }
    public function validateOverride(Person$p,array$i,?int$except=null):array
    {
        $e=$this->validator->validateOverride($i);$y=filter_var($i['year']??null,FILTER_VALIDATE_INT);$m=filter_var($i['month']??null,FILTER_VALIDATE_INT);
        if($y!==false&&$m!==false&&!isset($e['year'])&&!isset($e['month'])&&$this->capacity->overrideExists($p->id,(int)$y,(int)$m,$except))$e['month']='A capacity override already exists for that month.';return$e;
    }
    public function createOverride(Person$p,array$i):PersonCapacityOverride{return$this->capacity->createOverride(['person_id'=>$p->id]+$this->normalize($i));}
    public function updateOverride(Person$p,PersonCapacityOverride$o,array$i):PersonCapacityOverride{$this->assertBelongs($p,$o);return$this->capacity->updateOverride($o->id,$p->id,$this->normalize($i));}
    public function removeOverride(Person$p,PersonCapacityOverride$o):void{$this->assertBelongs($p,$o);$this->capacity->deleteOverride($o->id,$p->id);}
    private function assertBelongs(Person$p,PersonCapacityOverride$o):void{if($o->personId!==$p->id)throw new \OutOfBoundsException('Capacity override not found.');}
    private function normalize(array$i):array{$h=$this->decimals->canonical(trim((string)$i['available_hours']));$n=trim((string)($i['notes']??''));return['year'=>(int)$i['year'],'month'=>(int)$i['month'],'available_hours'=>$h,'notes'=>$n===''?null:$n];}
}
