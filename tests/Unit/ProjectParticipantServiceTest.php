<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Auth\ProjectPolicy;
use App\Exceptions\AuthorizationException;
use App\Exceptions\DuplicateProjectParticipantException;
use App\Models\ParticipantPersonOption;
use App\Models\Project;
use App\Models\User;
use App\Services\ProjectParticipantService;
use App\Validation\ProjectParticipantValidator;
use PHPUnit\Framework\TestCase;
use Tests\Fakes\InMemoryPersonRepository;
use Tests\Fakes\InMemoryProjectParticipantRepository;
use Tests\Fakes\InMemoryProjectRepository;
use Tests\Support\UserFactory;

final class ProjectParticipantServiceTest extends TestCase
{
    public function testAdminAddsEditsActivatesDeactivatesAndRemovesWithoutChangingRelatedRecords(): void
    {
        [$service,$participants,$projects,$people,$project] = $this->context();
        $admin = UserFactory::make();
        $personCount = $people->count();
        $managerBefore = $project->managerPersonId;

        $created = $service->create($project, $this->input(), $admin, null);
        self::assertSame('researcher', $created->projectRole);
        self::assertSame(1, $participants->countForProject($project->id));
        $updated = $service->update($project, $created, $this->input(['project_role'=>'task_leader','participation_start'=>'2026-03-01']), $admin, null);
        self::assertSame('task_leader', $updated->projectRole);
        self::assertFalse($service->setActive($project, $updated, false, $admin, null)->isActive);
        self::assertTrue($service->setActive($project, $updated, true, $admin, null)->isActive);
        $service->remove($project, $updated, $admin, null);

        self::assertSame(0, $participants->countForProject($project->id));
        self::assertSame($personCount, $people->count());
        self::assertSame($managerBefore, $projects->findById($project->id)?->managerPersonId);
    }

    public function testOwnerCanManageAndNotesAreRemovedFromLists(): void
    {
        [$service,$participants,,, $project,$managerPerson] = $this->context();
        $manager = UserFactory::make(role: User::ROLE_PROJECT_MANAGER);
        $created = $service->create($project, $this->input(['notes'=>'private']), $manager, $managerPerson);
        $listing = $service->listing($project, ['search'=>'Ada','active'=>'active','project_role'=>'researcher','internal'=>'internal','person_active'=>'active']);

        self::assertSame(1, $listing['page']->total);
        self::assertNull($listing['page']->items[0]->notes);
        self::assertSame('private', $participants->findById($created->id)?->notes);
    }

    public function testNonOwnerUnlinkedManagerParticipantAndViewerCannotWrite(): void
    {
        foreach ([
            [User::ROLE_PROJECT_MANAGER, 2],
            [User::ROLE_PROJECT_MANAGER, null],
            [User::ROLE_PARTICIPANT, null],
            [User::ROLE_VIEWER, null],
        ] as [$role,$personId]) {
            [$service,,,, $project,$managerPerson,$otherPerson] = $this->context();
            $actorPerson = $personId === 2 ? $otherPerson : ($personId === 1 ? $managerPerson : null);
            try {
                $service->create($project, $this->input(), UserFactory::make(role: $role), $actorPerson);
                self::fail($role . ' unexpectedly managed participants.');
            } catch (AuthorizationException) {
                self::assertTrue(true);
            }
        }
    }

    public function testOwnershipIsRecheckedAgainstCurrentProjectAndAtRepositoryWrite(): void
    {
        [$service,$participants,$projects,,, $managerPerson] = $this->context();
        $project = $projects->findById(1);
        self::assertInstanceOf(Project::class, $project);
        $manager = UserFactory::make(role: User::ROLE_PROJECT_MANAGER);
        $participants->projectManagers[1] = 2;
        $this->expectException(AuthorizationException::class);
        $service->create($project, $this->input(), $manager, $managerPerson);
    }

    public function testDuplicateSameProjectRejectedButSamePersonDifferentProjectAllowedAndManagerIsNotAutomatic(): void
    {
        [$service,$participants,$projects,, $project] = $this->context();
        $admin = UserFactory::make();
        self::assertSame(0, $participants->countForProject($project->id));
        $service->create($project, $this->input(), $admin, null);
        try {
            $service->create($project, $this->input(), $admin, null);
            self::fail('Duplicate participation was accepted.');
        } catch (DuplicateProjectParticipantException) {
            self::assertTrue(true);
        }
        $second = $projects->create($this->projectData('TWO',2));
        $service->create($second, $this->input(), $admin, null);
        self::assertSame(2, count($participants->participants));
    }

