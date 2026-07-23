<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Database\ConnectionFactory;
use App\Exceptions\AdminSafetyException;
use App\Exceptions\DuplicateEmailException;
use App\Exceptions\DuplicateUsernameException;
use App\Models\User;
use DateTimeImmutable;
use PDO;
use PDOException;

final class PdoUserRepository implements UserRepository
{
    private ?PDO $pdo = null;

    public function __construct(private readonly ConnectionFactory $connections)
    {
    }

    public function findById(int $id): ?User
    {
        $statement = $this->connection()->prepare('SELECT * FROM users WHERE id = :id');
        $statement->execute(['id' => $id]);
        $row = $statement->fetch();
        return is_array($row) ? $this->hydrate($row) : null;
    }

    public function findByEmail(string $email): ?User
    {
        $statement = $this->connection()->prepare('SELECT * FROM users WHERE email = :email');
        $statement->execute(['email' => $email]);
        $row = $statement->fetch();
        return is_array($row) ? $this->hydrate($row) : null;
    }

    public function findByUsername(string $username): ?User
    {
        $statement = $this->connection()->prepare('SELECT * FROM users WHERE username = :username');
        $statement->execute(['username' => $username]);
        $row = $statement->fetch();
        return is_array($row) ? $this->hydrate($row) : null;
    }

    public function findByLoginIdentifier(string $identifier): ?User
    {
        return str_contains($identifier, '@')
            ? $this->findByEmail($identifier)
            : $this->findByUsername($identifier);
    }

    public function all(): array
    {
        $statement = $this->connection()->query('SELECT * FROM users ORDER BY last_name, first_name, id');
        return array_map(fn (array $row): User => $this->hydrate($row), $statement->fetchAll());
    }

    public function create(array $data): User
    {
        try {
            $statement = $this->connection()->prepare(
                'INSERT INTO users (username, email, password_hash, first_name, last_name, role, is_active)
                 VALUES (:username, :email, :password_hash, :first_name, :last_name, :role, :is_active)'
            );
            $statement->execute([
                'username' => $data['username'],
                'email' => $data['email'],
                'password_hash' => $data['password_hash'],
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'role' => $data['role'],
                'is_active' => $data['is_active'] ? 1 : 0,
            ]);
        } catch (PDOException $exception) {
            $this->translateDuplicate($exception);
            throw $exception;
        }

        return $this->findById((int) $this->connection()->lastInsertId())
            ?? throw new \RuntimeException('Created user could not be loaded.');
    }

    public function update(int $id, array $data): User
    {
        $pdo = $this->connection();
        $pdo->beginTransaction();
        try {
            $current = $this->lockUser($id);
            if ($current === null) {
                throw new \OutOfBoundsException('User not found.');
            }
            if ($id === ($data['acting_user_id'] ?? 0) && $current->isAdmin() && $data['role'] !== User::ROLE_ADMIN) {
                throw new AdminSafetyException('You cannot remove your own administrator role.');
            }
            if ($id === ($data['acting_user_id'] ?? 0) && !$data['is_active']) {
                throw new AdminSafetyException('You cannot deactivate your own account.');
            }
            if ($current->isAdmin() && $current->isActive && ($data['role'] !== User::ROLE_ADMIN || !$data['is_active'])) {
                $this->assertAnotherActiveAdmin($id);
            }

            $passwordSql = array_key_exists('password_hash', $data) ? ', password_hash = :password_hash' : '';
            $statement = $pdo->prepare(
                'UPDATE users SET username = :username, email = :email, first_name = :first_name, last_name = :last_name,
                 role = :role, is_active = :is_active' . $passwordSql . ' WHERE id = :id'
            );
            $parameters = [
                'id' => $id,
                'username' => $data['username'],
                'email' => $data['email'],
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'role' => $data['role'],
                'is_active' => $data['is_active'] ? 1 : 0,
            ];
            if (array_key_exists('password_hash', $data)) {
                $parameters['password_hash'] = $data['password_hash'];
            }
            $statement->execute($parameters);
            $pdo->commit();
        } catch (PDOException $exception) {
            $pdo->rollBack();
            $this->translateDuplicate($exception);
            throw $exception;
        } catch (\Throwable $exception) {
            $pdo->rollBack();
            throw $exception;
        }

        return $this->findById($id) ?? throw new \RuntimeException('Updated user could not be loaded.');
    }

