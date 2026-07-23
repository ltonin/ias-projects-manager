<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\User;

interface UserRepository
{
    public function findById(int $id): ?User;
    public function findByEmail(string $email): ?User;
    public function findByUsername(string $username): ?User;
    public function findByLoginIdentifier(string $identifier): ?User;
    /** @return list<User> */
    public function all(): array;
    /** @param array{username:string,email:string,password_hash:string,first_name:string,last_name:string,role:string,is_active:bool} $data */
    public function create(array $data): User;
    /** @param array{username:string,email:string,first_name:string,last_name:string,role:string,is_active:bool,acting_user_id:int,password_hash?:string} $data */
    public function update(int $id, array $data): User;
    public function updatePasswordHash(int $id, string $passwordHash): void;
    public function recordLogin(int $id): void;
    public function setActive(int $id, bool $active, int $actingUserId): User;
    public function emailExists(string $email, ?int $exceptId = null): bool;
    public function usernameExists(string $username, ?int $exceptId = null): bool;
    public function activeAdminCount(): int;
}
