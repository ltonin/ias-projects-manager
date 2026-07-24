<?php

declare(strict_types=1);

namespace App\Auth;

use App\Exceptions\AuthorizationException;
use App\Exceptions\AuthenticationRequiredException;
use App\Models\User;

final class Authorization
{
    public function __construct(private readonly CurrentUser $currentUser)
    {
    }

    public function user(): User
    {
        return $this->currentUser->get() ?? throw new AuthenticationRequiredException('Authentication required.');
    }

    public function admin(): User
    {
        $user = $this->user();
        if (!$user->isAdmin()) {
            throw new AuthorizationException('Administrator access is required.');
        }
        return $user;
    }

    public function canViewPeople(User $user): bool
    {
        return $user->isAdmin() || $user->isProjectManager();
    }

    public function peopleViewer(): User
    {
        $user = $this->user();
        if (!$this->canViewPeople($user)) {
            throw new AuthorizationException('People access is not permitted.');
        }
        return $user;
    }
}
