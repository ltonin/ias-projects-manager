<?php
declare(strict_types=1);
namespace App\Models;
use DateTimeImmutable;
final class PersonCapacityOverride
{
    public function __construct(public readonly int$id,public readonly int$personId,public readonly int$year,public readonly int$month,public readonly string$availableHours,public readonly ?string$notes,public readonly DateTimeImmutable$createdAt,public readonly DateTimeImmutable$updatedAt){}
    public function periodKey():string{return sprintf('%04d-%02d',$this->year,$this->month);}
    public function monthLabel():string{return(new DateTimeImmutable($this->periodKey().'-01'))->format('F Y');}
    public function withoutNotes():self{return new self($this->id,$this->personId,$this->year,$this->month,$this->availableHours,null,$this->createdAt,$this->updatedAt);}
}
