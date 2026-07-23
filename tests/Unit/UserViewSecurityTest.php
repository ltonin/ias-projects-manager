<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Auth\Csrf;
use App\Models\User;
use App\Support\Flash;
use App\Support\UrlGenerator;
use App\Support\View;
use PHPUnit\Framework\TestCase;

final class UserViewSecurityTest extends TestCase
{
    protected function setUp(): void { $_SESSION = []; }

    public function testUserFieldsAreEscapedAndPasswordsAreNeverRepopulated(): void
    {
        $secret = 'never-render-this-password';
        $view = new View(dirname(__DIR__, 2) . '/views', new UrlGenerator('https://example.test'), new Flash(), null, new Csrf());
        $html = $view->render('admin/users/form', [
            'title' => 'Create user',
            'mode' => 'create',
            'user' => null,
            'errors' => [],
            'roles' => User::ROLES,
            'csrfToken' => 'test-token',
            'values' => [
                'username' => '<script>.user',
                'first_name' => '<script>alert(1)</script>',
                'last_name' => 'User',
                'email' => 'safe@example.test',
                'role' => User::ROLE_VIEWER,
                'is_active' => '1',
                'password' => $secret,
                'password_hash' => '$2y$hash',
            ],
        ]);
        self::assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $html);
        self::assertStringContainsString('&lt;script&gt;.user', $html);
        self::assertStringNotContainsString('<script>alert(1)</script>', $html);
        self::assertStringNotContainsString($secret, $html);
        self::assertStringNotContainsString('$2y$hash', $html);
    }
}
