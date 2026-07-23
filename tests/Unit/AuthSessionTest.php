<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Auth\AuthSession;
use App\Auth\SessionManager;
use PHPUnit\Framework\TestCase;

final class AuthSessionTest extends TestCase
{
    protected function setUp(): void { $_SESSION = []; }

    public function testIdleTimeoutInvalidatesAuthentication(): void
    {
        $session = new AuthSession(new SessionManager(), 60, 600);
        $session->login(7, 1000);
        self::assertNull($session->userId(1061));
    }

    public function testAbsoluteTimeoutInvalidatesAuthentication(): void
    {
        $session = new AuthSession(new SessionManager(), 100, 200);
        $session->login(7, 1000);
        self::assertSame(7, $session->userId(1090));
        self::assertSame(7, $session->userId(1180));
        self::assertNull($session->userId(1201));
    }
}
