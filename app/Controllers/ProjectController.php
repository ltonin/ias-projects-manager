<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Auth\Authorization;
use App\Auth\Csrf;
use App\Auth\CurrentPerson;
use App\Auth\ProjectPolicy;
use App\Exceptions\DuplicateProjectFieldException;
use App\Exceptions\HttpException;
use App\Http\Request;
use App\Http\Response;
use App\Models\Project;
use App\Models\User;
use App\Repositories\ProjectRepository;
use App\Services\ProjectService;
use App\Support\Flash;
use App\Support\UrlGenerator;
use App\Support\View;

final class ProjectController
{
    public function __construct(
        private readonly Request $request,private readonly View $view,private readonly Authorization $authorization,
        private readonly CurrentPerson $currentPerson,private readonly ProjectPolicy $policy,private readonly ProjectRepository $projects,
        private readonly ProjectService $service,private readonly Csrf $csrf,private readonly Flash $flash,private readonly UrlGenerator $urls
    ){}

    public function index():Response
    {
        $user=$this->authorization->user();$person=$this->currentPerson->get();$listing=$this->service->listing($this->request->queryData());
        return new Response($this->view->render('projects/index',[
            'title'=>'Projects','page'=>$listing['page'],'filters'=>$listing['filters'],'statusLabels'=>Project::STATUS_LABELS,
            'managerOptions'=>$this->projects->managerOptions(),'canCreate'=>$this->policy->canCreate($user,$person),
            'missingPerson'=>$user->isProjectManager()&&$person===null,'person'=>$person,'policy'=>$this->policy,'csrfToken'=>$this->csrf->token(),
        ]));
    }
    /** @param array<string,string> $p */
    public function show(array $p):Response
    {
        $user=$this->authorization->user();$person=$this->currentPerson->get();$project=$this->find($p);
        $canNotes=$this->policy->canViewNotes($user,$person,$project);
        return new Response($this->view->render('projects/show',[
            'title'=>$project->acronym,'project'=>$canNotes?$project:$project->withoutNotes(),
            'canViewNotes'=>$canNotes,'canEdit'=>$this->policy->canEdit($user,$person,$project),
            'canStatus'=>$this->policy->canChangeStatus($user,$person,$project),'statusLabels'=>Project::STATUS_LABELS,'csrfToken'=>$this->csrf->token(),
        ]));
    }
    public function createForm():Response
    {
        $user=$this->authorization->user();$person=$this->currentPerson->get();$this->policy->requireCreate($user,$person);
        return$this->form('Create project','create',null,$user,$person,[],$this->emptyValues());
    }
    public function create():Response
    {
        $user=$this->authorization->user();$person=$this->currentPerson->get();$this->policy->requireCreate($user,$person);$this->requireCsrf();
        $input=$this->request->postData();
        if($user->isProjectManager())$input['manager_person_id']=$person?->id;
        $errors=$this->service->validate($input);
        if($errors!==[])return$this->form('Create project','create',null,$user,$person,$errors,$input,422);
        try{$project=$this->service->create($input,$user,$person);}
        catch(DuplicateProjectFieldException $e){return$this->form('Create project','create',null,$user,$person,[$e->field=>$e->getMessage()],$input,422);}
        $this->flash->add('success',$project->displayTitle().' was created.');
        return Response::redirect($this->urls->to('/projects/'.$project->id));
    }
    /** @param array<string,string> $p */
    public function editForm(array $p):Response
    {
        $user=$this->authorization->user();$person=$this->currentPerson->get();$project=$this->find($p);$this->policy->requireEdit($user,$person,$project);
        return$this->form('Edit project','edit',$project,$user,$person,[],$this->values($project));
    }
    /** @param array<string,string> $p */
    public function update(array $p):Response
    {
        $user=$this->authorization->user();$person=$this->currentPerson->get();$project=$this->find($p);$this->policy->requireEdit($user,$person,$project);$this->requireCsrf();
        $input=$this->request->postData();
        if($user->isProjectManager())$input['manager_person_id']=$project->managerPersonId;
        $errors=$this->service->validate($input,$project->id);
        if($errors!==[])return$this->form('Edit project','edit',$project,$user,$person,$errors,$input,422);
        try{$updated=$this->service->update($project,$input,$user,$person);}
        catch(DuplicateProjectFieldException $e){return$this->form('Edit project','edit',$project,$user,$person,[$e->field=>$e->getMessage()],$input,422);}
        $this->flash->add('success',$updated->displayTitle().' was updated.');
        return Response::redirect($this->urls->to('/projects/'.$updated->id));
    }
    /** @param array<string,string> $p */
    public function status(array $p):Response
    {
        $user=$this->authorization->user();$person=$this->currentPerson->get();$project=$this->find($p);$this->requireCsrf();
        $status=(string)$this->request->post('status','');
        if(!array_key_exists($status,Project::STATUS_LABELS))throw new HttpException(422,'Invalid project status.');
        $updated=$this->service->changeStatus($project,$status,$user,$person);
        $this->flash->add('success',$updated->acronym.' status changed to '.$updated->statusLabel().'.');
        return Response::redirect($this->urls->to('/projects/'.$updated->id));
    }
    /** @param array<string,string> $p */
    private function find(array $p):Project{$id=filter_var($p['id']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);if($id===false)throw new HttpException(404,'Project not found.');return$this->projects->findById((int)$id)??throw new HttpException(404,'Project not found.');}
    private function requireCsrf():void{$token=$this->request->post('_csrf');if(!is_string($token)||!$this->csrf->validate($token))throw new HttpException(403,'Invalid CSRF token.');}
    /** @param array<string,string> $errors @param array<string,mixed> $values */
    private function form(string $title,string $mode,?Project $project,User $user,?\App\Models\Person $person,array $errors,array $values,int $status=200):Response
    {
        return new Response($this->view->render('projects/form',[
            'title'=>$title,'mode'=>$mode,'project'=>$project,'user'=>$user,'person'=>$person,'errors'=>$errors,'values'=>$values,
            'statusLabels'=>Project::STATUS_LABELS,'managerOptions'=>$user->isAdmin()?$this->projects->managerOptions():[],
            'csrfToken'=>$this->csrf->token(),
        ]),$status);
    }
    /** @return array<string,string> */
    private function emptyValues():array{return['acronym'=>'','title'=>'','description'=>'','internal_code'=>'','grant_agreement_number'=>'','funding_agency'=>'','funding_programme'=>'','coordinator_organization'=>'','manager_person_id'=>'','start_date'=>'','end_date'=>'','status'=>'planned','total_budget'=>'','currency'=>'','website_url'=>'','notes'=>''];}
    /** @return array<string,string> */
    private function values(Project $p):array{return['acronym'=>$p->acronym,'title'=>$p->title,'description'=>$p->description??'','internal_code'=>$p->internalCode??'','grant_agreement_number'=>$p->grantAgreementNumber??'','funding_agency'=>$p->fundingAgency??'','funding_programme'=>$p->fundingProgramme??'','coordinator_organization'=>$p->coordinatorOrganization??'','manager_person_id'=>$p->managerPersonId===null?'':(string)$p->managerPersonId,'start_date'=>$p->startDate?->format('Y-m-d')??'','end_date'=>$p->endDate?->format('Y-m-d')??'','status'=>$p->status,'total_budget'=>$p->totalBudget??'','currency'=>$p->currency??'','website_url'=>$p->websiteUrl??'','notes'=>$p->notes??''];}
}
