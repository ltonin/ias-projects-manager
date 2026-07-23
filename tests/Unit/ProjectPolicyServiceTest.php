<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Auth\ProjectPolicy;
use App\Exceptions\AuthorizationException;
use App\Models\ProjectManagerOption;
use App\Models\User;
use App\Services\ProjectService;
use App\Validation\ProjectValidator;
use PHPUnit\Framework\TestCase;
use Tests\Fakes\InMemoryProjectRepository;
use Tests\Support\PersonFactory;
use Tests\Support\UserFactory;

final class ProjectPolicyServiceTest extends TestCase
{
    public function testLinkedManagerCreatesOnlyForSelfAndCanManageOwnProject(): void
    {
        [$service, $repository] = $this->service();
        $manager = UserFactory::make(role: User::ROLE_PROJECT_MANAGER);
        $person = PersonFactory::make();
        $project = $service->create($this->input('1'), $manager, $person);

        self::assertSame(1, $project->managerPersonId);
        self::assertTrue((new ProjectPolicy())->canViewNotes($manager, $person, $project));
        self::assertSame('active', $service->changeStatus($project, 'active', $manager, $person)->status);
        self::assertCount(1, $repository->projects);
    }

    public function testUnlinkedManagerCannotCreate(): void
    {
        $this->expectException(AuthorizationException::class);
        $this->expectExceptionMessage(ProjectPolicy::MISSING_PERSON_MESSAGE);
        [$service] = $this->service();
        $service->create($this->input(''), UserFactory::make(role: User::ROLE_PROJECT_MANAGER), null);
    }

    public function testManagerCannotAssignOrEditAnotherPersonsProject(): void
    {
        [$service] = $this->service();
        $manager = UserFactory::make(role: User::ROLE_PROJECT_MANAGER);
        $person = PersonFactory::make();

        try {
            $service->create($this->input('2'), $manager, $person);
            self::fail('A manager assigned another person.');
        } catch (AuthorizationException) {
        }

        $project = $service->create($this->input('2'), UserFactory::make(role: User::ROLE_ADMIN), $person);
        $this->expectException(AuthorizationException::class);
        $service->update($project, $this->input('2'), $manager, $person);
    }

    public function testViewerCanViewButCannotEditOrReadNotes(): void
    {
        [$service] = $this->service();
        $project = $service->create($this->input('1'), UserFactory::make(role: User::ROLE_ADMIN), PersonFactory::make());
        $viewer = UserFactory::make(role: User::ROLE_VIEWER);
        $policy = new ProjectPolicy();

        self::assertFalse($policy->canEdit($viewer, null, $project));
        self::assertFalse($policy->canViewNotes($viewer, null, $project));
        self::assertNull($project->withoutNotes()->notes);
    }

    /** @return array{ProjectService,InMemoryProjectRepository} */
    private function service(): array
    {
        $repository = new InMemoryProjectRepository([
            new ProjectManagerOption(1, 'Test Manager', 'Researcher', 'University', true, 'manager.one'),
            new ProjectManagerOption(2, 'Other Manager', 'Researcher', 'University', true, 'manager.two'),
        ]);

        return [new ProjectService($repository, new ProjectValidator(), new ProjectPolicy()), $repository];
    }

    /** @return array<string,string> */
    private function input(string $managerPersonId): array
    {
        return [
            'acronym' => 'TEST',
            'title' => 'Test project',
            'description' => '',
            'internal_code' => '',
            'grant_agreement_number' => '',
            'funding_agency' => '',
            'funding_programme' => '',
            'coordinator_organization' => '',
            'manager_person_id' => $managerPersonId,
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'status' => 'planned',
            'total_budget' => '1000',
            'currency' => 'eur',
            'website_url' => 'https://example.test',
            'notes' => 'private note',
        ];
    }
}
