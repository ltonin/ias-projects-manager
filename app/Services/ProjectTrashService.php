<?php
declare(strict_types=1);
namespace App\Services;

use App\Auth\ProjectPolicy;
use App\Exceptions\AuthorizationException;
use App\Models\Person;
use App\Models\Project;
use App\Models\User;
use App\Repositories\ProjectTrashRepository;

final class ProjectTrashService
{
    public function __construct(private readonly ProjectTrashRepository$trash,private readonly ProjectPolicy$policy){}
    public function move(Project$p,User$u,?Person$person):void
    {
        $this->policy->requireEdit($u,$person,$p);$this->trash->softDelete($p,$u->id,$u->isProjectManager()?$person?->id:null);
    }
    public function restore(Project$p,User$u):void{$this->admin($u);$this->trash->restore($p,$u->id);}
    public function permanentlyDelete(Project$p,User$u,string$confirmation):array
    {
        $this->admin($u);if(!$p->isDeleted())throw new \DomainException('Only projects in Trash can be permanently deleted.');
        if(!hash_equals($p->acronym,$confirmation))throw new \InvalidArgumentException('Type the exact project acronym to confirm permanent deletion.');
        return$this->trash->permanentlyDelete($p,$u->id);
    }
    private function admin(User$u):void{if(!$u->isAdmin())throw new AuthorizationException('Project Trash is restricted to administrators.');}
}
