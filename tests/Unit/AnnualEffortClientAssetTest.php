<?php
declare(strict_types=1);
namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class AnnualEffortClientAssetTest extends TestCase
{
    public function testAssetsRemainProgressiveAndUseIntegerHundredths():void
    {
        $decimal=file_get_contents(dirname(__DIR__,2).'/public/assets/js/annual-effort-decimal.js');
        $grid=file_get_contents(dirname(__DIR__,2).'/public/assets/js/annual-effort.js');
        self::assertIsString($decimal);self::assertIsString($grid);
        self::assertStringContainsString('cents', $decimal);
        self::assertStringNotContainsString('parseFloat', $decimal.$grid);
        self::assertStringNotContainsString('localStorage', $grid);
        self::assertStringContainsString('beforeunload', $grid);
        self::assertStringContainsString('dataset.initial', $grid);
        self::assertStringContainsString('sessionStorage', $grid);
    }
}
