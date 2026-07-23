<?php
declare(strict_types=1);
namespace App\Models;
use App\Support\DecimalHours;
final class MonthlyCapacitySummary
{
    public function __construct(public readonly int$year,public readonly int$month,public readonly EffectiveCapacity$capacity,public readonly string$plannedHours,public readonly string$actualHours,public readonly ?int$overrideId=null){}
    public function plannedRemaining(DecimalHours$d):string{return$d->subtract($this->capacity->effectiveHours,$this->plannedHours);}
    public function actualRemaining(DecimalHours$d):string{return$d->subtract($this->capacity->effectiveHours,$this->actualHours);}
    public function plannedStatus(DecimalHours$d):string{return$this->status($this->plannedRemaining($d),$d);}
    public function actualStatus(DecimalHours$d):string{return$this->status($this->actualRemaining($d),$d);}
    public function plannedWarning(DecimalHours$d):?string{$remaining=$this->plannedRemaining($d);return$d->compare($remaining,'0.00')<0?'Planned allocation exceeds monthly capacity by '.$d->format(abs($d->cents($remaining))).' hours.':null;}
    public function actualWarning(DecimalHours$d):?string{$remaining=$this->actualRemaining($d);return$d->compare($remaining,'0.00')<0?'Actual recorded effort exceeds monthly capacity by '.$d->format(abs($d->cents($remaining))).' hours.':null;}
    private function status(string$remaining,DecimalHours$d):string{$c=$d->compare($remaining,'0.00');return$c>0?'available':($c===0?'fully_allocated':'overallocated');}
}
