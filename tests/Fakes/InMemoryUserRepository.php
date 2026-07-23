<?php

declare(strict_types=1);

namespace Tests\Fakes;

use App\Exceptions\AdminSafetyException;
use App\Exceptions\DuplicateEmailException;
use App\Models\User;
use App\Repositories\UserRepository;
use DateTimeImmutable;

final class InMemoryUserRepository implements UserRepository
{
    /** @var array<int, User> */
    public array $users = [];
    public bool $passwordUpdated = false;
    public bool $loginRecorded = false;
    private int $nextId = 1;

    /** @param list<User> $users */
    public function __construct(array $users = [])
    {
        foreach ($users as $user) {
            $this->users[$user->id] = $user;
            $this->nextId = max($this->nextId, $user->id + 1);
        }
    }

    public function findById(int $id): ?User { return $this->users[$id] ?? null; }
    public function findByEmail(string $email): ?User
    {
        foreach ($this->users as $user) {
            if ($user->email === $email) {
                return $user;
            }
        }
        return null;
    }
    public function findByUsername(string $username): ?User
    {
        foreach ($this->users as $user) {
            if ($user->username === $username) {
                return $user;
            }
        }
        return null;
    }
    public function findByLoginIdentifier(string $identifier): ?User
    {
        return str_contains($identifier, '@') ? $this->findByEmail($identifier) : $this->findByUsername($identifier);
    }
    public function all(): array { return array_values($this->users); }

    public function create(array $data): User
    {
        if ($this->emailExists($data['email'])) {
            throw new DuplicateEmailException();
        }
        $now = new DateTimeImmutable();
        if ($this->usernameExists($data['username'])) {
            throw new \App\Exceptions\DuplicateUsernameException();
        }
        $user = new User($this->nextId++, $data['username'], $data['email'], $data['password_hash'], $data['first_name'], $data['last_name'], $data['role'], $data['is_active'], null, $now, $now);
        return $this->users[$user->id] = $user;
    }

    public function update(int $id, array $data): User
    {
        $current = $this->users[$id] ?? throw new \OutOfBoundsException();
        $actorId = (int) ($data['acting_user_id'] ?? 0);
        if ($id === $actorId && $current->isAdmin() && $data['role'] !== User::ROLE_ADMIN) {
            throw new AdminSafetyException('You cannot remove your own administrator role.');
        }
        if ($id === $actorId && !$data['is_active']) {
            throw new AdminSafetyException('You cannot deactivate your own account.');
        }
        if ($current->isAdmin() && $current->isActive && ($data['role'] !== User::ROLE_ADMIN || !$data['is_active']) && $this->activeAdminCount($id) === 0) {
            throw new AdminSafetyException('The last active administrator cannot be deactivated or demoted.');
        }
        $updated = new User($id, $data['username'], $data['email'], $data['password_hash'] ?? $current->passwordHash, $data['first_name'], $data['last_name'], $data['role'], $data['is_active'], $current->lastLoginAt, $current->createdAt, new DateTimeImmutable());
        return $this->users[$id] = $updated;
    }

    public function updatePasswordHash(int $id, string $passwordHash): void
    {
        $user = $this->users[$id];
        $this->users[$id] = new User($user->id, $user->username, $user->email, $passwordHash, $user->firstName, $user->lastName, $user->role, $user->isActive, $user->lastLoginAt, $user->createdAt, new DateTimeImmutable());
        $this->passwordUpdated = true;
    }
    public function recordLogin(int $id): void { $this->loginRecorded = true; }

    public function setActive(int $id, bool $active, int $actingUserId): User
    {
        $user = $this->users[$id] ?? throw new \OutOfBoundsException();
        if (!$active && $id === $actingUserId) {
            throw new AdminSafetyException('You cannot deactivate your own account.');
        }
        if (!$active && $user->isAdmin() && $user->isActive && $this->activeAdminCount($id) === 0) {
            throw new AdminSafetyException('The last active administrator cannot be deactivated or demoted.');
        }
        return $this->users[$id] = new User($user->id, $user->username, $user->email, $user->passwordHash, $user->firstName, $user->lastName, $user->role, $active, $user->lastLoginAt, $user->createdAt, new DateTimeImmutable());
    }

    public function emailExists(string $email, ?int $exceptId = null): bool
    {
        foreach ($this->users as $user) {
            if ($user->email === $email && $user->id !== $exceptId) {
                return true;
            }
        }
        return false;
    }

    public function usernameExists(string $username, ?int $exceptId = null): bool
    {
        foreach ($this->users as $user) {
            if (strtolower($user->username) === strtolower($username) && $user->id !== $exceptId) {
                return true;
            }
        }
        return false;
    }

    private function activeAdminCount(int $exceptId): int
    {
        return count(array_filter($this->users, static fn (User $user): bool => $user->id !== $exceptId && $user->isAdmin() && $user->isActive));
    }
}
