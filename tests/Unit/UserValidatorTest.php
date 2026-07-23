<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Validation\UserValidator;
use PHPUnit\Framework\TestCase;

final class UserValidatorTest extends TestCase
{
    public function testRejectsInvalidFieldsAndPassword(): void
    {
        $errors = (new UserValidator(12))->validateUser([
            'username' => '', 'first_name' => '', 'last_name' => '', 'email' => 'bad', 'role' => 'owner',
            'password' => 'short', 'password_confirmation' => 'different',
        ], true);
        self::assertEqualsCanonicalizing(['username', 'first_name', 'last_name', 'email', 'role', 'password', 'password_confirmation'], array_keys($errors));
    }

    public function testAcceptsLongPassphraseWithoutCompositionRules(): void
    {
        $password = 'a long lowercase passphrase';
        self::assertSame([], (new UserValidator(12))->validateUser([
            'username' => 'ada.lovelace', 'first_name' => 'Ada', 'last_name' => 'Lovelace', 'email' => 'ADA@example.test',
            'role' => 'admin', 'password' => $password, 'password_confirmation' => $password,
        ], true));
        self::assertSame('ada@example.test', UserValidator::normalizeEmail(' ADA@example.test '));
    }

    public function testUsernamePolicyAndNormalization(): void
    {
        self::assertSame('luca.tonin', UserValidator::normalizeUsername(' LUCA.TONIN '));
        self::assertNull(UserValidator::usernameError('luca.tonin'));
        foreach ([
            'ab', str_repeat('a', 51), '.leading', 'trailing-', 'has space',
            'has@sign', 'bad!', 'admin',
        ] as $invalid) {
            self::assertNotNull(UserValidator::usernameError($invalid), $invalid);
        }
        self::assertNull(UserValidator::usernameError(str_repeat('a', 3)));
        self::assertNull(UserValidator::usernameError(str_repeat('a', 50)));
        self::assertNull(UserValidator::usernameError('a..b'));
    }

    public function testInvalidLoginIdentifiersUseTheSameGenericError(): void
    {
        $validator = new UserValidator(12);
        $usernameErrors = $validator->validateLogin(['identifier' => 'bad username', 'password' => 'password']);
        $emailErrors = $validator->validateLogin(['identifier' => 'bad@', 'password' => 'password']);
        self::assertSame($usernameErrors['credentials'], $emailErrors['credentials']);
        self::assertSame('The email or username, password, or account status is invalid.', $usernameErrors['credentials']);
    }
}
