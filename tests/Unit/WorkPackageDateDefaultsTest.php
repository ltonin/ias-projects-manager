<?php
declare(strict_types=1);
namespace Tests\Unit;

use App\Models\Project;
use App\Support\WorkPackageDateDefaults;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class WorkPackageDateDefaultsTest extends TestCase
{
    public function testProjectDatesAndOpenBoundariesBecomeCreateDefaults():void
    {
        $defaults=new WorkPackageDateDefaults();
        self::assertSame(['start_date'=>'2027-01-01','end_date'=>'2027-12-31'],$defaults->forProject($this->project('2027-01-01','2027-12-31')));
        self::assertSame(['start_date'=>'','end_date'=>'2027-12-31'],$defaults->forProject($this->project(null,'2027-12-31')));
    }
    private function project(?string$start,?string$end):Project{$n=new DateTimeImmutable('2026-01-01');return new Project(1,'TEST','Test',null,null,null,null,null,null,1,$start===null?null:new DateTimeImmutable($start),$end===null?null:new DateTimeImmutable($end),'active',null,null,null,null,$n,$n);}
}
