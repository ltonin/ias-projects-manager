<?php
declare(strict_types=1);
namespace App\Repositories;

use App\Models\PersonHourAllocation;

interface AnnualEffortRepository
{
    /** @return list<PersonHourAllocation> */
    public function classifiedForProjectYear(int $projectId,int $year):array;
    /** @return list<PersonHourAllocation> Includes Work Package and project-level rows. */
    public function forProjectYear(int $projectId,int $year):array;
    /** @return array{count:int,planned:string,actual:string} */
    public function unassignedSummary(int $projectId,int $year):array;
    /** @param list<PersonHourAllocation> $rows */
    public function snapshotToken(array $rows):string;
    /** @param list<int> $personIds @return array<int,array{standard:string,overrides:array<int,string>,months:array<int,array{planned:string,actual:string}>}> */
    public function capacityData(array$personIds,int$year):array;
    /** @return array<int,array{planned:string,actual:string}> */
    public function projectPersonTotals(int$projectId,int$year):array;
    /**
     * @param list<array{participant_id:int,work_package_id:?int,month:int,value:?string}> $changes
     */
    public function save(int $projectId,int $year,array $changes,string $expectedToken,?int $requiredManagerPersonId):int;
}