    public function testValidationRejectsMissingPersonAndDuplicate(): void
    {
        [$service,,,, $project] = $this->context();
        self::assertArrayHasKey('person_id', $service->validateCreate($project, $this->input(['person_id'=>'999'])));
        $service->create($project, $this->input(), UserFactory::make(), null);
        self::assertArrayHasKey('person_id', $service->validateCreate($project, $this->input()));
    }

    public function testInactivePeopleAreNeitherEligibleNorAcceptedByCraftedRequests(): void
    {
        [$service,$participants,,$people,$project] = $this->context();
        $data = $this->personData('Inactive','Person','inactive@example.test');
        $data['is_active'] = false;
        $inactive = $people->create($data);
        $participants->people[$inactive->id] = new ParticipantPersonOption(
            $inactive->id,
            $inactive->fullName(),
            $inactive->positionLabel(),
            $inactive->affiliation,
            $inactive->institutionalEmail,
            false,
            null,
            '2025-01-01',
            null,
        );

        $ids = array_map(
            static fn (ParticipantPersonOption $person): int => $person->id,
            $participants->availablePeople($project->id),
        );
        self::assertNotContains($inactive->id, $ids);

        $input = $this->input(['person_id'=>(string)$inactive->id]);
        self::assertSame('Select an active eligible person.', $service->validateCreate($project, $input)['person_id']);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Select an active eligible person.');
        $service->create($project, $input, UserFactory::make(), null);
    }

    /** @return array{ProjectParticipantService,InMemoryProjectParticipantRepository,InMemoryProjectRepository,InMemoryPersonRepository,Project,\App\Models\Person,\App\Models\Person} */
    private function context(): array
    {
        $people = new InMemoryPersonRepository();
        $manager = $people->create($this->personData('Grace','Hopper','manager@example.test'));
        $other = $people->create($this->personData('Ada','Lovelace','ada@example.test'));
        $projects = new InMemoryProjectRepository();
        $project = $projects->create($this->projectData('TEST',$manager->id));
        $participants = new InMemoryProjectParticipantRepository([
            new ParticipantPersonOption($manager->id,$manager->fullName(),$manager->positionLabel(),$manager->affiliation,$manager->institutionalEmail,true,null,'2025-01-01',null),
            new ParticipantPersonOption($other->id,$other->fullName(),$other->positionLabel(),$other->affiliation,$other->institutionalEmail,true,null,'2025-01-01',null),
        ]);
        $participants->projectManagers[$project->id] = $manager->id;
        $service = new ProjectParticipantService($participants,$projects,$people,new ProjectParticipantValidator(),new ProjectPolicy());
        return [$service,$participants,$projects,$people,$project,$manager,$other];
    }

    /** @param array<string,string> $overrides @return array<string,string> */
    private function input(array $overrides=[]): array
    {
        return $overrides+['person_id'=>'2','project_role'=>'researcher','participation_start'=>'2026-02-01','participation_end'=>'2026-12-01','is_active'=>'1','notes'=>'private'];
    }
    /** @return array<string,mixed> */
    private function personData(string $first,string $last,string $email): array
    {
        return ['user_id'=>null,'first_name'=>$first,'last_name'=>$last,'institutional_email'=>$email,'affiliation'=>'University','position_type'=>'researcher','is_internal'=>true,'active_from'=>'2025-01-01','active_to'=>null,'is_active'=>true,'notes'=>null];
    }
    /** @return array<string,mixed> */
    private function projectData(string $acronym,int $manager): array
    {
        return ['acronym'=>$acronym,'title'=>'Test project','description'=>null,'internal_code'=>null,'grant_agreement_number'=>null,'funding_agency'=>null,'funding_programme'=>null,'coordinator_organization'=>null,'manager_person_id'=>$manager,'start_date'=>'2026-01-01','end_date'=>'2028-12-31','status'=>'active','total_budget'=>null,'currency'=>null,'website_url'=>null,'notes'=>null];
    }
}
