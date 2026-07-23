<?php
declare(strict_types=1);
namespace Tests\Unit;

use App\Auth\CapacityPolicy;
use App\Exceptions\AuthorizationException;
use App\Models\User;
use App\Services\PersonCapacityService;
use App\Support\DecimalHours;
use App\Validation\PersonCapacityValidator;
use PHPUnit\Framework\TestCase;
use Tests\Fakes\InMemoryPersonCapacityRepository;
use Tests\Fakes\InMemoryPersonRepository;
use Tests\Support\PersonFactory;
use Tests\Support\UserFactory;

final class GlobalCapacityOverviewTest extends TestCase
{
    public function testPolicyAllowsAdminAndLinkedManagerButRejectsOrdinaryUser():void
    {
        $policy=new CapacityPolicy();$person=PersonFactory::make();
        self::assertTrue($policy->canViewGlobal(UserFactory::make(),$person));
        self::assertTrue($policy->canViewGlobal(UserFactory::make(role:User::ROLE_PROJECT_MANAGER),$person));
        self::assertFalse($policy->canViewGlobal(UserFactory::make(role:User::ROLE_VIEWER),$person));
        $this->expectException(AuthorizationException::class);$policy->requireGlobal(UserFactory::make(role:User::ROLE_VIEWER),$person);
    }
    public function testOverviewBatchesAllPeopleAndPreservesZeroOverrideAndYear():void
    {
        $people=new InMemoryPersonRepository();$people->people[1]=PersonFactory::make(id:1,firstName:'Ada');$people->people[2]=PersonFactory::make(id:2,userId:null,firstName:'Grace');
        $capacity=new InMemoryPersonCapacityRepository();$capacity->createOverride(['person_id'=>1,'year'=>2026,'month'=>2,'available_hours'=>'0.00','notes'=>null]);
        $capacity->totals[1][2026][2]=['planned'=>'5.00','actual'=>'4.00'];
        $service=new PersonCapacityService($capacity,$people,new PersonCapacityValidator(),new DecimalHours());
        $page=$service->overview(UserFactory::make(),$people->people[1],2026);
        self::assertSame(2026,$page->year);self::assertCount(2,$page->people);self::assertCount(12,$page->people[0]['months']);
        self::assertSame('0.00',$page->people[0]['months'][1]->capacity->effectiveHours);self::assertSame('override',$page->people[0]['months'][1]->capacity->source);
        self::assertSame('1375.00',$page->people[0]['annualCapacity']);self::assertSame('1500.00',$page->people[1]['annualCapacity']);
        self::assertSame('0.00',$service->overview(UserFactory::make(),$people->people[1],2027)->people[0]['months'][1]->plannedHours);
    }
    public function testNoJavascriptCapacityMarkupKeepsPanelsVisible():void
    {
        $view=(string)file_get_contents(dirname(__DIR__,2).'/views/capacity/overview.php');
        self::assertStringContainsString('type="button" class="capacity-person-toggle"',$view);
        self::assertStringContainsString('aria-expanded="true"',$view);self::assertStringContainsString('aria-controls=',$view);
        self::assertStringNotContainsString('class="capacity-person-panel" hidden',$view);
        self::assertStringContainsString('data-capacity-expand-all',$view);self::assertStringContainsString('data-capacity-collapse-all',$view);
    }
}
