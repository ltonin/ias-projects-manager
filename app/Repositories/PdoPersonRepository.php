<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Database\ConnectionFactory;
use App\Exceptions\DuplicatePersonEmailException;
use App\Exceptions\UserAlreadyLinkedException;
use App\Models\Person;
use App\Models\PersonPage;
use App\Models\UserLinkOption;
use DateTimeImmutable;
use PDO;
use PDOException;

final class PdoPersonRepository implements PersonRepository
{
    private ?PDO $pdo = null;

    public function __construct(private readonly ConnectionFactory $connections)
    {
    }

    public function findById(int $id): ?Person
    {
        $statement = $this->connection()->prepare($this->selectSql() . ' WHERE p.id = :id');
        $statement->execute(['id' => $id]);
        $row = $statement->fetch();
        return is_array($row) ? $this->hydrate($row) : null;
    }

    public function findByUserId(int $userId): ?Person
    {
        $statement = $this->connection()->prepare($this->selectSql() . ' WHERE p.user_id = :user_id');
        $statement->execute(['user_id' => $userId]);
        $row = $statement->fetch();
        return is_array($row) ? $this->hydrate($row) : null;
    }

    public function search(array $filters, int $page, int $perPage): PersonPage
    {
        [$where, $parameters] = $this->where($filters);
        $count = $this->connection()->prepare('SELECT COUNT(*) FROM people p LEFT JOIN users u ON u.id = p.user_id' . $where);
        $count->execute($parameters);
        $total = (int) $count->fetchColumn();
        $offset = ($page - 1) * $perPage;

        $statement = $this->connection()->prepare(
            $this->selectSql() . $where . ' ORDER BY p.last_name ASC, p.first_name ASC, p.id ASC LIMIT :limit OFFSET :offset'
        );
        foreach ($parameters as $key => $value) {
            $statement->bindValue(':' . $key, $value);
        }
        $statement->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $statement->bindValue(':offset', $offset, PDO::PARAM_INT);
        $statement->execute();

        return new PersonPage(
            array_map(fn (array $row): Person => $this->hydrate($row), $statement->fetchAll()),
            $total,
            $page,
            $perPage,
        );
    }

    public function create(array $data): Person
    {
        try {
            $statement = $this->connection()->prepare(
                'INSERT INTO people
                 (user_id, first_name, last_name, institutional_email, affiliation, position_type,
                  is_internal, active_from, active_to, is_active, notes)
                 VALUES
                 (:user_id, :first_name, :last_name, :institutional_email, :affiliation, :position_type,
                  :is_internal, :active_from, :active_to, :is_active, :notes)'
            );
            $statement->execute($this->parameters($data));
        } catch (PDOException $exception) {
            $this->translateConstraint($exception);
            throw $exception;
        }
        return $this->findById((int) $this->connection()->lastInsertId())
            ?? throw new \RuntimeException('Created person could not be loaded.');
    }

    public function update(int $id, array $data): Person
    {
        try {
            $statement = $this->connection()->prepare(
                'UPDATE people SET user_id = :user_id, first_name = :first_name, last_name = :last_name,
                 institutional_email = :institutional_email, affiliation = :affiliation,
                 position_type = :position_type, is_internal = :is_internal, active_from = :active_from,
                 active_to = :active_to, is_active = :is_active, notes = :notes WHERE id = :id'
            );
            $statement->execute(['id' => $id] + $this->parameters($data));
            if ($statement->rowCount() === 0 && $this->findById($id) === null) {
                throw new \OutOfBoundsException('Person not found.');
            }
        } catch (PDOException $exception) {
            $this->translateConstraint($exception);
            throw $exception;
        }
        return $this->findById($id) ?? throw new \OutOfBoundsException('Person not found.');
    }

    public function setActive(int $id, bool $active): Person
    {
        $statement = $this->connection()->prepare('UPDATE people SET is_active = :active WHERE id = :id');
        $statement->execute(['id' => $id, 'active' => $active ? 1 : 0]);
        return $this->findById($id) ?? throw new \OutOfBoundsException('Person not found.');
    }

    public function emailExists(string $email, ?int $exceptId = null): bool
    {
        return $this->exists('institutional_email', $email, $exceptId);
    }

    public function userIsLinked(int $userId, ?int $exceptPersonId = null): bool
    {
        return $this->exists('user_id', $userId, $exceptPersonId);
    }

    public function userExists(int $userId): bool
    {
        $statement = $this->connection()->prepare('SELECT COUNT(*) FROM users WHERE id = :id');
        $statement->execute(['id' => $userId]);
        return (int) $statement->fetchColumn() > 0;
    }

    public function availableUsers(?int $currentPersonId = null): array
    {
        $sql = 'SELECT u.id, u.username, u.first_name, u.last_name, u.email, u.role, u.is_active
                FROM users u LEFT JOIN people p ON p.user_id = u.id WHERE p.id IS NULL';
        $parameters = [];
        if ($currentPersonId !== null) {
            $sql .= ' OR p.id = :person_id';
            $parameters['person_id'] = $currentPersonId;
        }
        $sql .= ' ORDER BY u.username ASC, u.id ASC';
        $statement = $this->connection()->prepare($sql);
        $statement->execute($parameters);
        return array_map(static fn (array $row): UserLinkOption => new UserLinkOption(
            (int) $row['id'],
            (string) $row['username'],
            (string) $row['first_name'],
            (string) $row['last_name'],
            (string) $row['email'],
            (string) $row['role'],
            (bool) $row['is_active'],
        ), $statement->fetchAll());
    }

