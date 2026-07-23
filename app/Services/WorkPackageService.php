<?php
declare(strict_types=1);
namespace App\Services;

use App\Auth\ProjectPolicy;
use App\Exceptions\DuplicateWorkPackageCodeException;
use App\Models\Person;
use App\Models\Project;
use App\Models\User;
use App\Models\WorkPackage;
use App\Models\WorkPackagePage;
use App\Repositories\ProjectParticipantRepository;
use App\Repositories\ProjectRepository;
use App\Repositories\WorkPackageRepository;
use App\Repositories\PersonHourAllocationRepository;
use App\Exceptions\WorkPackageHasAllocationsException;
use App\Validation\WorkPackageValidator;

final class WorkPackageService
{
    public const DEFAULT_PER_PAGE=25;public const MAX_PER_PAGE=100;
    public function __construct(private readonly WorkPackageRepository$workPackages,private readonly ProjectRepository$projects,private readonly ProjectParticipantRepository$participants,private readonly WorkPackageValidator$validator,private readonly ProjectPolicy$policy,private readonly ?PersonHourAllocationRepository$allocations=null){}
    /** @param array<string,mixed>$input @return array<string,string> */
    public function validate(Project$p,array$input,?int$except=null):array
    {
        $e=$this->validator->validate($input,$p);$code=trim((string)($input['code']??''));
        if($code!==''&&!isset($e['code'])&&$this->workPackages->codeExistsForProject($p->id,$code,$except))$e['code']='That Work Package code is already used in this project.';
        $rp=$this->id($input['responsible_participant_id']??null);
        if(trim((string)($input['responsible_participant_id']??''))!==''&&!isset($e['responsible_participant_id'])){
            $participant=$rp===null?null:$this->participants->findById($rp);
            if($participant===null)$e['responsible_participant_id']='The selected participant no longer exists.';
            elseif($participant->projectId!==$p->id)$e['responsible_participant_id']='Responsible participant must belong to this project.';
        }
        return$e;
    }
    /** @param array<string,mixed>$input */
    public function create(Project$p,array$input,User$u,?Person$person):WorkPackage
    {
        $this->current($p,$u,$person);if(($e=$this->validate($p,$input))!==[])throw new \InvalidArgumentException(reset($e));
        return$this->workPackages->create(['project_id'=>$p->id]+$this->normalize($input),$u->isProjectManager()?$person?->id:null);
    }
    /** @param array<string,mixed>$input */
    public function update(Project$p,WorkPackage$wp,array$input,User$u,?Person$person):WorkPackage
    {
        $this->relationship($p,$wp);$this->current($p,$u,$person);if(($e=$this->validate($p,$input,$wp->id))!==[])throw new \InvalidArgumentException(reset($e));
        return$this->workPackages->update($wp->id,$p->id,['project_id'=>$p->id]+$this->normalize($input),$u->isProjectManager()?$person?->id:null);
    }
    public function setActive(Project$p,WorkPackage$wp,bool$a,User$u,?Person$person):WorkPackage{$this->relationship($p,$wp);$this->current($p,$u,$person);return$this->workPackages->setActive($wp->id,$p->id,$a,$u->isProjectManager()?$person?->id:null);}
    public function delete(Project$p,WorkPackage$wp,User$u,?Person$person):void{$this->relationship($p,$wp);$this->current($p,$u,$person);if($this->allocations?->hasAllocationsForWorkPackage($wp->id))throw new WorkPackageHasAllocationsException('This Work Package has person-hour allocations. Remove or reassign them before deletion; deactivation remains available.');$this->workPackages->delete($wp->id,$p->id,$u->isProjectManager()?$person?->id:null);}
    /** @param array<string,mixed>$q @return array{page:WorkPackagePage,filters:array<string,string>} */
    public function listing(Project$p,array$q):array
    {
        $f=['search'=>mb_substr(trim((string)($q['search']??'')),0,200),'active'=>in_array((string)($q['active']??'all'),['all','active','inactive'],true)?(string)($q['active']??'all'):'all','responsibility'=>in_array((string)($q['responsibility']??'all'),['all','assigned','unassigned'],true)?(string)($q['responsibility']??'all'):'all','responsible_participant_id'=>$this->id($q['responsible_participant_id']??null)!==null?(string)$this->id($q['responsible_participant_id']):'','year'=>preg_match('/^(?:19|20|21)\\d{2}$/',(string)($q['year']??''))?(string)$q['year']:''];
        $page=filter_var($q['page']??1,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]])?:1;$per=filter_var($q['per_page']??self::DEFAULT_PER_PAGE,FILTER_VALIDATE_INT,['options'=>['min_range'=>1,'max_range'=>self::MAX_PER_PAGE]])?:self::DEFAULT_PER_PAGE;
        $result=$this->workPackages->listForProject($p->id,$f,(int)$page,(int)$per);if($result->total>0&&$page>$result->pageCount())$result=$this->workPackages->listForProject($p->id,$f,$result->pageCount(),(int)$per);return['page'=>$result,'filters'=>$f];
    }
    private function current(Project$p,User$u,?Person$person):void{$current=$this->projects->findById($p->id)??throw new \OutOfBoundsException('Project not found.');$this->policy->requireManageWorkPackages($u,$person,$current);}
    private function relationship(Project$p,WorkPackage$wp):void{if($wp->projectId!==$p->id)throw new \OutOfBoundsException('Work Package not found.');}
    /** @param array<string,mixed>$i @return array<string,mixed> */
    private function normalize(array$i):array{$n=static fn($v)=>trim((string)$v)===''?null:trim((string)$v);return['code'=>trim((string)($i['code']??'')),'title'=>trim((string)($i['title']??'')),'description'=>$n($i['description']??null),'start_date'=>$n($i['start_date']??null),'end_date'=>$n($i['end_date']??null),'responsible_participant_id'=>$this->id($i['responsible_participant_id']??null),'is_active'=>isset($i['is_active'])&&(string)$i['is_active']==='1','notes'=>$n($i['notes']??null)];}
    private function id(mixed$v):?int{$v=trim((string)$v);if($v==='')return null;$id=filter_var($v,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);return$id===false?null:(int)$id;}
}
