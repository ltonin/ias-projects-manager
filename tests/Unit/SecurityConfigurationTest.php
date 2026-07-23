<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Auth\AuthSession;
use App\Auth\SessionManager;
use App\Validation\UserValidator;
use PHPUnit\Framework\TestCase;

final class SecurityConfigurationTest extends TestCase
{
    public function testRejectsInvalidTimeoutConfiguration(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new AuthSession(new SessionManager(), 1800, 1200);
    }

    public function testRejectsUnsafePasswordMinimum(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new UserValidator(4);
    }
}
