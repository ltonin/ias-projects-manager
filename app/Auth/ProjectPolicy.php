<?php

declare(strict_types=1);

namespace App\Auth;

use App\Exceptions\AuthorizationException;
use App\Models\Person;
use App\Models\Project;
use App\Models\User;

final class ProjectPolicy
{
    public const MISSING_PERSON_MESSAGE='Your account must be linked to a person record before you can create or manage projects. Contact the administrator.';
    public function canCreate(User $user,?Person $person):bool{return $user->isAdmin()||($user->isProjectManager()&&$person!==null);}
    public function canEdit(User $user,?Person $person,Project $project):bool{return $user->isAdmin()||($user->isProjectManager()&&$project->isOwnedBy($person?->id));}
    public function canChangeStatus(User $user,?Person $person,Project $project):bool{return $this->canEdit($user,$person,$project);}
    public function canViewNotes(User $user,?Person $person,Project $project):bool{return $this->canEdit($user,$person,$project);}
    public function requireCreate(User $user,?Person $person):void
    {
        if($user->isProjectManager()&&$person===null)throw new AuthorizationException(self::MISSING_PERSON_MESSAGE);
        if(!$this->canCreate($user,$person))throw new AuthorizationException('Project creation is not permitted.');
    }
    public function requireEdit(User $user,?Person $person,Project $project):void
    {
        if(!$this->canEdit($user,$person,$project))throw new AuthorizationException('Project editing is not permitted.');
    }
}
