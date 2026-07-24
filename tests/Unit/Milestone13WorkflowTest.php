<?php
declare(strict_types=1);

namespace Tests\Unit;

use App\Auth\ProjectPolicy;
use App\Models\User;
use App\Models\ProjectManagerOption;
use PHPUnit\Framework\TestCase;
use Tests\Fakes\InMemoryProjectRepository;
use Tests\Support\PersonFactory;
use Tests\Support\UserFactory;

final class Milestone13WorkflowTest extends TestCase
{
    public function testProjectManagerCanViewButCannotEditAnotherManagersProject(): void
    {
        $manager = UserFactory::make(role: User::ROLE_PROJECT_MANAGER);
        $currentPerson = PersonFactory::make(id: 10);
        $repository = new InMemoryProjectRepository([
            new ProjectManagerOption(20, 'Other Manager', 'Researcher', null, true, null),
        ]);
        $project = $repository->create([
            'acronym'=>'OTHER','title'=>'Other project','description'=>null,'internal_code'=>null,
            'grant_agreement_number'=>null,'funding_agency'=>null,'funding_programme'=>null,
            'coordinator_organization'=>null,'manager_person_id'=>20,'start_date'=>null,'end_date'=>null,
            'status'=>'active','total_budget'=>null,'currency'=>null,'hours_per_pm'=>'125.00',
            'website_url'=>null,'notes'=>null,
        ]);
        $policy = new ProjectPolicy();

        self::assertTrue($policy->canView($manager, $currentPerson, $project));
        self::assertFalse($policy->canEdit($manager, $currentPerson, $project));
        self::assertFalse($policy->canManageWorkPackages($manager, $currentPerson, $project));
        self::assertFalse($policy->canManageParticipants($manager, $currentPerson, $project));
    }

    public function testCreationFormIsNotCappedAtFiveRows(): void
    {
        $view = (string) file_get_contents(dirname(__DIR__, 2) . '/views/projects/create_workflow.php');
        self::assertStringNotContainsString('while(count($rows)<5)', $view);
        self::assertStringContainsString('Add another Work Package', $view);
        self::assertStringContainsString('data-remove-wp', $view);
    }

    public function testCapacityPresentationIncludesPeriodValues(): void
    {
        $service = (string) file_get_contents(dirname(__DIR__, 2) . '/app/Services/AnnualEffortService.php');
        $view = (string) file_get_contents(dirname(__DIR__, 2) . '/views/annual_effort/show.php');
        foreach (['allocatedHours', 'capacityHours', 'excessHours'] as $field) {
            self::assertStringContainsString($field, $service);
            self::assertStringContainsString($field, $view);
        }
    }
}
