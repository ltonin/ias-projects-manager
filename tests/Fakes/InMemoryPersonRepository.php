<?php

declare(strict_types=1);

namespace Tests\Fakes;

use App\Exceptions\DuplicatePersonEmailException;
use App\Exceptions\UserAlreadyLinkedException;
use App\Models\Person;
use App\Models\PersonPage;
use App\Models\UserLinkOption;
use App\Repositories\PersonRepository;
use DateTimeImmutable;

final class InMemoryPersonRepository implements PersonRepository
{
    /** @var array<int, Person> */
    public array $people = [];
    /** @var array<int, UserLinkOption> */
    public array $users = [];
    private int $nextId = 1;

    /** @param list<UserLinkOption> $users */
    public function __construct(array $users = [])
    {
        foreach ($users as $user) { $this->users[$user->id] = $user; }
    }

    public function findById(int $id): ?Person { return $this->people[$id] ?? null; }
    public function findByUserId(int $userId): ?Person
    {
        foreach ($this->people as $person) { if ($person->userId === $userId) return $person; }
        return null;
    }
    public function search(array $filters, int $page, int $perPage): PersonPage
    {
        $items = array_values(array_filter($this->people, static function (Person $person) use ($filters): bool {
            $haystack = strtolower(implode(' ', [$person->firstName, $person->lastName, $person->institutionalEmail, $person->affiliation, $person->linkedUsername]));
            return ($filters['search'] === '' || str_contains($haystack, strtolower($filters['search'])))
                && ($filters['active'] === 'all' || $person->isActive === ($filters['active'] === 'active'))
                && ($filters['internal'] === 'all' || $person->isInternal === ($filters['internal'] === 'internal'))
                && ($filters['position_type'] === '' || $person->positionType === $filters['position_type'])
                && ($filters['linked'] === 'all' || ($person->userId !== null) === ($filters['linked'] === 'linked'));
        }));
        usort($items, static fn (Person $a, Person $b): int => [$a->lastName, $a->firstName, $a->id] <=> [$b->lastName, $b->firstName, $b->id]);
        return new PersonPage(array_slice($items, ($page - 1) * $perPage, $perPage), count($items), $page, $perPage);
    }
    public function create(array $data): Person
    {
        if ($data['institutional_email'] !== null && $this->emailExists($data['institutional_email'])) throw new DuplicatePersonEmailException();
        if ($data['user_id'] !== null && $this->userIsLinked($data['user_id'])) throw new UserAlreadyLinkedException();
        return $this->people[$this->nextId] = $this->make($this->nextId++, $data);
    }
    public function update(int $id, array $data): Person
    {
        if (!isset($this->people[$id])) throw new \OutOfBoundsException();
        if ($data['institutional_email'] !== null && $this->emailExists($data['institutional_email'], $id)) throw new DuplicatePersonEmailException();
        if ($data['user_id'] !== null && $this->userIsLinked($data['user_id'], $id)) throw new UserAlreadyLinkedException();
        return $this->people[$id] = $this->make($id, $data);
    }
    public function setActive(int $id, bool $active): Person
    {
        $person = $this->people[$id] ?? throw new \OutOfBoundsException();
        $data = [
            'user_id'=>$person->userId,'first_name'=>$person->firstName,'last_name'=>$person->lastName,
            'institutional_email'=>$person->institutionalEmail,'affiliation'=>$person->affiliation,
            'position_type'=>$person->positionType,'is_internal'=>$person->isInternal,
            'active_from'=>$person->activeFrom?->format('Y-m-d'),'active_to'=>$person->activeTo?->format('Y-m-d'),
            'is_active'=>$active,'notes'=>$person->notes,
            'default_monthly_capacity_hours'=>$person->defaultMonthlyCapacityHours,
        ];
        return $this->people[$id] = $this->make($id, $data);
    }
    public function emailExists(string $email, ?int $exceptId = null): bool
    {
        foreach ($this->people as $person) if ($person->id !== $exceptId && strtolower((string)$person->institutionalEmail) === strtolower($email)) return true;
        return false;
    }
    public function userIsLinked(int $userId, ?int $exceptPersonId = null): bool
    {
        foreach ($this->people as $person) if ($person->id !== $exceptPersonId && $person->userId === $userId) return true;
        return false;
    }
    public function userExists(int $userId): bool { return isset($this->users[$userId]); }
    public function availableUsers(?int $currentPersonId = null): array
    {
        return array_values(array_filter($this->users, fn (UserLinkOption $user): bool => !$this->userIsLinked($user->id, $currentPersonId)));
    }
    public function count(): int { return count($this->people); }
    public function capacityScope(string $role,?int$managerPersonId):array
    {
        $items=array_values($this->people);usort($items,static fn(Person$a,Person$b):int=>[$a->lastName,$a->firstName,$a->id]<=>[$b->lastName,$b->firstName,$b->id]);return$items;
    }

    /** @param array<string,mixed> $data */
    private function make(int $id, array $data): Person
    {
        $now = new DateTimeImmutable('2026-01-01');
        $username = $data['user_id'] === null ? null : ($this->users[$data['user_id']]->username ?? null);
        return new Person($id, $data['user_id'], $data['first_name'], $data['last_name'], $data['institutional_email'], $data['affiliation'], $data['position_type'], $data['is_internal'], $data['active_from']===null?null:new DateTimeImmutable($data['active_from']), $data['active_to']===null?null:new DateTimeImmutable($data['active_to']), $data['is_active'], $data['notes'], $now, $now, $username,$data['default_monthly_capacity_hours']??'125.00');
    }
}
