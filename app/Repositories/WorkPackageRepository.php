<?php
declare(strict_types=1);
namespace App\Repositories;

use App\Models\ProjectParticipant;
use App\Models\WorkPackage;
use App\Models\WorkPackagePage;

interface WorkPackageRepository
{
    public function findById(int $id): ?WorkPackage;
    /** @param array{search:string,active:string,responsibility:string,responsible_participant_id:string,year:string} $filters */
    public function listForProject(int $projectId,array $filters,int $page,int $perPage): WorkPackagePage;
    /** @return list<WorkPackage> */
    public function summaryForProject(int $projectId,int $limit=5): array;
    /** @return list<WorkPackage> */
    public function optionsForProject(int $projectId):array;
    /** @return list<WorkPackage> */
    public function listByResponsibleParticipant(int $participantId): array;
    public function countForProject(int $projectId,?bool $active=null): int;
    public function countWithoutResponsibleForProject(int $projectId): int;
    /** @return list<ProjectParticipant> */
    public function responsibleOptions(int $projectId): array;
    /** @param array<string,mixed> $data */
    public function create(array $data,?int $requiredManagerPersonId=null): WorkPackage;
    /** @param array<string,mixed> $data */
    public function update(int $id,int $projectId,array $data,?int $requiredManagerPersonId=null): WorkPackage;
    public function setActive(int $id,int $projectId,bool $active,?int $requiredManagerPersonId=null): WorkPackage;
    public function delete(int $id,int $projectId,?int $requiredManagerPersonId=null): void;
    public function codeExistsForProject(int $projectId,string $code,?int $exceptId=null): bool;
    public function countByResponsibleParticipant(int $participantId): int;
    public function hasResponsibleParticipantReference(int $participantId): bool;
    public function hasDateConflictForProject(int $projectId,?string $startDate,?string $endDate): bool;
}
