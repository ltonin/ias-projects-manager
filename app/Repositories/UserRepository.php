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
    /**
     * Atomically creates a user and links either a new Person or the explicitly selected unlinked Person.
     * @param array{username:string,email:string,password_hash:string,first_name:string,last_name:string,role:string,is_active:bool} $userData
     * @param array<string,mixed>|null $newPersonData
     */
    public function createWithPerson(array $userData,?array $newPersonData,?int $existingPersonId):User;
    /** @param array{username:string,email:string,first_name:string,last_name:string,role:string,is_active:bool,acting_user_id:int,password_hash?:string} $data */
    public function update(int $id, array $data): User;
    public function updatePasswordHash(int $id, string $passwordHash): void;
    public function updateEmail(int $id, string $email): User;
    public function recordLogin(int $id): void;
    public function setActive(int $id, bool $active, int $actingUserId): User;
    public function emailExists(string $email, ?int $exceptId = null): bool;
    public function usernameExists(string $username, ?int $exceptId = null): bool;
    public function activeAdminCount(): int;
}
