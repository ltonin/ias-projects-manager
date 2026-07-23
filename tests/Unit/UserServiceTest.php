<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Auth\AuthSession;
use App\Auth\SessionManager;
use App\Models\User;
use App\Services\AuthenticationService;
use App\Services\UserService;
use App\Validation\UserValidator;
use PHPUnit\Framework\TestCase;
use Tests\Fakes\InMemoryUserRepository;
use Tests\Support\UserFactory;

final class UserServiceTest extends TestCase
{
    protected function setUp(): void { $_SESSION = []; }

    public function testDuplicateEmailProducesValidationError(): void
    {
        $service = $this->service(new InMemoryUserRepository([UserFactory::make()]));
        $errors = $service->validate($this->input('USER@example.test'), true);
        self::assertSame('That email address is already in use.', $errors['email']);
    }

    public function testExistingUserMayKeepOwnEmail(): void
    {
        $service = $this->service(new InMemoryUserRepository([UserFactory::make()]));
        self::assertSame([], $service->validate($this->input('USER@example.test'), true, 1));
    }

    public function testDuplicateUsernameIsCaseInsensitiveAndOwnUsernameMayBeKept(): void
    {
        $repository = new InMemoryUserRepository([UserFactory::make(username: 'existing.user')]);
        $service = $this->service($repository);
        $input = $this->input('new@example.test');
        $input['username'] = 'EXISTING.USER';
        self::assertSame('That username is already in use.', $service->validate($input, true)['username']);
        self::assertArrayNotHasKey('username', $service->validate($input, true, 1));
    }

    public function testNonexistentUserUpdateFailsSafely(): void
    {
        $this->expectException(\OutOfBoundsException::class);
        $this->service(new InMemoryUserRepository())->update(999, $this->input('new@example.test'), 1);
    }

    private function service(InMemoryUserRepository $repository): UserService
    {
        return new UserService($repository, new UserValidator(12), new AuthenticationService($repository, new AuthSession(new SessionManager(), 1800, 28800)));
    }

    /** @return array<string, string> */
    private function input(string $email): array
    {
        return [
            'username' => 'new.user', 'email' => $email, 'first_name' => 'Test', 'last_name' => 'User',
            'role' => User::ROLE_VIEWER, 'is_active' => '1',
            'password' => 'a sufficiently long passphrase', 'password_confirmation' => 'a sufficiently long passphrase',
        ];
    }
}
