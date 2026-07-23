<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Auth\AuthSession;
use App\Auth\SessionManager;
use App\Services\AuthenticationService;
use PHPUnit\Framework\TestCase;
use Tests\Fakes\InMemoryUserRepository;
use Tests\Support\UserFactory;

final class AuthenticationServiceTest extends TestCase
{
    protected function setUp(): void { $_SESSION = []; }

    public function testSuccessfulLoginAndLogout(): void
    {
        $repository = new InMemoryUserRepository([UserFactory::make()]);
        $session = new AuthSession(new SessionManager(), 1800, 28800);
        $service = new AuthenticationService($repository, $session);
        self::assertTrue($service->attempt(' USER@EXAMPLE.TEST ', 'correct horse battery staple'));
        self::assertSame(1, $session->userId());
        self::assertTrue($repository->loginRecorded);
        $service->logout();
        self::assertNull($session->userId());
    }

    public function testSuccessfulUsernameLoginIsCaseInsensitive(): void
    {
        $repository = new InMemoryUserRepository([UserFactory::make(username: 'luca.tonin')]);
        $session = new AuthSession(new SessionManager(), 1800, 28800);
        $service = new AuthenticationService($repository, $session);
        self::assertTrue($service->attempt(' LUCA.TONIN ', 'correct horse battery staple'));
        self::assertSame(1, $session->userId());
    }

    public function testInvalidUnknownAndInactiveLoginsFail(): void
    {
        $repository = new InMemoryUserRepository([
            UserFactory::make(),
            UserFactory::make(2, active: false, email: 'inactive@example.test', username: 'inactive.user'),
        ]);
        $service = new AuthenticationService($repository, new AuthSession(new SessionManager(), 1800, 28800));
        self::assertFalse($service->attempt('user@example.test', 'wrong'));
        self::assertFalse($service->attempt('missing@example.test', 'anything'));
        self::assertFalse($service->attempt('inactive@example.test', 'correct horse battery staple'));
        self::assertFalse($service->attempt('inactive.user', 'correct horse battery staple'));
        self::assertFalse($service->attempt('invalid username', 'correct horse battery staple'));
        self::assertSame([], $_SESSION);
    }

    public function testRehashesPasswordAfterSuccessfulLogin(): void
    {
        $oldHash = password_hash('correct horse battery staple', PASSWORD_BCRYPT, ['cost' => 4]);
        $repository = new InMemoryUserRepository([UserFactory::make(hash: $oldHash)]);
        $service = new AuthenticationService($repository, new AuthSession(new SessionManager(), 1800, 28800));
        self::assertTrue($service->attempt('user@example.test', 'correct horse battery staple'));
        self::assertTrue($repository->passwordUpdated);
        self::assertTrue(password_verify('correct horse battery staple', $repository->findById(1)?->passwordHash ?? ''));
    }
}
