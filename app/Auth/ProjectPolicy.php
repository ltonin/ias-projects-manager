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
    public function canView(User $user,?Person $person,Project $project,bool $participates=false):bool
    {
        return $user->isAdmin()
            || ($person!==null&&($project->isOwnedBy($person->id)||$participates))
            || $user->role===User::ROLE_VIEWER;
    }
    public function requireView(User $user,?Person $person,Project $project,bool $participates=false):void
    {
        if(!$this->canView($user,$person,$project,$participates))throw new AuthorizationException('Project access is not permitted.');
    }
    public function canEdit(User $user,?Person $person,Project $project):bool{return $user->isAdmin()||($user->isProjectManager()&&$project->isOwnedBy($person?->id));}
    public function canChangeStatus(User $user,?Person $person,Project $project):bool{return $this->canEdit($user,$person,$project);}
    public function canViewNotes(User $user,?Person $person,Project $project):bool{return $this->canEdit($user,$person,$project);}
    public function canManageParticipants(User $user,?Person $person,Project $project):bool{return $this->canEdit($user,$person,$project);}
    public function canViewParticipantNotes(User $user,?Person $person,Project $project):bool{return $this->canEdit($user,$person,$project);}
    public function canManageAllocations(User $user,?Person $person,Project $project):bool{return $this->canEdit($user,$person,$project);}
    public function canViewAllocationNotes(User $user,?Person $person,Project $project):bool{return $this->canEdit($user,$person,$project);}
    public function canManageWorkPackages(User $user,?Person $person,Project $project):bool{return $this->canEdit($user,$person,$project);}
    public function canViewWorkPackageNotes(User $user,?Person $person,Project $project):bool{return $this->canEdit($user,$person,$project);}
    public function requireCreate(User $user,?Person $person):void
    {
        if($user->isProjectManager()&&$person===null)throw new AuthorizationException(self::MISSING_PERSON_MESSAGE);
        if(!$this->canCreate($user,$person))throw new AuthorizationException('Project creation is not permitted.');
    }
    public function requireEdit(User $user,?Person $person,Project $project):void
    {
        if(!$this->canEdit($user,$person,$project))throw new AuthorizationException('Project editing is not permitted.');
    }
    public function requireManageParticipants(User $user,?Person $person,Project $project):void
    {
        if($user->isProjectManager()&&$person===null)throw new AuthorizationException(self::MISSING_PERSON_MESSAGE);
        if(!$this->canManageParticipants($user,$person,$project))throw new AuthorizationException('Participant management is not permitted for this project.');
    }
    public function requireManageWorkPackages(User $user,?Person $person,Project $project):void
    {
        if($user->isProjectManager()&&$person===null)throw new AuthorizationException(self::MISSING_PERSON_MESSAGE);
        if(!$this->canManageWorkPackages($user,$person,$project))throw new AuthorizationException('Work Package management is not permitted for this project.');
    }
}
