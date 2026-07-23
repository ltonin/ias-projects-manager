<?php

declare(strict_types=1);

namespace App\Services;

use App\Auth\ProjectPolicy;
use App\Exceptions\AuthorizationException;
use App\Models\Person;
use App\Models\Project;
use App\Models\ProjectParticipant;
use App\Models\ProjectParticipantPage;
use App\Models\User;
use App\Repositories\PersonRepository;
use App\Repositories\ProjectParticipantRepository;
use App\Repositories\ProjectRepository;
use App\Validation\ProjectParticipantValidator;

final class ProjectParticipantService
{
    public const DEFAULT_PER_PAGE = 25;
    public const MAX_PER_PAGE = 100;

    public function __construct(
        private readonly ProjectParticipantRepository $participants,
        private readonly ProjectRepository $projects,
        private readonly PersonRepository $people,
        private readonly ProjectParticipantValidator $validator,
        private readonly ProjectPolicy $policy,
    ) {
    }

    /** @param array<string,mixed> $input @return array<string,string> */
    public function validateCreate(Project $project, array $input): array
    {
        $personId = $this->positiveId($input['person_id'] ?? null);
        if ($personId === null || ($person = $this->people->findById($personId)) === null) {
            return ['person_id' => 'Select an existing person.'];
        }
        $errors = $this->validator->validate($input, $project, $person);
        if ($this->participants->personAlreadyParticipates($project->id, $personId)) {
            $errors['person_id'] = 'That person already participates in this project.';
        }
        return $errors;
    }

    /** @param array<string,mixed> $input @return array<string,string> */
    public function validateUpdate(Project $project, ProjectParticipant $participant, array $input): array
    {
        $person = $this->people->findById($participant->personId);
        return $person === null
            ? ['person_id' => 'The participant person no longer exists.']
            : $this->validator->validate($input, $project, $person);
    }

    /** @param array<string,mixed> $input */
    public function create(Project $project, array $input, User $user, ?Person $currentPerson): ProjectParticipant
    {
        $this->requireCurrentProjectAndAuthorization($project, $user, $currentPerson);
        $personId = $this->positiveId($input['person_id'] ?? null);
        if ($personId === null || $this->people->findById($personId) === null) throw new \InvalidArgumentException('Person not found.');
        if ($this->participants->personAlreadyParticipates($project->id, $personId)) {
            throw new \App\Exceptions\DuplicateProjectParticipantException('That person already participates in this project.');
        }
        return $this->participants->create(
            ['project_id' => $project->id, 'person_id' => $personId] + $this->normalize($input),
            $user->isProjectManager() ? $currentPerson?->id : null,
        );
    }

    /** @param array<string,mixed> $input */
    public function update(Project $project, ProjectParticipant $participant, array $input, User $user, ?Person $currentPerson): ProjectParticipant
    {
        $this->assertRelationship($project, $participant);
        $this->requireCurrentProjectAndAuthorization($project, $user, $currentPerson);
        return $this->participants->update(
            $participant->id, $project->id, $this->normalize($input),
            $user->isProjectManager() ? $currentPerson?->id : null,
        );
    }

    public function setActive(Project $project, ProjectParticipant $participant, bool $active, User $user, ?Person $currentPerson): ProjectParticipant
    {
        $this->assertRelationship($project, $participant);
        $this->requireCurrentProjectAndAuthorization($project, $user, $currentPerson);
        return $this->participants->setActive(
            $participant->id, $project->id, $active, $user->isProjectManager() ? $currentPerson?->id : null,
        );
    }

    public function remove(Project $project, ProjectParticipant $participant, User $user, ?Person $currentPerson): void
    {
        $this->assertRelationship($project, $participant);
        $this->requireCurrentProjectAndAuthorization($project, $user, $currentPerson);
        $this->participants->delete(
            $participant->id, $project->id, $user->isProjectManager() ? $currentPerson?->id : null,
        );
    }

    /** @param array<string,mixed> $query @return array{page:ProjectParticipantPage,filters:array{search:string,active:string,project_role:string,internal:string,person_active:string}} */
    public function listing(Project $project, array $query): array
    {
        $filters = [
            'search' => mb_substr(trim((string) ($query['search'] ?? '')), 0, 200),
            'active' => in_array((string) ($query['active'] ?? 'all'), ['all','active','inactive'], true) ? (string) ($query['active'] ?? 'all') : 'all',
            'project_role' => array_key_exists((string) ($query['project_role'] ?? ''), ProjectParticipant::ROLE_LABELS) ? (string) $query['project_role'] : '',
            'internal' => in_array((string) ($query['internal'] ?? 'all'), ['all','internal','external'], true) ? (string) ($query['internal'] ?? 'all') : 'all',
            'person_active' => in_array((string) ($query['person_active'] ?? 'all'), ['all','active','inactive'], true) ? (string) ($query['person_active'] ?? 'all') : 'all',
        ];
        $page = filter_var($query['page'] ?? 1, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: 1;
        $perPage = filter_var($query['per_page'] ?? self::DEFAULT_PER_PAGE, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => self::MAX_PER_PAGE]]) ?: self::DEFAULT_PER_PAGE;
        $result = $this->participants->listForProject($project->id, $filters, (int) $page, (int) $perPage);
        if ($result->total > 0 && $page > $result->pageCount()) {
            $result = $this->participants->listForProject($project->id, $filters, $result->pageCount(), (int) $perPage);
        }
        return ['page' => $result, 'filters' => $filters];
    }

    private function requireCurrentProjectAndAuthorization(Project $project, User $user, ?Person $person): void
    {
        $current = $this->projects->findById($project->id);
        if ($current === null) throw new \OutOfBoundsException('Project not found.');
        $this->policy->requireManageParticipants($user, $person, $current);
    }
    private function assertRelationship(Project $project, ProjectParticipant $participant): void
    {
        if ($participant->projectId !== $project->id) throw new \OutOfBoundsException('Participant not found.');
    }
    /** @param array<string,mixed> $input @return array<string,mixed> */
    private function normalize(array $input): array
    {
        $nullable = static fn (mixed $value): ?string => trim((string) $value) === '' ? null : trim((string) $value);
        return [
            'project_role' => trim((string) ($input['project_role'] ?? '')),
            'participation_start' => $nullable($input['participation_start'] ?? null),
            'participation_end' => $nullable($input['participation_end'] ?? null),
            'is_active' => isset($input['is_active']) && (string) $input['is_active'] === '1',
            'notes' => $nullable($input['notes'] ?? null),
        ];
    }
    private function positiveId(mixed $value): ?int
    {
        $id = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        return $id === false ? null : (int) $id;
    }
}
