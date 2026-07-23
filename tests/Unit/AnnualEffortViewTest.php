<?php
declare(strict_types=1);
namespace Tests\Unit;

use App\Models\AnnualEffortPage;
use App\Models\PersonHourAllocation;
use App\Models\Project;
use App\Models\ProjectParticipant;
use App\Models\WorkPackage;
use App\Support\Flash;
use App\Support\PersonMonthConverter;
use App\Support\UrlGenerator;
use App\Support\View;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class AnnualEffortViewTest extends TestCase
{
    protected function setUp():void{$_SESSION=[];}
    public function testUnifiedWpFirstReadOnlyGridHasAlignedStructureAndNoModeControls():void
    {
        $html=$this->render(false);
        self::assertSame(2,substr_count($html,'Ada Lovelace'));self::assertSame(2,substr_count($html,'Grace Hopper'));
        self::assertLessThan(strpos($html,'WP2 — Development'),strpos($html,'WP1 — Management'));
        self::assertSame(3,substr_count($html,'<colgroup>'));self::assertSame(3*12,substr_count($html,'class="month-column-width"'));
        self::assertStringContainsString('Unified classified project total',$html);
        self::assertStringNotContainsString('name="allocations[',$html);self::assertStringNotContainsString('concurrency_token',$html);
        self::assertStringNotContainsString('Planned mode',$html);self::assertStringNotContainsString('Actual mode',$html);self::assertStringNotContainsString('name="mode"',$html);
        self::assertStringNotContainsString('data-save-button',$html);
        self::assertStringNotContainsString('Add participant',$html);
    }
    public function testAuthorizedUnifiedInputsPreserveNullAndExplicitZero():void
    {
        $html=$this->render(true);
        self::assertSame(48,substr_count($html,'class="form-control form-control-sm effort-cell"'));
        self::assertStringContainsString('value="0.00"',$html);self::assertStringContainsString('data-initial="0.00"',$html);
        self::assertStringContainsString('name="allocations[1][1][2]" value=""',$html);
        self::assertStringContainsString('Save person-hours',$html);self::assertStringContainsString('data-dirty-count',$html);
    }
    public function testReadOnlyManagerActionsIncludeYearPreservingAddParticipant():void
    {
        $html=$this->render(false,false,false,null,true);
        self::assertStringContainsString('Add participant',$html);
        self::assertStringContainsString('/projects/1/participants/create?year=2027&amp;return=configure',$html);
        self::assertStringContainsString('/projects/1/configure?year=2027',$html);
    }
    public function testDivergentAllocationIsReadOnlyExplainedLinkedAndExcluded():void
    {
        $html=$this->render(true,false,true);
        self::assertSame(47,substr_count($html,'class="form-control form-control-sm effort-cell"'));
        self::assertStringContainsString('Unified totals are incomplete.',$html);self::assertStringContainsString('Different values',$html);
        self::assertStringContainsString('Planned: 20.00 hours. Actual: 15.00 hours.',$html);
        self::assertStringContainsString('/allocations/2',$html);self::assertStringContainsString('1 divergent',$html);
    }
    public function testCurrentMonthUsesServerProvidedStateOnlyForCurrentYear():void
    {
        $current=$this->render(false,false,false,7);self::assertStringContainsString('aria-label="Jul, current month"',$current);self::assertStringContainsString('current-month-label">Current',$current);
        $other=$this->render(false,false,false,null);self::assertStringNotContainsString('current-month-label">Current',$other);
    }
    public function testLegacyUnassignedSummaryDoesNotChoosePlannedOrActualHours():void
    {
        $html=$this->render(false,true);self::assertStringContainsString('Legacy unassigned effort is excluded.',$html);self::assertStringContainsString('Classify unassigned allocations',$html);
        self::assertStringNotContainsString('planned 10.00',$html);self::assertStringNotContainsString('actual 5.00',$html);
    }
    private function render(bool$manage,bool$unassigned=false,bool$divergent=false,?int$currentMonth=null,bool$readOnlyCanManage=false):string
    {
        $n=new DateTimeImmutable('2027-01-01');$project=new Project(1,'TEST','Test',null,null,null,null,null,null,1,new DateTimeImmutable('2027-01-01'),new DateTimeImmutable('2027-12-31'),'active',null,null,null,null,$n,$n,null,null,'125.00');
        $participants=[$this->participant(1,'Ada','Lovelace'),$this->participant(2,'Grace','Hopper')];$wps=[new WorkPackage(1,1,'WP1','Management',null,null,null,1,true,null,$n,$n,'TEST','Test'),new WorkPackage(2,1,'WP2','Development',null,null,null,null,true,null,$n,$n,'TEST','Test')];$sections=[];
        foreach($wps as$wp){$rows=[];foreach($participants as$p){$months=[];for($m=1;$m<=12;$m++){$allocation=$wp->id===1&&$p->id===1&&$m===1?($divergent?$this->allocation(2,'20.00','15.00'):$this->allocation(1,'0.00','0.00')):null;$isDivergent=$allocation!==null&&$allocation->plannedHours!==$allocation->actualHours;$months[$m]=['allocation'=>$allocation,'allowed'=>true,'divergent'=>$isDivergent,'value'=>$isDivergent?null:$allocation?->plannedHours];}$rows[]=['participant'=>$p,'months'=>$months,'annualHours'=>'0.00','divergentCount'=>$divergent&&$wp->id===1&&$p->id===1?1:0];}$sections[]=['workPackage'=>$wp,'participants'=>$rows,'monthlyHours'=>array_fill(1,12,'0.00'),'annualHours'=>'0.00','divergentCount'=>$divergent&&$wp->id===1?1:0,'warnings'=>[]];}
        $page=new AnnualEffortPage($project,2027,$manage,$sections,array_fill(1,12,'0.00'),'0.00',1,1,[],['count'=>$unassigned?1:0,'planned'=>'10.00','actual'=>'5.00'],'token',$divergent?1:0,$currentMonth);
        return(new View(dirname(__DIR__,2).'/views',new UrlGenerator('https://example.test'),new Flash()))->render('annual_effort/show',['title'=>'Effort','page'=>$page,'error'=>null,'submitted'=>[],'csrfToken'=>'csrf','converter'=>new PersonMonthConverter(),'editMode'=>$manage,'canEditHours'=>$manage||$readOnlyCanManage]);
    }
    private function participant(int$id,string$first,string$last):ProjectParticipant{$n=new DateTimeImmutable('2027-01-01');return new ProjectParticipant($id,1,$id,'researcher',null,null,true,null,$n,$n,$first,$last,null,null,'researcher',true,true,null,null,null,null,'TEST','Test','active');}
    private function allocation(int$id,?string$planned,?string$actual):PersonHourAllocation{$n=new DateTimeImmutable('2027-01-01');return new PersonHourAllocation($id,1,2027,1,$planned,$actual,null,$n,$n,1,1,'Ada Lovelace','researcher','TEST','Test','active','125.00',1,'WP1','Management',true);}
}