    public function updatePasswordHash(int $id, string $passwordHash): void
    {
        $statement = $this->connection()->prepare('UPDATE users SET password_hash = :password_hash WHERE id = :id');
        $statement->execute(['id' => $id, 'password_hash' => $passwordHash]);
    }

    public function recordLogin(int $id): void
    {
        $statement = $this->connection()->prepare('UPDATE users SET last_login_at = CURRENT_TIMESTAMP WHERE id = :id');
        $statement->execute(['id' => $id]);
    }

    public function setActive(int $id, bool $active, int $actingUserId): User
    {
        $pdo = $this->connection();
        $pdo->beginTransaction();
        try {
            $user = $this->lockUser($id);
            if ($user === null) {
                throw new \OutOfBoundsException('User not found.');
            }
            if (!$active && $id === $actingUserId) {
                throw new AdminSafetyException('You cannot deactivate your own account.');
            }
            if (!$active && $user->isAdmin() && $user->isActive) {
                $this->assertAnotherActiveAdmin($id);
            }
            $statement = $pdo->prepare('UPDATE users SET is_active = :is_active WHERE id = :id');
            $statement->execute(['id' => $id, 'is_active' => $active ? 1 : 0]);
            $pdo->commit();
        } catch (\Throwable $exception) {
            $pdo->rollBack();
            throw $exception;
        }

        return $this->findById($id) ?? throw new \RuntimeException('Updated user could not be loaded.');
    }

    public function emailExists(string $email, ?int $exceptId = null): bool
    {
        $sql = 'SELECT COUNT(*) FROM users WHERE email = :email';
        $parameters = ['email' => $email];
        if ($exceptId !== null) {
            $sql .= ' AND id <> :id';
            $parameters['id'] = $exceptId;
        }
        $statement = $this->connection()->prepare($sql);
        $statement->execute($parameters);
        return (int) $statement->fetchColumn() > 0;
    }

    public function usernameExists(string $username, ?int $exceptId = null): bool
    {
        $sql = 'SELECT COUNT(*) FROM users WHERE username = :username';
        $parameters = ['username' => $username];
        if ($exceptId !== null) {
            $sql .= ' AND id <> :id';
            $parameters['id'] = $exceptId;
        }
        $statement = $this->connection()->prepare($sql);
        $statement->execute($parameters);
        return (int) $statement->fetchColumn() > 0;
    }

    private function connection(): PDO
    {
        return $this->pdo ??= $this->connections->create();
    }

    private function lockUser(int $id): ?User
    {
        $statement = $this->connection()->prepare('SELECT * FROM users WHERE id = :id FOR UPDATE');
        $statement->execute(['id' => $id]);
        $row = $statement->fetch();
        return is_array($row) ? $this->hydrate($row) : null;
    }

    private function assertAnotherActiveAdmin(int $excludedId): void
    {
        $statement = $this->connection()->prepare(
            'SELECT id FROM users WHERE role = :role AND is_active = 1 AND id <> :id FOR UPDATE'
        );
        $statement->execute(['role' => User::ROLE_ADMIN, 'id' => $excludedId]);
        if ($statement->fetchColumn() === false) {
            throw new AdminSafetyException('The last active administrator cannot be deactivated or demoted.');
        }
    }

    /** @param array<string, mixed> $row */
    private function hydrate(array $row): User
    {
        return new User(
            (int) $row['id'],
            (string) $row['username'],
            (string) $row['email'],
            (string) $row['password_hash'],
            (string) $row['first_name'],
            (string) $row['last_name'],
            (string) $row['role'],
            (bool) $row['is_active'],
            $row['last_login_at'] === null ? null : new DateTimeImmutable((string) $row['last_login_at']),
            new DateTimeImmutable((string) $row['created_at']),
            new DateTimeImmutable((string) $row['updated_at']),
        );
    }

    private function translateDuplicate(PDOException $exception): void
    {
        if (($exception->errorInfo[0] ?? '') === '23000') {
            if (str_contains(strtolower((string) ($exception->errorInfo[2] ?? '')), 'users_username_unique')) {
                throw new DuplicateUsernameException('That username is already in use.', 0, $exception);
            }
            throw new DuplicateEmailException('That email address is already in use.', 0, $exception);
        }
    }
}
