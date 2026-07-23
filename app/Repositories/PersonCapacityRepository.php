<?php
declare(strict_types=1);
namespace App\Repositories;
use App\Models\PersonCapacityOverride;
interface PersonCapacityRepository
{
    public function findOverrideById(int$id):?PersonCapacityOverride;
    public function findOverrideForPersonAndMonth(int$personId,int$year,int$month):?PersonCapacityOverride;
    /** @return list<PersonCapacityOverride> */public function listOverridesForPersonAndYear(int$personId,int$year):array;
    /** @return array<int,array{planned:string,actual:string}> */public function monthlyAllocationTotalsForPerson(int$personId,int$year):array;
    public function createOverride(array$data):PersonCapacityOverride;
    public function updateOverride(int$id,int$personId,array$data):PersonCapacityOverride;
    public function deleteOverride(int$id,int$personId):void;
    public function overrideExists(int$personId,int$year,int$month,?int$exceptId=null):bool;
    public function hasOverridesForPerson(int$personId):bool;
    /** @param list<int> $personIds @return array<int,array<int,PersonCapacityOverride>> */
    public function overviewOverrides(array $personIds,int $year):array;
    /** @param list<int> $personIds @return array<int,array<int,array{planned:string,actual:string}>> */
    public function overviewAllocationTotals(array $personIds,int $year):array;
}
