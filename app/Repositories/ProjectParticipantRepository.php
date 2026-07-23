<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\ParticipantPersonOption;
use App\Models\ProjectParticipant;
use App\Models\ProjectParticipantPage;

interface ProjectParticipantRepository
{
    public function findById(int $id): ?ProjectParticipant;
    public function findByProjectAndPerson(int $projectId, int $personId): ?ProjectParticipant;
    /** @param array{search:string,active:string,project_role:string,internal:string,person_active:string} $filters */
    public function listForProject(int $projectId, array $filters, int $page, int $perPage): ProjectParticipantPage;
    /** @return list<ProjectParticipant> */
    public function summaryForProject(int $projectId, int $limit = 5): array;
    public function countForProject(int $projectId, ?bool $active = null): int;
    /** @param array<string,mixed> $data */
    public function create(array $data, ?int $requiredManagerPersonId = null): ProjectParticipant;
    /** @param array<string,mixed> $data */
    public function update(int $id, int $projectId, array $data, ?int $requiredManagerPersonId = null): ProjectParticipant;
    public function setActive(int $id, int $projectId, bool $active, ?int $requiredManagerPersonId = null): ProjectParticipant;
    public function delete(int $id, int $projectId, ?int $requiredManagerPersonId = null): void;
    public function personAlreadyParticipates(int $projectId, int $personId, ?int $exceptId = null): bool;
    /** @return list<ParticipantPersonOption> */
    public function availablePeople(int $projectId): array;
}
