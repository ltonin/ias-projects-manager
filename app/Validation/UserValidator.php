<?php

declare(strict_types=1);

namespace App\Validation;

use App\Models\User;

final class UserValidator
{
    public const RESERVED_USERNAMES = [
        'admin', 'administrator', 'root', 'system', 'support',
        'login', 'logout', 'health', 'api', 'assets',
    ];

    public function __construct(
        private readonly int $passwordMinLength,
        private readonly int $passwordMaxLength = 4096,
    ) {
        if ($passwordMinLength < 8 || $passwordMinLength > $passwordMaxLength) {
            throw new \InvalidArgumentException('Invalid password length configuration.');
        }
    }

    public static function normalizeEmail(string $email): string
    {
        return strtolower(trim($email));
    }

    public static function normalizeUsername(string $username): string
    {
        return strtolower(trim($username));
    }

    public static function normalizeLoginIdentifier(string $identifier): string
    {
        $trimmed = trim($identifier);
        return str_contains($trimmed, '@')
            ? self::normalizeEmail($trimmed)
            : self::normalizeUsername($trimmed);
    }

    public static function usernameError(string $username): ?string
    {
        $normalized = self::normalizeUsername($username);
        $length = strlen($normalized);
        if ($length < 3 || $length > 50) {
            return 'Username must be between 3 and 50 characters.';
        }
        if (preg_match('/^[a-z0-9][a-z0-9._-]*[a-z0-9]$/', $normalized) !== 1) {
            return 'Username may contain lowercase letters, numbers, dots, underscores, and hyphens, and must start and end with a letter or number.';
        }
        if (in_array($normalized, self::RESERVED_USERNAMES, true)) {
            return 'That username is reserved.';
        }
        return null;
    }

    /** @param array<string, mixed> $input @return array<string, string> */
    public function validateLogin(array $input): array
    {
        $errors = [];
        $identifier = self::normalizeLoginIdentifier((string) ($input['identifier'] ?? ''));
        $validIdentifier = str_contains($identifier, '@')
            ? filter_var($identifier, FILTER_VALIDATE_EMAIL) !== false && strlen($identifier) <= 254
            : self::usernameError($identifier) === null;
        if (!$validIdentifier) {
            $errors['credentials'] = 'The email or username, password, or account status is invalid.';
        }
        $password = (string) ($input['password'] ?? '');
        if ($password === '') {
            $errors['password'] = 'Enter your password.';
        } elseif (strlen($password) > $this->passwordMaxLength) {
            $errors['password'] = sprintf('Password must not exceed %d characters.', $this->passwordMaxLength);
        }
        return $errors;
    }

    /** @param array<string, mixed> $input @return array<string, string> */
    public function validateUser(array $input, bool $passwordRequired): array
    {
        $errors = [];
        $usernameError = self::usernameError((string) ($input['username'] ?? ''));
        if ($usernameError !== null) {
            $errors['username'] = $usernameError;
        }
        foreach (['first_name' => 'First name', 'last_name' => 'Last name'] as $field => $label) {
            $value = trim((string) ($input[$field] ?? ''));
            if ($value === '') {
                $errors[$field] = $label . ' is required.';
            } elseif (strlen($value) > 100) {
                $errors[$field] = $label . ' must not exceed 100 characters.';
            }
        }
        $email = self::normalizeEmail((string) ($input['email'] ?? ''));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 254) {
            $errors['email'] = 'Enter a valid email address.';
        }
        if (!in_array((string) ($input['role'] ?? ''), User::ROLES, true)) {
            $errors['role'] = 'Select a valid role.';
        }

        $password = (string) ($input['password'] ?? '');
        if ($passwordRequired || $password !== '') {
            if (strlen($password) < $this->passwordMinLength) {
                $errors['password'] = sprintf('Password must be at least %d characters.', $this->passwordMinLength);
            } elseif (strlen($password) > $this->passwordMaxLength) {
                $errors['password'] = sprintf('Password must not exceed %d characters.', $this->passwordMaxLength);
            }
            if (!hash_equals($password, (string) ($input['password_confirmation'] ?? ''))) {
                $errors['password_confirmation'] = 'Password confirmation does not match.';
            }
        }
        return $errors;
    }
}
