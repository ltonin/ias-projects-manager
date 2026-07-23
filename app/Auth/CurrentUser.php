<?php

declare(strict_types=1);

namespace App\Auth;

use App\Models\User;
use App\Repositories\UserRepository;

final class CurrentUser
{
    private bool $loaded = false;
    private ?User $user = null;

    public function __construct(
        private readonly AuthSession $session,
        private readonly UserRepository $users,
    ) {
    }

    public function get(): ?User
    {
        if ($this->loaded) {
            return $this->user;
        }
        $this->loaded = true;
        $id = $this->session->userId();
        if ($id === null) {
            return null;
        }
        $user = $this->users->findById($id);
        if ($user === null || !$user->isActive) {
            $this->session->logout();
            return null;
        }
        return $this->user = $user;
    }

    public function clear(): void
    {
        $this->loaded = true;
        $this->user = null;
    }
}
