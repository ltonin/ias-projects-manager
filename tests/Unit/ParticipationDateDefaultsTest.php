<?php
declare(strict_types=1);
namespace Tests\Unit;

use App\Models\Project;
use App\Support\ParticipationDateDefaults;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class ParticipationDateDefaultsTest extends TestCase
{
    public function testProjectAndNarrowerPersonBoundaries():void
    {
        $defaults=new ParticipationDateDefaults();$project=$this->project('2027-01-01','2027-12-31');
        self::assertSame(['participation_start'=>'2027-01-01','participation_end'=>'2027-12-31'],$defaults->forProject($project));
        self::assertSame(['participation_start'=>'2027-03-01','participation_end'=>'2027-10-31'],$defaults->forPerson($project,'2027-03-01','2027-10-31'));
    }
    public function testOpenBoundariesUseApplicableIntersection():void
    {
        $defaults=new ParticipationDateDefaults();
        self::assertSame(['participation_start'=>'2027-02-01','participation_end'=>''],$defaults->forPerson($this->project(null,null),'2027-02-01',null));
        self::assertSame(['participation_start'=>'','participation_end'=>'2027-09-30'],$defaults->forPerson($this->project(null,null),null,'2027-09-30'));
    }
    private function project(?string$start,?string$end):Project{$n=new DateTimeImmutable('2026-01-01');return new Project(1,'TEST','Test',null,null,null,null,null,null,1,$start===null?null:new DateTimeImmutable($start),$end===null?null:new DateTimeImmutable($end),'active',null,null,null,null,$n,$n);}
}
