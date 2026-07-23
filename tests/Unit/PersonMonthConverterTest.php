<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\PersonMonthConverter;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class PersonMonthConverterTest extends TestCase
{
    public function testRequiredConversionsAreExact():void
    {
        $c=new PersonMonthConverter();
        foreach([['62.50','125.00','0.500'],['100.00','125.00','0.800'],['125.00','125.00','1.000'],['150.00','125.00','1.200'],['75.00','150.00','0.500']]as[$h,$f,$expected])self::assertSame($expected,$c->convert($h,$f));
        self::assertNull($c->convert(null,'125.00'));
        self::assertSame('0.01',$c->subtract('100.00','99.99'));
        self::assertSame('-0.001',$c->pmVariance('99.87','100.00','125.00'));
    }
    public function testZeroDivisorIsRejected():void{$this->expectException(InvalidArgumentException::class);(new PersonMonthConverter())->convert('1.00','0.00');}
}
