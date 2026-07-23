<?php

declare(strict_types=1);

namespace App\Services;

use App\Auth\ProjectPolicy;
use App\Exceptions\AuthorizationException;
use App\Models\Person;
use App\Models\Project;
use App\Models\ProjectPage;
use App\Models\User;
use App\Repositories\ProjectRepository;
use App\Validation\ProjectValidator;

final class ProjectService
{
    public const DEFAULT_PER_PAGE=25;
    public const MAX_PER_PAGE=100;
    public function __construct(private readonly ProjectRepository $projects,private readonly ProjectValidator $validator,private readonly ProjectPolicy $policy){}

    /** @param array<string,mixed> $input @return array<string,string> */
    public function validate(array $input,?int $exceptId=null):array
    {
        $e=$this->validator->validate($input);
        $checks=['acronym'=>'acronymExists','internal_code'=>'internalCodeExists','grant_agreement_number'=>'grantAgreementNumberExists'];
        foreach($checks as $field=>$method){$v=$this->nullable(trim((string)($input[$field]??'')));if($v!==null&&!isset($e[$field])&&$this->projects->$method($v,$exceptId))$e[$field]='That '.str_replace('_',' ',$field).' is already in use.';}
        $manager=$this->personId($input['manager_person_id']??null);if($manager!==null&&!isset($e['manager_person_id'])&&!$this->projects->personExists($manager))$e['manager_person_id']='The selected responsible person no longer exists.';
        return$e;
    }

    /** @param array<string,mixed> $input */
    public function create(array $input,User $user,?Person $person):Project
    {
        $this->policy->requireCreate($user,$person);
        if($user->isProjectManager()){
            $submitted=$this->personId($input['manager_person_id']??null);
            if($submitted!==null&&$submitted!==$person?->id)throw new AuthorizationException('Project managers cannot assign another responsible person.');
            $input['manager_person_id']=(string)$person->id;
        }
        return$this->projects->create($this->normalize($input));
    }

    /** @param array<string,mixed> $input */
    public function update(Project $project,array $input,User $user,?Person $person):Project
    {
        $this->policy->requireEdit($user,$person,$project);
        $required=null;
        if($user->isProjectManager()){
            $submitted=$this->personId($input['manager_person_id']??null);
            if($submitted!==null&&$submitted!==$project->managerPersonId)throw new AuthorizationException('Project managers cannot reassign project responsibility.');
            $input['manager_person_id']=$project->managerPersonId===null?'':(string)$project->managerPersonId;
            $required=$person?->id;
        }
        return$this->projects->update($project->id,$this->normalize($input),$required);
    }

    public function changeStatus(Project $project,string $status,User $user,?Person $person):Project
    {
        if(!$this->policy->canChangeStatus($user,$person,$project))throw new AuthorizationException('Project status changes are not permitted.');
        if(!array_key_exists($status,Project::STATUS_LABELS))throw new \InvalidArgumentException('Invalid project status.');
        return$this->projects->updateStatus($project->id,$status,$user->isProjectManager()?$person?->id:null);
    }

    /** @param array<string,mixed> $query @return array{page:ProjectPage,filters:array{search:string,status:string,manager_person_id:string,funding_agency:string,funding_programme:string}} */
    public function listing(array $query):array
    {
        $filters=[
            'search'=>mb_substr(trim((string)($query['search']??'')),0,200),
            'status'=>array_key_exists((string)($query['status']??''),Project::STATUS_LABELS)?(string)$query['status']:'',
            'manager_person_id'=>filter_var($query['manager_person_id']??'',FILTER_VALIDATE_INT,['options'=>['min_range'=>1]])?(string)(int)$query['manager_person_id']:'',
            'funding_agency'=>mb_substr(trim((string)($query['funding_agency']??'')),0,255),
            'funding_programme'=>mb_substr(trim((string)($query['funding_programme']??'')),0,255),
        ];
        $page=filter_var($query['page']??1,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]])?:1;
        $per=filter_var($query['per_page']??self::DEFAULT_PER_PAGE,FILTER_VALIDATE_INT,['options'=>['min_range'=>1,'max_range'=>self::MAX_PER_PAGE]])?:self::DEFAULT_PER_PAGE;
        $result=$this->projects->search($filters,(int)$page,(int)$per);
        if($result->total>0&&$page>$result->pageCount())$result=$this->projects->search($filters,$result->pageCount(),(int)$per);
        return['page'=>$result,'filters'=>$filters];
    }

    /** @param array<string,mixed> $i @return array<string,mixed> */
    private function normalize(array $i):array
    {
        $budget=$this->nullable(trim((string)($i['total_budget']??'')));
        if($budget!==null&&!str_contains($budget,'.'))$budget.='.00';elseif($budget!==null&&strlen(substr(strrchr($budget,'.')?:'',1))===1)$budget.='0';
        return[
            'acronym'=>trim((string)($i['acronym']??'')),'title'=>trim((string)($i['title']??'')),
            'description'=>$this->nullable(trim((string)($i['description']??''))),'internal_code'=>$this->nullable(trim((string)($i['internal_code']??''))),
            'grant_agreement_number'=>$this->nullable(trim((string)($i['grant_agreement_number']??''))),'funding_agency'=>$this->nullable(trim((string)($i['funding_agency']??''))),
            'funding_programme'=>$this->nullable(trim((string)($i['funding_programme']??''))),'coordinator_organization'=>$this->nullable(trim((string)($i['coordinator_organization']??''))),
            'manager_person_id'=>$this->personId($i['manager_person_id']??null),'start_date'=>$this->nullable(trim((string)($i['start_date']??''))),
            'end_date'=>$this->nullable(trim((string)($i['end_date']??''))),'status'=>(string)($i['status']??''),
            'total_budget'=>$budget,'currency'=>$budget===null?null:strtoupper(trim((string)($i['currency']??''))),
            'website_url'=>$this->nullable(trim((string)($i['website_url']??''))),'notes'=>$this->nullable(trim((string)($i['notes']??''))),
        ];
    }
    private function nullable(string $v):?string{return$v===''?null:$v;}
    private function personId(mixed $v):?int{$s=trim((string)$v);return$s===''?null:(int)$s;}
}
