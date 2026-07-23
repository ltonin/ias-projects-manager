<?php

declare(strict_types=1);

namespace Tests\Fakes;

use App\Exceptions\AuthorizationException;
use App\Exceptions\DuplicateProjectParticipantException;
use App\Models\ParticipantPersonOption;
use App\Models\ProjectParticipant;
use App\Models\ProjectParticipantPage;
use App\Repositories\ProjectParticipantRepository;
use DateTimeImmutable;

final class InMemoryProjectParticipantRepository implements ProjectParticipantRepository
{
    /** @var array<int,ProjectParticipant> */
    public array $participants = [];
    /** @var array<int,ParticipantPersonOption> */
    public array $people = [];
    /** @var array<int,int|null> */
    public array $projectManagers = [];
    private int $nextId = 1;

    /** @param list<ParticipantPersonOption> $people */
    public function __construct(array $people = [])
    {
        foreach ($people as $person) $this->people[$person->id] = $person;
    }

    public function findById(int $id): ?ProjectParticipant { return $this->participants[$id] ?? null; }
    public function findByProjectAndPerson(int $projectId, int $personId): ?ProjectParticipant
    {
        foreach ($this->participants as $participant) if ($participant->projectId === $projectId && $participant->personId === $personId) return $participant;
        return null;
    }
    public function listForProject(int $projectId, array $filters, int $page, int $perPage): ProjectParticipantPage
    {
        $items = array_values(array_filter($this->participants, static function (ProjectParticipant $p) use ($projectId, $filters): bool {
            $haystack = strtolower(implode(' ', [$p->personFirstName,$p->personLastName,$p->institutionalEmail,$p->affiliation,$p->linkedUsername,$p->projectRole,$p->roleLabel()]));
            return $p->projectId === $projectId
                && ($filters['search'] === '' || str_contains($haystack, strtolower($filters['search'])))
                && ($filters['active'] === 'all' || $p->isActive === ($filters['active'] === 'active'))
                && ($filters['project_role'] === '' || $p->projectRole === $filters['project_role'])
                && ($filters['internal'] === 'all' || $p->personIsInternal === ($filters['internal'] === 'internal'))
                && ($filters['person_active'] === 'all' || $p->personIsActive === ($filters['person_active'] === 'active'));
        }));
        usort($items, static fn (ProjectParticipant $a, ProjectParticipant $b): int => [$b->isActive,$a->personLastName,$a->personFirstName,$a->id] <=> [$a->isActive,$b->personLastName,$b->personFirstName,$b->id]);
        $items = array_map(static fn (ProjectParticipant $p): ProjectParticipant => $p->withoutNotes(), $items);
        return new ProjectParticipantPage(array_slice($items, ($page - 1) * $perPage, $perPage), count($items), $page, $perPage);
    }
    public function summaryForProject(int $projectId, int $limit = 5): array
    {
        return array_slice($this->listForProject($projectId, ['search'=>'','active'=>'all','project_role'=>'','internal'=>'all','person_active'=>'all'], 1, $limit)->items, 0, $limit);
    }
    public function allForProject(int$projectId):array{return$this->summaryForProject($projectId,PHP_INT_MAX);}
    public function countForProject(int $projectId, ?bool $active = null): int
    {
        return count(array_filter($this->participants, static fn (ProjectParticipant $p): bool => $p->projectId === $projectId && ($active === null || $p->isActive === $active)));
    }
    public function create(array $data, ?int $requiredManagerPersonId = null): ProjectParticipant
    {
        $this->authorize($data['project_id'], $requiredManagerPersonId);
        if ($this->personAlreadyParticipates($data['project_id'], $data['person_id'])) throw new DuplicateProjectParticipantException();
        return $this->participants[$this->nextId] = $this->make($this->nextId++, $data);
    }
    public function update(int $id, int $projectId, array $data, ?int $requiredManagerPersonId = null): ProjectParticipant
    {
        $this->authorize($projectId, $requiredManagerPersonId);
        $current = $this->participants[$id] ?? throw new \OutOfBoundsException();
        if ($current->projectId !== $projectId) throw new \OutOfBoundsException();
        return $this->participants[$id] = $this->make($id, ['project_id'=>$projectId,'person_id'=>$current->personId]+$data);
    }
    public function setActive(int $id, int $projectId, bool $active, ?int $requiredManagerPersonId = null): ProjectParticipant
    {
        $current = $this->participants[$id] ?? throw new \OutOfBoundsException();
        return $this->update($id, $projectId, [
            'project_role'=>$current->projectRole,'participation_start'=>$current->participationStart?->format('Y-m-d'),
            'participation_end'=>$current->participationEnd?->format('Y-m-d'),'is_active'=>$active,'notes'=>$current->notes,
        ], $requiredManagerPersonId);
    }
    public function delete(int $id, int $projectId, ?int $requiredManagerPersonId = null): void
    {
        $this->authorize($projectId, $requiredManagerPersonId);
        if (!isset($this->participants[$id]) || $this->participants[$id]->projectId !== $projectId) throw new \OutOfBoundsException();
        unset($this->participants[$id]);
    }
    public function personAlreadyParticipates(int $projectId, int $personId, ?int $exceptId = null): bool
    {
        foreach ($this->participants as $p) if ($p->id !== $exceptId && $p->projectId === $projectId && $p->personId === $personId) return true;
        return false;
    }
    public function availablePeople(int $projectId): array
    {
        return array_values(array_filter($this->people, fn (ParticipantPersonOption $person): bool => $person->isActive&&!$this->personAlreadyParticipates($projectId, $person->id)));
    }
    private function authorize(int $projectId, ?int $requiredManager): void
    {
        if ($requiredManager !== null && ($this->projectManagers[$projectId] ?? null) !== $requiredManager) throw new AuthorizationException('Project ownership changed.');
    }
    /** @param array<string,mixed> $data */
    private function make(int $id, array $data): ProjectParticipant
    {
        $now = new DateTimeImmutable('2026-01-01');
        $person = $this->people[$data['person_id']] ?? new ParticipantPersonOption($data['person_id'], 'Test Person', 'Researcher', 'University', 'person@example.test', true, null, null, null);
        [$first, $last] = array_pad(explode(' ', $person->name, 2), 2, '');
        return new ProjectParticipant(
            $id,$data['project_id'],$data['person_id'],$data['project_role'],
            $data['participation_start']===null?null:new DateTimeImmutable($data['participation_start']),
            $data['participation_end']===null?null:new DateTimeImmutable($data['participation_end']),
            $data['is_active'],$data['notes'],$now,$now,$first,$last,$person->institutionalEmail,$person->affiliation,
            'researcher',true,$person->isActive,$person->activeFrom===null?null:new DateTimeImmutable($person->activeFrom),
            $person->activeTo===null?null:new DateTimeImmutable($person->activeTo),$person->username,true,'TEST','Test project','active',
        );
    }
}
