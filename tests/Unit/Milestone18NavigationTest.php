<?php
declare(strict_types=1);
namespace Tests\Unit;

use App\Support\UrlGenerator;
use PHPUnit\Framework\TestCase;

final class Milestone18NavigationTest extends TestCase
{
    public function testBothProjectOverviewAllocationEntryPointsCarryTheSameValidatedContext():void
    {
        $view=(string)file_get_contents(dirname(__DIR__,2).'/views/annual_effort/show.php');
        self::assertGreaterThanOrEqual(3,substr_count($view,"'context'=>'project-overview'"));
        self::assertStringContainsString("'year'=>\$page->year",$view);
        self::assertStringNotContainsString('return_url',$view);
    }

    public function testAllocationContextIsWhitelistedAndSurvivesFormsAndActions():void
    {
        $root=dirname(__DIR__,2);
        $controller=(string)file_get_contents($root.'/app/Controllers/PersonHourAllocationController.php');
        self::assertStringContainsString("in_array(\$origin,['project-overview','capacity'],true)",$controller);
        self::assertStringContainsString("'project-overview'=>['/projects/'.\$project->id,'Back to Project Overview']",$controller);
        self::assertStringContainsString("'capacity'=>['/capacity','Back to Capacity']",$controller);
        self::assertStringNotContainsString('HTTP_REFERER',$controller);
        foreach(['show.php','form.php','index.php','remove.php']as$file){
            $view=(string)file_get_contents($root.'/views/person_hour_allocations/'.$file);
            self::assertStringContainsString("navigation['contextQuery']",$view);
        }
        self::assertStringContainsString('Back to All Allocations',$controller);
    }

    public function testBasePathIsPreservedByServerGeneratedContextUrls():void
    {
        $urls=new UrlGenerator('https://example.test','/iaslab-projects');
        self::assertSame(
            'https://example.test/iaslab-projects/projects/42?year=2026',
            $urls->to('/projects/42',['year'=>2026])
        );
    }
}
