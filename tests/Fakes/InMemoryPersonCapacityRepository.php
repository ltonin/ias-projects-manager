<?php
declare(strict_types=1);
namespace Tests\Fakes;
use App\Exceptions\DuplicateCapacityOverrideException;
use App\Models\PersonCapacityOverride;
use App\Repositories\PersonCapacityRepository;
use DateTimeImmutable;
final class InMemoryPersonCapacityRepository implements PersonCapacityRepository
{
    public array$overrides=[];public array$totals=[];private int$next=1;
    public function findOverrideById(int$id):?PersonCapacityOverride{return$this->overrides[$id]??null;}
    public function findOverrideForPersonAndMonth(int$p,int$y,int$m):?PersonCapacityOverride{foreach($this->overrides as$o)if($o->personId===$p&&$o->year===$y&&$o->month===$m)return$o;return null;}
    public function listOverridesForPersonAndYear(int$p,int$y):array{return array_values(array_map(static fn($o)=>$o->withoutNotes(),array_filter($this->overrides,static fn($o)=>$o->personId===$p&&$o->year===$y)));}
    public function monthlyAllocationTotalsForPerson(int$p,int$y):array{return$this->totals[$p][$y]??[];}
    public function createOverride(array$d):PersonCapacityOverride{if($this->overrideExists($d['person_id'],$d['year'],$d['month']))throw new DuplicateCapacityOverrideException();return$this->overrides[$this->next]=$this->make($this->next++,$d);}
    public function updateOverride(int$id,int$p,array$d):PersonCapacityOverride{$o=$this->overrides[$id]??throw new \OutOfBoundsException();if($o->personId!==$p)throw new \OutOfBoundsException();if($this->overrideExists($p,$d['year'],$d['month'],$id))throw new DuplicateCapacityOverrideException();return$this->overrides[$id]=$this->make($id,['person_id'=>$p]+$d);}
    public function deleteOverride(int$id,int$p):void{if(!isset($this->overrides[$id])||$this->overrides[$id]->personId!==$p)throw new \OutOfBoundsException();unset($this->overrides[$id]);}
    public function overrideExists(int$p,int$y,int$m,?int$except=null):bool{foreach($this->overrides as$o)if($o->id!==$except&&$o->personId===$p&&$o->year===$y&&$o->month===$m)return true;return false;}
    public function hasOverridesForPerson(int$p):bool{foreach($this->overrides as$o)if($o->personId===$p)return true;return false;}
    public function overviewOverrides(array$ids,int$year):array{$out=[];foreach($this->overrides as$o)if(in_array($o->personId,$ids,true)&&$o->year===$year)$out[$o->personId][$o->month]=$o->withoutNotes();return$out;}
    public function overviewAllocationTotals(array$ids,int$year):array{$out=[];foreach($ids as$id)if(isset($this->totals[$id][$year]))$out[$id]=$this->totals[$id][$year];return$out;}
    private function make(int$id,array$d):PersonCapacityOverride{$n=new DateTimeImmutable('2027-01-01');return new PersonCapacityOverride($id,$d['person_id'],$d['year'],$d['month'],$d['available_hours'],$d['notes'],$n,$n);}
}
