<?php
declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Person;
use App\Validation\PersonValidator;
use PHPUnit\Framework\TestCase;

final class Milestone14WorkflowTest extends TestCase
{
    public function testAnnualCapacityDefaultsUseStablePositionIdentifiers(): void
    {
        foreach (['full_professor','associate_professor','assistant_professor','researcher'] as $position) {
            self::assertSame('1150.00', Person::defaultAnnualCapacity($position));
        }
        foreach (['postdoc','phd_student','research_fellow','technician','administrative_staff','external_collaborator','other'] as $position) {
            self::assertSame('1500.00', Person::defaultAnnualCapacity($position));
        }
    }

    public function testAnnualCapacityIsRequiredAndPositive(): void
    {
        $valid=['user_id'=>'','first_name'=>'Ada','last_name'=>'Lovelace','institutional_email'=>'',
            'affiliation'=>'','position_type'=>'researcher','is_internal'=>'1','active_from'=>'',
            'active_to'=>'','is_active'=>'1','default_monthly_capacity_hours'=>'125.00',
            'annual_capacity_hours'=>'1150','notes'=>''];
        self::assertArrayNotHasKey('annual_capacity_hours',(new PersonValidator())->validate($valid));
        foreach (['','0','-1','1.234','invalid'] as$value) {
            self::assertArrayHasKey('annual_capacity_hours',(new PersonValidator())->validate(array_replace($valid,['annual_capacity_hours'=>$value])));
        }
    }

    public function testContextAndConfigurationNavigationAreWhitelistedAndYearAware(): void
    {
        $controller=(string)file_get_contents(dirname(__DIR__,2).'/app/Controllers/PersonHourAllocationController.php');
        $overview=(string)file_get_contents(dirname(__DIR__,2).'/views/annual_effort/show.php');
        $nav=(string)file_get_contents(dirname(__DIR__,2).'/views/projects/_configure_nav.php');
        self::assertStringContainsString("'project'=>", $controller);
        self::assertStringContainsString("'capacity'=>", $controller);
        self::assertStringContainsString("'context'=>'project-overview'", $overview);
        self::assertStringContainsString('Back to Project Overview', $nav);
        foreach (['>Details<','>Work Packages<','>Participants<','aria-current="page"'] as $text) self::assertStringContainsString($text,$nav);
    }

    public function testCapacityToolbarUsesContentSizedCss(): void
    {
        $css=(string)file_get_contents(dirname(__DIR__,2).'/public/assets/css/app.css');
        self::assertStringContainsString('.capacity-overview-tools button{width:max-content;justify-self:start}', $css);
    }
}