    public function count(): int
    {
        return (int) $this->connection()->query('SELECT COUNT(*) FROM people')->fetchColumn();
    }

    private function connection(): PDO { return $this->pdo ??= $this->connections->create(); }

    private function selectSql(): string
    {
        return 'SELECT p.*, u.username AS linked_username FROM people p LEFT JOIN users u ON u.id = p.user_id';
    }

    /** @param array<string, mixed> $data @return array<string, mixed> */
    private function parameters(array $data): array
    {
        return [
            'user_id' => $data['user_id'],
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'institutional_email' => $data['institutional_email'],
            'affiliation' => $data['affiliation'],
            'position_type' => $data['position_type'],
            'is_internal' => $data['is_internal'] ? 1 : 0,
            'active_from' => $data['active_from'],
            'active_to' => $data['active_to'],
            'is_active' => $data['is_active'] ? 1 : 0,
            'notes' => $data['notes'],
        ];
    }

    /** @param array{search:string,active:string,internal:string,position_type:string,linked:string} $filters
     * @return array{string, array<string, mixed>}
     */
    private function where(array $filters): array
    {
        $conditions = [];
        $parameters = [];
        if ($filters['search'] !== '') {
            $conditions[] = "(p.first_name LIKE :search_first ESCAPE '=' OR p.last_name LIKE :search_last ESCAPE '='
                OR p.institutional_email LIKE :search_email ESCAPE '=' OR p.affiliation LIKE :search_affiliation ESCAPE '='
                OR u.username LIKE :search_username ESCAPE '=')";
            $search = '%' . strtr($filters['search'], ['=' => '==', '%' => '=%', '_' => '=_']) . '%';
            foreach (['search_first', 'search_last', 'search_email', 'search_affiliation', 'search_username'] as $key) {
                $parameters[$key] = $search;
            }
        }
        if ($filters['active'] !== 'all') {
            $conditions[] = 'p.is_active = :active';
            $parameters['active'] = $filters['active'] === 'active' ? 1 : 0;
        }
        if ($filters['internal'] !== 'all') {
            $conditions[] = 'p.is_internal = :internal';
            $parameters['internal'] = $filters['internal'] === 'internal' ? 1 : 0;
        }
        if ($filters['position_type'] !== '') {
            $conditions[] = 'p.position_type = :position_type';
            $parameters['position_type'] = $filters['position_type'];
        }
        if ($filters['linked'] !== 'all') {
            $conditions[] = $filters['linked'] === 'linked' ? 'p.user_id IS NOT NULL' : 'p.user_id IS NULL';
        }
        return [$conditions === [] ? '' : ' WHERE ' . implode(' AND ', $conditions), $parameters];
    }

    private function exists(string $column, string|int $value, ?int $exceptId): bool
    {
        $sql = sprintf('SELECT COUNT(*) FROM people WHERE %s = :value', $column);
        $parameters = ['value' => $value];
        if ($exceptId !== null) {
            $sql .= ' AND id <> :id';
            $parameters['id'] = $exceptId;
        }
        $statement = $this->connection()->prepare($sql);
        $statement->execute($parameters);
        return (int) $statement->fetchColumn() > 0;
    }

    /** @param array<string, mixed> $row */
    private function hydrate(array $row): Person
    {
        return new Person(
            (int) $row['id'],
            $row['user_id'] === null ? null : (int) $row['user_id'],
            (string) $row['first_name'],
            (string) $row['last_name'],
            $row['institutional_email'] === null ? null : (string) $row['institutional_email'],
            $row['affiliation'] === null ? null : (string) $row['affiliation'],
            (string) $row['position_type'],
            (bool) $row['is_internal'],
            $row['active_from'] === null ? null : new DateTimeImmutable((string) $row['active_from']),
            $row['active_to'] === null ? null : new DateTimeImmutable((string) $row['active_to']),
            (bool) $row['is_active'],
            $row['notes'] === null ? null : (string) $row['notes'],
            new DateTimeImmutable((string) $row['created_at']),
            new DateTimeImmutable((string) $row['updated_at']),
            $row['linked_username'] === null ? null : (string) $row['linked_username'],
        );
    }

    private function translateConstraint(PDOException $exception): void
    {
        if (($exception->errorInfo[0] ?? '') !== '23000') {
            return;
        }
        $message = strtolower((string) ($exception->errorInfo[2] ?? ''));
        if (str_contains($message, 'people_user_unique')) {
            throw new UserAlreadyLinkedException('That user is already linked to another person.', 0, $exception);
        }
        if (str_contains($message, 'people_institutional_email_unique')) {
            throw new DuplicatePersonEmailException('That institutional email is already in use.', 0, $exception);
        }
    }
}
