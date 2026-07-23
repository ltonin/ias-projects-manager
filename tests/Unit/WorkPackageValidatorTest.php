<?php
declare(strict_types=1);
namespace Tests\Unit;

use App\Models\Project;
use App\Validation\WorkPackageValidator;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class WorkPackageValidatorTest extends TestCase
{
    public function testValidNullableDatesAndResponsibility():void{self::assertSame([], (new WorkPackageValidator())->validate($this->input(),$this->project()));}
    public function testRequiredAndLengthRules():void
    {
        $v=new WorkPackageValidator();
        $e=$v->validate($this->input(['code'=>'','title'=>'','notes'=>str_repeat('x',2001)]),$this->project());
        self::assertArrayHasKey('code',$e);self::assertArrayHasKey('title',$e);self::assertArrayHasKey('notes',$e);
        self::assertArrayHasKey('code',$v->validate($this->input(['code'=>str_repeat('x',51)]),$this->project()));
        self::assertArrayHasKey('title',$v->validate($this->input(['title'=>str_repeat('x',256)]),$this->project()));
    }
    public function testOrderingAndEveryKnownProjectBoundary():void
    {
        $v=new WorkPackageValidator();$p=$this->project();
        self::assertArrayHasKey('end_date',$v->validate($this->input(['start_date'=>'2026-06-02','end_date'=>'2026-06-01']),$p));
        self::assertArrayHasKey('start_date',$v->validate($this->input(['start_date'=>'2025-12-31']),$p));
        self::assertArrayHasKey('end_date',$v->validate($this->input(['end_date'=>'2027-01-01']),$p));
        self::assertArrayHasKey('end_date',$v->validate($this->input(['end_date'=>'2025-12-31']),$p));
        self::assertArrayHasKey('start_date',$v->validate($this->input(['start_date'=>'2027-01-01']),$p));
    }
    public function testUnknownProjectBoundariesDoNotRestrictDates():void{self::assertSame([], (new WorkPackageValidator())->validate($this->input(['start_date'=>'2020-01-01','end_date'=>'2030-01-01']),$this->project(null,null)));}
    public function testMalformedDatesAndParticipantId():void
    {
        $e=(new WorkPackageValidator())->validate($this->input(['start_date'=>'tomorrow','end_date'=>'2026-02-30','responsible_participant_id'=>'person-1']),$this->project());
        self::assertArrayHasKey('start_date',$e);self::assertArrayHasKey('end_date',$e);self::assertArrayHasKey('responsible_participant_id',$e);
    }
    private function input(array$o=[]):array{return$o+['code'=>'WP1','title'=>'Research','description'=>'','start_date'=>'','end_date'=>'','responsible_participant_id'=>'','is_active'=>'1','notes'=>''];}
    private function project(?string$s='2026-01-01',?string$e='2026-12-31'):Project{$n=new DateTimeImmutable('2026-01-01');return new Project(1,'TEST','Test',null,null,null,null,null,null,1,$s?new DateTimeImmutable($s):null,$e?new DateTimeImmutable($e):null,'active',null,null,null,null,$n,$n);}
}
