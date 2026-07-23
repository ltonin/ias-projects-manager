<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Project;
use App\Models\ProjectManagerOption;
use App\Models\ProjectPage;

interface ProjectRepository
{
    public function findById(int $id): ?Project;
    /** @param array{search:string,status:string,manager_person_id:string,funding_agency:string,funding_programme:string} $filters */
    public function search(array $filters, int $page, int $perPage): ProjectPage;
    /** @param array<string,mixed> $data */
    public function create(array $data): Project;
    /** @param array<string,mixed> $data */
    public function update(int $id, array $data, ?int $requiredManagerPersonId = null): Project;
    public function updateStatus(int $id, string $status, ?int $requiredManagerPersonId = null): Project;
    public function acronymExists(string $value, ?int $exceptId = null): bool;
    public function internalCodeExists(string $value, ?int $exceptId = null): bool;
    public function grantAgreementNumberExists(string $value, ?int $exceptId = null): bool;
    public function personExists(int $id): bool;
    /** @return list<ProjectManagerOption> */
    public function managerOptions(): array;
}
