<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\HourTotals;
use App\Models\PersonHourAllocation;
use App\Models\PersonHourAllocationPage;

interface PersonHourAllocationRepository
{
    public function findById(int $id): ?PersonHourAllocation;
    public function findByParticipantAndMonth(int $participantId, int $year, int $month): ?PersonHourAllocation;
    public function findByParticipantWorkPackageAndMonth(int $participantId,?int $workPackageId,int $year,int $month):?PersonHourAllocation;
    /** @param array{year:string,planned:string,actual:string,variance:string,work_package_id:string,assignment:string} $filters */
    public function listForParticipant(int $participantId, array $filters, int $page, int $perPage): PersonHourAllocationPage;
    /** @return list<PersonHourAllocation> */
    public function recentForParticipant(int $participantId, int $limit = 12): array;
    public function countForParticipant(int $participantId): int;
    /** @param array<string,mixed> $data */
    public function create(array $data, ?int $requiredManagerPersonId = null): PersonHourAllocation;
    /** @param array<string,mixed> $data */
    public function update(int $id, int $participantId, array $data, ?int $requiredManagerPersonId = null): PersonHourAllocation;
    public function delete(int $id, int $participantId, ?int $requiredManagerPersonId = null): void;
    public function periodExists(int $participantId, int $year, int $month, ?int $exceptId = null): bool;
    public function participantWorkPackagePeriodExists(int $participantId,?int $workPackageId,int $year,int $month,?int $exceptId=null):bool;
    public function hasAllocationsForParticipant(int $participantId): bool;
    public function totalsForParticipant(int $participantId): HourTotals;
    public function totalsForProject(int $projectId): HourTotals;
    public function unifiedTotalsForProject(int $projectId):HourTotals;
    public function divergentCountForProject(int $projectId):int;
    public function totalsForPersonAndMonth(int $personId, int $year, int $month): HourTotals;
    public function totalsForWorkPackage(int $workPackageId):HourTotals;
    public function unifiedTotalsForWorkPackage(int $workPackageId):HourTotals;
    public function divergentCountForWorkPackage(int $workPackageId):int;
    public function unifiedTotalsForParticipant(int $participantId):HourTotals;
    public function divergentCountForParticipant(int $participantId):int;
    public function totalsForUnassignedProject(int $projectId):HourTotals;
    /** @return list<PersonHourAllocation> Notes may be inspected only for a presence indicator. */
    public function findLegacyUnassignedByProject(int $projectId):array;
    public function reclassifyLegacy(int $id,int $participantId,int $workPackageId,?int $requiredManagerPersonId=null):PersonHourAllocation;
    /** @return list<PersonHourAllocation> */
    public function listForWorkPackage(int $workPackageId,int $limit=10):array;
    /** @return list<PersonHourAllocation> */
    public function listForProjectAndPeriod(int $projectId,int $year,int $startMonth=1,int $endMonth=12):array;
    public function hasAllocationsForWorkPackage(int $workPackageId):bool;
    /** @return array<int,HourTotals> Key 0 represents unassigned effort. */
    public function totalsByWorkPackageForProject(int $projectId):array;
}
