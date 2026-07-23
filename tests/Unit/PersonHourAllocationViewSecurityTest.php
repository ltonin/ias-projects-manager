<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\HourTotals;
use App\Models\PersonHourAllocation;
use App\Models\PersonHourAllocationPage;
use App\Models\Project;
use App\Models\ProjectParticipant;
use App\Support\Flash;
use App\Support\PersonMonthConverter;
use App\Support\UrlGenerator;
use App\Support\View;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class PersonHourAllocationViewSecurityTest extends TestCase
{
    protected function setUp():void{$_SESSION=[];}
    public function testNotesOmittedAndPersonHtmlEscapedInUnauthorizedDetailAndList():void
    {
        $secret='never-render-allocation-note';$allocation=$this->allocation($secret)->withoutNotes();$view=new View(dirname(__DIR__,2).'/views',new UrlGenerator('https://example.test'),new Flash());
        $common=['project'=>$this->project(),'participant'=>$this->participant(),'converter'=>new PersonMonthConverter()];
        $detail=$view->render('person_hour_allocations/show',$common+['title'=>'Allocation','allocation'=>$allocation,'canViewNotes'=>false,'canManage'=>false,'csrfToken'=>'token','personMonthTotals'=>new HourTotals('1.00','2.00',1)]);
        $list=$view->render('person_hour_allocations/index',$common+['title'=>'Allocations','page'=>new PersonHourAllocationPage([$allocation],1,1,25),'filters'=>['year'=>'','planned'=>'all','actual'=>'all','variance'=>'all'],'canManage'=>false,'totals'=>new HourTotals('1.00','2.00',1)]);
        self::assertStringNotContainsString($secret,$detail.$list);self::assertStringNotContainsString('Internal notes',$detail);self::assertStringContainsString('&lt;script&gt;Ada&lt;/script&gt;',$detail);self::assertStringNotContainsString('<script>Ada</script>',$detail.$list);
    }
    public function testLegacyListShowsOnlyNotesIndicatorAndNoWritableMetadata():void
    {
        $secret='legacy-private-note';$allocation=$this->allocation($secret);$view=new View(dirname(__DIR__,2).'/views',new UrlGenerator('https://example.test'),new Flash());
        $html=$view->render('person_hour_allocations/unassigned',['title'=>'Legacy','project'=>$this->project(),'allocations'=>[$allocation],'totals'=>new HourTotals('62.50','75.00',1),'canManage'=>false]);
        self::assertStringContainsString('Present',$html);self::assertStringNotContainsString($secret,$html);self::assertStringNotContainsString('_csrf',$html);self::assertStringNotContainsString('/edit',$html);
    }
    private function allocation(string$notes):PersonHourAllocation{$n=new DateTimeImmutable('2027-01-01');return new PersonHourAllocation(1,1,2027,1,'62.50','75.00',$notes,$n,$n,1,2,'<script>Ada</script>','researcher','TEST','Test','active','125.00');}
    private function project():Project{$n=new DateTimeImmutable('2026-01-01');return new Project(1,'TEST','Test',null,null,null,null,null,null,5,null,null,'active',null,null,null,null,$n,$n);}
    private function participant():ProjectParticipant{$n=new DateTimeImmutable('2026-01-01');return new ProjectParticipant(1,1,2,'researcher',null,null,true,null,$n,$n,'<script>Ada</script>','Lovelace',null,null,'researcher',true,true,null,null,null,null,'TEST','Test','active');}
}
