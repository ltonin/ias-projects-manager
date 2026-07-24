<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Auth\AuthSession;
use App\Auth\Authorization;
use App\Auth\CurrentUser;
use App\Auth\SessionManager;
use App\Exceptions\AuthorizationException;
use App\Exceptions\AuthenticationRequiredException;
use App\Models\User;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tests\Fakes\InMemoryUserRepository;
use Tests\Support\UserFactory;

final class AuthorizationTest extends TestCase
{
    protected function setUp(): void { $_SESSION = []; }

    public function testAnonymousAuthenticationIsRequired(): void
    {
        $this->expectException(AuthenticationRequiredException::class);
        $this->authorization(null)->user();
    }

    #[DataProvider('nonAdminRoles')]
    public function testNonAdminsCannotUseAdminArea(string $role): void
    {
        $this->expectException(AuthorizationException::class);
        $this->authorization($role)->admin();
    }

    public function testAdminCanUseAdminArea(): void
    {
        self::assertTrue($this->authorization(User::ROLE_ADMIN)->admin()->isAdmin());
    }

    public function testProjectManagerCanViewPeopleButCannotUseAdminArea(): void
    {
        $authorization=$this->authorization(User::ROLE_PROJECT_MANAGER);
        self::assertTrue($authorization->peopleViewer()->isProjectManager());
        $this->expectException(AuthorizationException::class);
        $authorization->admin();
    }

    public static function nonAdminRoles(): array
    {
        return [[User::ROLE_PARTICIPANT], [User::ROLE_VIEWER]];
    }

    private function authorization(?string $role): Authorization
    {
        $repository = new InMemoryUserRepository($role === null ? [] : [UserFactory::make(role: $role)]);
        $session = new AuthSession(new SessionManager(), 1800, 28800);
        if ($role !== null) {
            $session->login(1);
        }
        return new Authorization(new CurrentUser($session, $repository));
    }
}
