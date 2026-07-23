<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\DuplicateEmailException;
use App\Models\User;
use App\Repositories\UserRepository;
use App\Validation\UserValidator;

final class UserService
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly UserValidator $validator,
        private readonly AuthenticationService $authentication,
    ) {
    }

    /** @param array<string, mixed> $input @return array<string, string> */
    public function validate(array $input, bool $passwordRequired, ?int $exceptId = null): array
    {
        $errors = $this->validator->validateUser($input, $passwordRequired);
        if ((string) ($input['role'] ?? '') === User::ROLE_ADMIN) {
            $existing = $exceptId === null ? null : $this->users->findById($exceptId);
            if ($existing === null || !$existing->isAdmin()) {
                $errors['role'] = 'Administrator cannot be assigned through user management.';
            }
        }
        $email = UserValidator::normalizeEmail((string) ($input['email'] ?? ''));
        $username = UserValidator::normalizeUsername((string) ($input['username'] ?? ''));
        if (!isset($errors['email']) && $this->users->emailExists($email, $exceptId)) {
            $errors['email'] = 'That email address is already in use.';
        }
        if (!isset($errors['username']) && $this->users->usernameExists($username, $exceptId)) {
            $errors['username'] = 'That username is already in use.';
        }
        return $errors;
    }

    /** @param array<string, mixed> $input */
    public function create(array $input): User
    {
        return $this->users->create([
            'username' => UserValidator::normalizeUsername((string) $input['username']),
            'email' => UserValidator::normalizeEmail((string) $input['email']),
            'password_hash' => $this->authentication->hash((string) $input['password']),
            'first_name' => trim((string) $input['first_name']),
            'last_name' => trim((string) $input['last_name']),
            'role' => (string) $input['role'],
            'is_active' => isset($input['is_active']) && (string) $input['is_active'] === '1',
        ]);
    }

    /** @param array<string, mixed> $input */
    public function update(int $id, array $input, int $actingUserId): User
    {
        $data = [
            'username' => UserValidator::normalizeUsername((string) $input['username']),
            'email' => UserValidator::normalizeEmail((string) $input['email']),
            'first_name' => trim((string) $input['first_name']),
            'last_name' => trim((string) $input['last_name']),
            'role' => (string) $input['role'],
            'is_active' => isset($input['is_active']) && (string) $input['is_active'] === '1',
            'acting_user_id' => $actingUserId,
        ];
        if ((string) ($input['password'] ?? '') !== '') {
            $data['password_hash'] = $this->authentication->hash((string) $input['password']);
        }
        return $this->users->update($id, $data);
    }

    public function setActive(int $id, bool $active, int $actingUserId): User
    {
        return $this->users->setActive($id, $active, $actingUserId);
    }
}
