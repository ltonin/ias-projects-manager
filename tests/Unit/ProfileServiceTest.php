<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Auth\AuthSession;
use App\Auth\SessionManager;
use App\Services\AuthenticationService;
use App\Services\ProfileService;
use PHPUnit\Framework\TestCase;
use Tests\Fakes\InMemoryUserRepository;
use Tests\Support\UserFactory;

final class ProfileServiceTest extends TestCase
{
    public function testItUpdatesOnlyTheCurrentUsersEmail(): void
    {
        $user = UserFactory::make();
        $other = UserFactory::make(2, email: 'other@example.test', username: 'other.user');
        $repository = new InMemoryUserRepository([$user, $other]);
        $service = $this->service($repository);

        $updated = $service->updateEmail($user, ['email' => ' New.Address@Example.Test ']);

        self::assertSame('new.address@example.test', $updated->email);
        self::assertSame('other@example.test', $repository->findById(2)?->email);
        self::assertSame(['email' => 'That email address is already in use.'], $service->validateEmail($updated, ['email' => 'other@example.test']));
    }

    public function testItValidatesAndChangesThePassword(): void
    {
        $user = UserFactory::make();
        $repository = new InMemoryUserRepository([$user]);
        $service = $this->service($repository);

        self::assertArrayHasKey('current_password', $service->validatePassword($user, [
            'current_password' => 'wrong',
            'new_password' => 'a secure replacement',
            'new_password_confirmation' => 'a secure replacement',
        ]));

        $service->changePassword($user, [
            'current_password' => 'correct horse battery staple',
            'new_password' => 'a secure replacement',
            'new_password_confirmation' => 'a secure replacement',
        ]);

        self::assertTrue($repository->passwordUpdated);
        self::assertTrue(password_verify('a secure replacement', $repository->findById($user->id)?->passwordHash ?? ''));
    }

    private function service(InMemoryUserRepository $repository): ProfileService
    {
        $session = new AuthSession(new SessionManager(), 1800, 28800);
        return new ProfileService($repository, new AuthenticationService($repository, $session), 12);
    }
}
