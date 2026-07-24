<?php
declare(strict_types=1);
namespace App\Services;

use App\Auth\CurrentPerson;
use App\Auth\CurrentUser;
use App\Auth\ProjectPolicy;
use App\Auth\CapacityPolicy;
use App\Repositories\ProjectRepository;

final class NavigationService
{
    public function __construct(
        private readonly CurrentUser $currentUser,
        private readonly CurrentPerson $currentPerson,
        private readonly ProjectRepository $projects,
        private readonly ProjectPolicy $policy,
        private readonly CapacityPolicy $capacityPolicy,
    ) {}

    /** @return array<string,mixed> */
    public function context(string $path):array
    {
        $user=$this->currentUser->get();
        if($user===null)return['navigationProjects'=>[],'currentProjectId'=>null,'canCreateProject'=>false,'canViewPeople'=>false,'navigationPersonId'=>null,'navigationCapacityGlobal'=>false,'currentPath'=>$path];
        $person=$this->currentPerson->get();
        preg_match('#^/projects/(\d+)#',$path,$match);
        return[
            'navigationProjects'=>$this->projects->accessibleFor($user->role,$person?->id),
            'currentProjectId'=>isset($match[1])?(int)$match[1]:null,
            'canCreateProject'=>$this->policy->canCreate($user,$person),
            'canViewPeople'=>$user->isAdmin()||$user->isProjectManager(),
            'navigationPersonId'=>$person?->id,
            'navigationCapacityGlobal'=>$this->capacityPolicy->canViewGlobal($user,$person),
            'currentPath'=>$path,
        ];
    }
}
