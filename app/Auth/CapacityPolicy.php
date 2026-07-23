<?php
declare(strict_types=1);
namespace App\Auth;

use App\Exceptions\AuthorizationException;
use App\Models\Person;
use App\Models\User;

final class CapacityPolicy
{
    public function canViewGlobal(User $user,?Person $person):bool
    {
        return $user->isAdmin()||($user->isProjectManager()&&$person!==null);
    }

    public function requireGlobal(User $user,?Person $person):void
    {
        if(!$this->canViewGlobal($user,$person))throw new AuthorizationException('Global capacity access is not permitted.');
    }

    public function canViewOwn(User $user,?Person $current,Person $target):bool
    {
        return $user->isAdmin()||($current!==null&&$current->id===$target->id);
    }
}
