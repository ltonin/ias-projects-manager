<?php

declare(strict_types=1);

namespace App\Services;

use App\Auth\ProjectPolicy;
use App\Exceptions\DuplicatePersonHourAllocationException;
use App\Models\Person;
use App\Models\PersonHourAllocation;
use App\Models\PersonHourAllocationPage;
use App\Models\Project;
use App\Models\ProjectParticipant;
use App\Models\User;
use App\Repositories\PersonHourAllocationRepository;
use App\Repositories\ProjectParticipantRepository;
use App\Repositories\ProjectRepository;
use App\Repositories\WorkPackageRepository;
use App\Validation\PersonHourAllocationValidator;

final class PersonHourAllocationService
{
    public const DEFAULT_PER_PAGE=25;
    public const MAX_PER_PAGE=100;
    public function __construct(
        private readonly PersonHourAllocationRepository$allocations,private readonly ProjectRepository$projects,
        private readonly ProjectParticipantRepository$participants,private readonly PersonHourAllocationValidator$validator,
        private readonly ProjectPolicy$policy,private readonly ?WorkPackageRepository$workPackages=null
    ){}
    /** @param array<string,mixed>$input @return array<string,string> */
    public function validate(Project$project,ProjectParticipant$participant,array$input,?int$exceptId=null):array
    {
        $raw=$input['work_package_id']??null;$wpId=$this->id($raw);$wp=$wpId===null?null:$this->workPackages?->findById($wpId);
        $errors=$this->validator->validate($input,$project,$participant,$wp);
        if(array_key_exists('work_package_key',$input))$errors['work_package_id']='Work Package keys are managed by the application.';
        elseif(!is_scalar($raw)||preg_match('/^[1-9]\d*$/',(string)$raw)!==1)$errors['work_package_id']='Select a Work Package.';
        elseif($wp===null)$errors['work_package_id']='The selected Work Package no longer exists.';
        elseif($wp!==null&&$wp->projectId!==$project->id)$errors['work_package_id']='The selected Work Package does not belong to this project.';
        $year=filter_var($input['year']??null,FILTER_VALIDATE_INT);$month=filter_var($input['month']??null,FILTER_VALIDATE_INT);
        if($year!==false&&$month!==false&&!isset($errors['year'])&&!isset($errors['month'])&&!isset($errors['work_package_id'])&&$this->allocations->participantWorkPackagePeriodExists($participant->id,$wpId,(int)$year,(int)$month,$exceptId))$errors['month']='An allocation already exists for this participant, Work Package, and month.';
        return$errors;
    }
    /** @param array<string,mixed>$input */
    public function create(Project$project,ProjectParticipant$participant,array$input,User$user,?Person$person):PersonHourAllocation
    {
        $this->requireCurrent($project,$participant,$user,$person);
        if(($errors=$this->validate($project,$participant,$input))!==[])throw new \InvalidArgumentException(reset($errors));
        return$this->allocations->create(['project_participant_id'=>$participant->id]+$this->normalize($input),$user->isProjectManager()?$person?->id:null);
    }
    /** @param array<string,mixed>$input */
    public function update(Project$project,ProjectParticipant$participant,PersonHourAllocation$allocation,array$input,User$user,?Person$person):PersonHourAllocation
    {
        $this->assertHierarchy($project,$participant,$allocation);$this->requireCurrent($project,$participant,$user,$person);
        if($allocation->workPackageId===null)throw new \InvalidArgumentException('Legacy unassigned effort must use the reclassification workflow.');
        if(($errors=$this->validate($project,$participant,$input,$allocation->id))!==[])throw new \InvalidArgumentException(reset($errors));
        return$this->allocations->update($allocation->id,$participant->id,['project_participant_id'=>$participant->id]+$this->normalize($input),$user->isProjectManager()?$person?->id:null);
    }
    /** @param array<string,mixed> $input @return array<string,string> */
    public function validateReclassification(Project$project,ProjectParticipant$participant,PersonHourAllocation$allocation,array$input):array
    {
        $this->assertHierarchy($project,$participant,$allocation);$raw=$input['work_package_id']??null;$id=$this->id($raw);$errors=[];
        if($allocation->workPackageId!==null)$errors['work_package_id']='This allocation is already classified.';
        elseif(array_key_exists('work_package_key',$input))$errors['work_package_id']='Work Package keys are managed by the application.';
        elseif(!is_scalar($raw)||preg_match('/^[1-9]\d*$/',(string)$raw)!==1)$errors['work_package_id']='Select a Work Package.';
        else{$wp=$this->workPackages?->findById($id??0);if($wp===null)$errors['work_package_id']='The selected Work Package no longer exists.';elseif($wp->projectId!==$project->id)$errors['work_package_id']='The selected Work Package does not belong to this project.';elseif($this->allocations->participantWorkPackagePeriodExists($participant->id,$id,$allocation->year,$allocation->month,$allocation->id))$errors['work_package_id']=$participant->personName().' already has an allocation for '.$wp->code.' in '.$allocation->monthLabel().'. Review the two allocation records before reclassifying this entry.';}
        return$errors;
    }
    /** @param array<string,mixed> $input */
    public function reclassifyLegacy(Project$project,ProjectParticipant$participant,PersonHourAllocation$allocation,array$input,User$user,?Person$person):PersonHourAllocation
    {
        $this->assertHierarchy($project,$participant,$allocation);$this->requireCurrent($project,$participant,$user,$person);
        if(($errors=$this->validateReclassification($project,$participant,$allocation,$input))!==[])throw new \InvalidArgumentException(reset($errors));
        return$this->allocations->reclassifyLegacy($allocation->id,$participant->id,$this->id($input['work_package_id'])??throw new \InvalidArgumentException('Select a Work Package.'),$user->isProjectManager()?$person?->id:null);
    }
    public function remove(Project$project,ProjectParticipant$participant,PersonHourAllocation$allocation,User$user,?Person$person):void
    {
        $this->assertHierarchy($project,$participant,$allocation);$this->requireCurrent($project,$participant,$user,$person);
        $this->allocations->delete($allocation->id,$participant->id,$user->isProjectManager()?$person?->id:null);
    }
    /** @param array<string,mixed>$query @return array{page:PersonHourAllocationPage,filters:array<string,string>} */
    public function listing(ProjectParticipant$participant,array$query):array
    {
        $year=filter_var($query['year']??'',FILTER_VALIDATE_INT,['options'=>['min_range'=>PersonHourAllocation::MIN_YEAR,'max_range'=>PersonHourAllocation::MAX_YEAR]]);
        $filters=['year'=>$year===false?'':(string)$year];
        $filters['work_package_id']=$this->id($query['work_package_id']??null)!==null?(string)$this->id($query['work_package_id']):'';
        $filters['assignment']=in_array((string)($query['assignment']??'all'),['all','assigned','unassigned'],true)?(string)($query['assignment']??'all'):'all';
        foreach(['planned','actual']as$key)$filters[$key]=in_array((string)($query[$key]??'all'),['all','present','absent'],true)?(string)($query[$key]??'all'):'all';
        $filters['variance']=in_array((string)($query['variance']??'all'),['all','same','different'],true)?(string)($query['variance']??'all'):'all';
        $page=filter_var($query['page']??1,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]])?:1;$per=filter_var($query['per_page']??self::DEFAULT_PER_PAGE,FILTER_VALIDATE_INT,['options'=>['min_range'=>1,'max_range'=>self::MAX_PER_PAGE]])?:self::DEFAULT_PER_PAGE;
        $result=$this->allocations->listForParticipant($participant->id,$filters,(int)$page,(int)$per);
        if($result->total>0&&$page>$result->pageCount())$result=$this->allocations->listForParticipant($participant->id,$filters,$result->pageCount(),(int)$per);
        return['page'=>$result,'filters'=>$filters];
    }
    private function requireCurrent(Project$project,ProjectParticipant$participant,User$user,?Person$person):void
    {
        $currentProject=$this->projects->findById($project->id);$currentParticipant=$this->participants->findById($participant->id);
        if($currentProject===null||$currentParticipant===null||$currentParticipant->projectId!==$currentProject->id)throw new \OutOfBoundsException('Allocation hierarchy no longer exists.');
        $this->policy->requireManageParticipants($user,$person,$currentProject);
    }
    private function assertHierarchy(Project$project,ProjectParticipant$participant,PersonHourAllocation$allocation):void
    {
        if($participant->projectId!==$project->id||$allocation->projectParticipantId!==$participant->id||$allocation->projectId!==$project->id)throw new \OutOfBoundsException('Allocation not found.');
    }
    /** @param array<string,mixed>$input @return array<string,mixed> */
    private function normalize(array$input):array
    {
        $decimal=static function(mixed$value):?string{$value=trim((string)$value);if($value==='')return null;if(!str_contains($value,'.'))return$value.'.00';return strlen(substr(strrchr($value,'.')?:'',1))===1?$value.'0':$value;};
        $notes=trim((string)($input['notes']??''));
        if(array_key_exists('allocated_hours',$input)){
            $hours=$decimal($input['allocated_hours']);
            $planned=$actual=$hours;
        }else{
            $planned=$decimal($input['planned_hours']??null);
            $actual=$decimal($input['actual_hours']??null);
        }
        return['work_package_id'=>$this->id($input['work_package_id']??null),'year'=>(int)$input['year'],'month'=>(int)$input['month'],'planned_hours'=>$planned,'actual_hours'=>$actual,'notes'=>$notes===''?null:$notes];
    }
    private function id(mixed$v):?int{$v=trim((string)$v);if($v==='')return null;$id=filter_var($v,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);return$id===false?null:(int)$id;}
}
