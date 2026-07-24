<?php
declare(strict_types=1);
namespace Tests\Unit;
use PHPUnit\Framework\TestCase;

final class ProjectConfigurationStructureTest extends TestCase
{
    public function testCanonicalConfigurationRouteAndThreeSectionsExist():void
    {
        $root=dirname(__DIR__,2);
        $routes=(string)file_get_contents($root.'/bootstrap/app.php');
        $nav=(string)file_get_contents($root.'/views/projects/_configure_nav.php');
        self::assertStringContainsString("'/projects/{id}/configure'",$routes);
        self::assertStringContainsString('>Details<',$nav);
        self::assertStringContainsString('Work Packages',$nav);
        self::assertStringContainsString('Participants',$nav);
        self::assertStringContainsString('aria-current="page"',$nav);
    }

    public function testConfigurationRegistriesExposeRequiredActionsAndEmptyStates():void
    {
        $root=dirname(__DIR__,2);
        $workPackages=(string)file_get_contents($root.'/views/work_packages/index.php');
        $participants=(string)file_get_contents($root.'/views/project_participants/index.php');
        self::assertStringContainsString('Add Work Package',$workPackages);
        self::assertStringContainsString('No Work Packages have been configured for this project.',$workPackages);
        self::assertStringContainsString("'/edit'",$workPackages);
        self::assertStringContainsString('Add participant',$participants);
        self::assertStringContainsString("'/edit'",$participants);
    }

    public function testConfigurationFormsPreserveYearContext():void
    {
        $root=dirname(__DIR__,2);
        self::assertStringContainsString('return_configuration',(string)file_get_contents($root.'/views/projects/form.php'));
        self::assertStringContainsString('return_year',(string)file_get_contents($root.'/views/work_packages/form.php'));
        self::assertStringContainsString('return_context',(string)file_get_contents($root.'/views/project_participants/form.php'));
    }
}
