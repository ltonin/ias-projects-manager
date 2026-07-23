<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Auth\Csrf;
use PHPUnit\Framework\TestCase;

final class CsrfTest extends TestCase
{
    protected function setUp(): void
    {
        $_SESSION = [];
    }

    public function testCreatesAndValidatesToken(): void
    {
        $csrf = new Csrf();
        $token = $csrf->token();
        self::assertSame(64, strlen($token));
        self::assertTrue($csrf->validate($token));
        self::assertFalse($csrf->validate('invalid'));
    }
}
