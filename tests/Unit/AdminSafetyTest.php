<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Auth\AuthSession;
use App\Auth\SessionManager;
use App\Exceptions\AdminSafetyException;
use App\Models\User;
use App\Services\AuthenticationService;
use App\Services\UserService;
use App\Validation\UserValidator;
use App\Validation\PersonValidator;
use Tests\Fakes\InMemoryPersonRepository;
use PHPUnit\Framework\TestCase;
use Tests\Fakes\InMemoryUserRepository;
use Tests\Support\UserFactory;

final class AdminSafetyTest extends TestCase
{
    protected function setUp(): void { $_SESSION = []; }

    public function testSelfDeactivationIsBlocked(): void
    {
        $this->expectException(AdminSafetyException::class);
        $this->service([UserFactory::make()])->setActive(1, false, 1);
    }

    public function testLastAdminDemotionIsBlocked(): void
    {
        $this->expectException(AdminSafetyException::class);
        $this->service([UserFactory::make()])->update(1, $this->input(User::ROLE_VIEWER), 2);
    }

    public function testSelfDemotionIsBlocked(): void
    {
        $this->expectException(AdminSafetyException::class);
        $this->service([UserFactory::make(), UserFactory::make(2, email: 'other@example.test')])->update(1, $this->input(User::ROLE_VIEWER), 1);
    }

    public function testValidActivationAndDeactivation(): void
    {
        $service = $this->service([UserFactory::make(), UserFactory::make(2, User::ROLE_VIEWER, true, 'viewer@example.test')]);
        self::assertFalse($service->setActive(2, false, 1)->isActive);
        self::assertTrue($service->setActive(2, true, 1)->isActive);
    }

    public function testCreatingASecondAdministratorIsBlocked(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->service([UserFactory::make()])->create([
            'username' => 'second.admin',
            'email' => 'second@example.test',
            'first_name' => 'Second',
            'last_name' => 'Admin',
            'role' => User::ROLE_ADMIN,
            'is_active' => '1',
            'password' => 'correct horse battery staple',
            'password_confirmation' => 'correct horse battery staple',
        ]);
    }

    public function testCreatingAProjectManagerIsAllowed(): void
    {
        $created = $this->service([UserFactory::make()])->create([
            'username' => 'project.manager',
            'email' => 'manager@example.test',
            'first_name' => 'Project',
            'last_name' => 'Manager',
            'role' => User::ROLE_PROJECT_MANAGER,
            'is_active' => '1',
            'password' => 'correct horse battery staple',
            'password_confirmation' => 'correct horse battery staple',
        ]);

        self::assertTrue($created->isProjectManager());
    }

    /** @param list<User> $users */
    private function service(array $users): UserService
    {
        $repository = new InMemoryUserRepository($users);
        return new UserService($repository,new UserValidator(12),new AuthenticationService($repository,new AuthSession(new SessionManager(),1800,28800)),new InMemoryPersonRepository(),new PersonValidator());
    }

    /** @return array<string, string> */
    private function input(string $role): array
    {
        return ['username' => 'test.user', 'email' => 'user@example.test', 'first_name' => 'Test', 'last_name' => 'User', 'role' => $role, 'is_active' => '1', 'password' => ''];
    }
}
