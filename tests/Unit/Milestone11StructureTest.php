<?php
declare(strict_types=1);
namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class Milestone11StructureTest extends TestCase
{
    public function testWorkflowRoutesAndReadOnlyBoundaryAreExplicit():void
    {
        $bootstrap=file_get_contents(dirname(__DIR__,2).'/bootstrap/app.php');
        self::assertStringContainsString("'/projects/{id}'", $bootstrap);
        self::assertStringContainsString("'/projects/{projectId}/effort/edit'", $bootstrap);
        self::assertStringContainsString('$annualEffortController->readOnly', $bootstrap);
        self::assertStringContainsString('$projectCreationController->submit', $bootstrap);
    }
    public function testSidebarAndGlobalHierarchyUseAccessibleMarkup():void
    {
        $layout=file_get_contents(dirname(__DIR__,2).'/views/layouts/app.php');
        $overview=file_get_contents(dirname(__DIR__,2).'/views/home/index.php');
        self::assertStringContainsString('app-sidebar offcanvas-md offcanvas-start',$layout);
        self::assertStringNotContainsString('<nav class="navbar',$layout);
        self::assertStringContainsString('data-global-overview',$overview);
        self::assertStringContainsString('overview-project',$overview);
        self::assertStringContainsString('overview-wp',$overview);
        self::assertStringContainsString('readonly-effort-table',$overview);
    }
    public function testCollapsedSidebarControlsIconsAndAccessibleLabelsArePresent():void
    {
        $root=dirname(__DIR__,2);
        $layout=(string)file_get_contents($root.'/views/layouts/app.php');
        $script=(string)file_get_contents($root.'/public/assets/js/app.js');
        self::assertStringContainsString('data-sidebar-toggle',$layout);
        self::assertStringContainsString('aria-expanded="true"',$layout);
        self::assertStringContainsString('aria-controls="app-sidebar"',$layout);
        self::assertStringContainsString('aria-label="Collapse sidebar"',$layout);
        self::assertStringContainsString('data-sidebar-reveal',$layout);
        self::assertStringContainsString('$currentProjectId!==null?\'aria-current="page"\'',$layout);
        foreach(['#si-home','#si-projects','#si-people','#si-capacity','#si-admin','#si-list','#si-users','#si-collapse']as$icon)self::assertStringContainsString($icon,$layout);
        foreach(['Overview','Projects','People','Capacity','Administration','Manage projects','Manage people','Users']as$label)self::assertStringContainsString($label,$layout);
        self::assertStringContainsString("'iaspm.sidebar'",$script);
        self::assertStringContainsString("localStorage.setItem(key,collapsed?'collapsed':'expanded')",$script);
        self::assertStringNotContainsString('currentProjectId',$script);
        self::assertStringContainsString("toggle.setAttribute('aria-expanded'",$script);
    }
    public function testWizardHasFourStepsAndNoHourEntry():void
    {
        $view=file_get_contents(dirname(__DIR__,2).'/views/projects/create_workflow.php');
        self::assertStringContainsString("'details'=>'Project details'",$view);
        self::assertStringContainsString("'work-packages'=>'Work Packages'",$view);
        self::assertStringContainsString("'participants'=>'Participants'",$view);
        self::assertStringContainsString("'review'=>'Review'",$view);
        self::assertStringNotContainsString('allocations[',$view);
    }
}
