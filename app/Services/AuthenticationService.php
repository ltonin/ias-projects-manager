<?php

declare(strict_types=1);

namespace App\Services;

use App\Auth\AuthSession;
use App\Repositories\UserRepository;
use App\Validation\UserValidator;

final class AuthenticationService
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly AuthSession $session,
    ) {
    }

    public function attempt(string $identifier, string $password): bool
    {
        $normalized = UserValidator::normalizeLoginIdentifier($identifier);
        $user = $this->users->findByLoginIdentifier($normalized);
        if ($user === null || !$user->isActive || !password_verify($password, $user->passwordHash)) {
            return false;
        }
        if (password_needs_rehash($user->passwordHash, PASSWORD_DEFAULT)) {
            $this->users->updatePasswordHash($user->id, $this->hash($password));
        }
        $this->users->recordLogin($user->id);
        $this->session->login($user->id);
        return true;
    }

    public function logout(): void
    {
        $this->session->logout();
    }

    public function hash(string $password): string
    {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        if (!is_string($hash)) {
            throw new \RuntimeException('Password could not be secured.');
        }
        return $hash;
    }
}
