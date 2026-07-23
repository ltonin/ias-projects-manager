<?php
declare(strict_types=1);
namespace Tests\Unit;
use App\Validation\PersonCapacityValidator;
use PHPUnit\Framework\TestCase;
final class PersonCapacityValidatorTest extends TestCase
{
    public function testValidZeroAndPositiveCapacities():void{$v=new PersonCapacityValidator();foreach(['0','0.00','0.01','125','125.00','999999.99']as$h)self::assertSame([],$v->validateOverride($this->input(['available_hours'=>$h])));}
    public function testInvalidCapacityPeriodAndNotes():void{$v=new PersonCapacityValidator();foreach(['-1','1.234','1e2','1,000','1000000','bad']as$h)self::assertArrayHasKey('available_hours',$v->validateOverride($this->input(['available_hours'=>$h])));self::assertArrayHasKey('year',$v->validateOverride($this->input(['year'=>'1999'])));self::assertArrayHasKey('month',$v->validateOverride($this->input(['month'=>'13'])));self::assertArrayHasKey('notes',$v->validateOverride($this->input(['notes'=>str_repeat('n',2001)])));}
    private function input(array$o=[]):array{return$o+['year'=>'2027','month'=>'3','available_hours'=>'125.00','notes'=>''];}
}
