<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Person;
use App\Models\PersonPage;
use App\Models\UserLinkOption;

interface PersonRepository
{
    public function findById(int $id): ?Person;
    public function findByUserId(int $userId): ?Person;
    /** @param array{search:string,active:string,internal:string,position_type:string,linked:string} $filters */
    public function search(array $filters, int $page, int $perPage): PersonPage;
    /** @param array<string, mixed> $data */
    public function create(array $data): Person;
    /** @param array<string, mixed> $data */
    public function update(int $id, array $data): Person;
    public function setActive(int $id, bool $active): Person;
    public function emailExists(string $email, ?int $exceptId = null): bool;
    public function userIsLinked(int $userId, ?int $exceptPersonId = null): bool;
    public function userExists(int $userId): bool;
    /** @return list<UserLinkOption> */
    public function availableUsers(?int $currentPersonId = null): array;
    public function count(): int;
}
