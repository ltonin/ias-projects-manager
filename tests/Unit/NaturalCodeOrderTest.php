<?php
declare(strict_types=1);
namespace Tests\Unit;

use App\Models\WorkPackage;
use App\Support\NaturalCodeOrder;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class NaturalCodeOrderTest extends TestCase
{
    public function testNaturalCodesAndDeterministicFallback():void
    {
        $items=NaturalCodeOrder::sort([$this->wp(5,'WP10'),$this->wp(4,'WP2'),$this->wp(3,'WP1'),$this->wp(2,'wp2'),$this->wp(1,'WP2')]);
        self::assertSame([[3,'WP1'],[1,'WP2'],[4,'WP2'],[2,'wp2'],[5,'WP10']],array_map(static fn($wp)=>[$wp->id,$wp->code],$items));
    }
    private function wp(int$id,string$code):WorkPackage{$now=new DateTimeImmutable('2027-01-01');return new WorkPackage($id,1,$code,'Title',null,null,null,null,true,null,$now,$now,'TEST','Test');}
}
