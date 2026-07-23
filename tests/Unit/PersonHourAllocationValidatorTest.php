<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\PersonHourAllocation;
use App\Models\Project;
use App\Models\ProjectParticipant;
use App\Models\WorkPackage;
use App\Validation\PersonHourAllocationValidator;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class PersonHourAllocationValidatorTest extends TestCase
{
    public function testValidHoursPlannedActualAndExplicitZero():void
    {
        $v=new PersonHourAllocationValidator();
        foreach([['planned_hours'=>'0'],['planned_hours'=>'0.00'],['planned_hours'=>'0.01'],['planned_hours'=>'1'],['planned_hours'=>'7.50'],['planned_hours'=>'125.00'],['planned_hours'=>'150.00'],['actual_hours'=>'75.00'],['planned_hours'=>'62.50','actual_hours'=>'50.00']]as$values)self::assertSame([],$v->validate($this->input($values),$this->project(),$this->participant()));
    }
    public function testMalformedEmptyAndOutOfRangeValues():void
    {
        $v=new PersonHourAllocationValidator();
        foreach(['-1','1.234','1e2','1,00','1,000.00','1000000.00','bad']as$value)self::assertArrayHasKey('planned_hours',$v->validate($this->input(['planned_hours'=>$value]),$this->project(),$this->participant()),$value);
        self::assertArrayHasKey('planned_hours',$v->validate($this->input(['planned_hours'=>'','actual_hours'=>'']),$this->project(),$this->participant()));
        self::assertArrayHasKey('year',$v->validate($this->input(['year'=>'1999']),$this->project(),$this->participant()));
        self::assertArrayHasKey('month',$v->validate($this->input(['month'=>'13']),$this->project(),$this->participant()));
        self::assertArrayHasKey('notes',$v->validate($this->input(['notes'=>str_repeat('n',2001)]),$this->project(),$this->participant()));
    }
    public function testCalendarMonthOverlapIncludingPartialLeapAndYearTransitions():void
    {
        $v=new PersonHourAllocationValidator();
        self::assertSame([],$v->validate($this->input(['year'=>'2028','month'=>'2']),$this->project('2028-02-29','2028-12-31'),$this->participant('2028-02-15','2028-12-31')));
        self::assertSame([],$v->validate($this->input(['year'=>'2027','month'=>'3']),$this->project(),$this->participant('2027-03-15','2027-03-20')));
        self::assertArrayHasKey('month',$v->validate($this->input(['year'=>'2027','month'=>'3']),$this->project(),$this->participant('2027-04-01',null)));
        self::assertArrayHasKey('month',$v->validate($this->input(['year'=>'2025','month'=>'12']),$this->project('2026-01-01',null),$this->participant(null,null)));
        self::assertSame([],$v->validate($this->input(['year'=>'2030','month'=>'1']),$this->project(null,null),$this->participant(null,null)));
    }
    public function testWorkPackageMonthOverlapSupportsPartialAndNullableBoundaries():void
    {
        $v=new PersonHourAllocationValidator();
        self::assertSame([],$v->validate($this->input(['year'=>'2027','month'=>'3']),$this->project(),$this->participant(),$this->workPackage('2027-03-15','2027-05-10')));
        self::assertSame([],$v->validate($this->input(['year'=>'2027','month'=>'5']),$this->project(),$this->participant(),$this->workPackage('2027-03-15','2027-05-10')));
        self::assertArrayHasKey('month',$v->validate($this->input(['year'=>'2027','month'=>'2']),$this->project(),$this->participant(),$this->workPackage('2027-03-01',null)));
        self::assertArrayHasKey('month',$v->validate($this->input(['year'=>'2027','month'=>'6']),$this->project(),$this->participant(),$this->workPackage(null,'2027-05-31')));
        self::assertSame([],$v->validate($this->input(['year'=>'2027','month'=>'6']),$this->project(),$this->participant(),$this->workPackage(null,null)));
    }
    private function input(array$o=[]):array{return$o+['year'=>'2027','month'=>'6','planned_hours'=>'1.00','actual_hours'=>'','notes'=>''];}
    private function project(?string$s='2026-01-01',?string$e='2029-12-31'):Project{$n=new DateTimeImmutable('2026-01-01');return new Project(1,'TEST','Test',null,null,null,null,null,null,5,$s?new DateTimeImmutable($s):null,$e?new DateTimeImmutable($e):null,'active',null,null,null,null,$n,$n);}
    private function participant(?string$s='2026-01-01',?string$e='2029-12-31'):ProjectParticipant{$n=new DateTimeImmutable('2026-01-01');return new ProjectParticipant(1,1,2,'researcher',$s?new DateTimeImmutable($s):null,$e?new DateTimeImmutable($e):null,true,null,$n,$n,'Ada','Lovelace',null,null,'researcher',true,true,$s?new DateTimeImmutable($s):null,$e?new DateTimeImmutable($e):null,null,null,'TEST','Test','active');}
    private function workPackage(?string$s,?string$e):WorkPackage{$n=new DateTimeImmutable('2026-01-01');return new WorkPackage(1,1,'WP1','Work',null,$s?new DateTimeImmutable($s):null,$e?new DateTimeImmutable($e):null,null,true,null,$n,$n,'TEST','Test');}
}
