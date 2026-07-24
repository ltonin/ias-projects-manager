<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Auth\Authorization;
use App\Auth\Csrf;
use App\Auth\CurrentPerson;
use App\Auth\ProjectPolicy;
use App\Exceptions\DuplicatePersonHourAllocationException;
use App\Exceptions\HttpException;
use App\Http\Request;
use App\Http\Response;
use App\Models\PersonHourAllocation;
use App\Models\Project;
use App\Models\ProjectParticipant;
use App\Repositories\PersonHourAllocationRepository;
use App\Repositories\ProjectParticipantRepository;
use App\Repositories\ProjectRepository;
use App\Repositories\PersonRepository;
use App\Repositories\WorkPackageRepository;
use App\Services\PersonCapacityService;
use App\Support\DecimalHours;
use App\Services\PersonHourAllocationService;
use App\Support\Flash;
use App\Support\PersonMonthConverter;
use App\Support\UrlGenerator;
use App\Support\View;

final class PersonHourAllocationController
{
    public function __construct(
        private readonly Request$request,private readonly View$view,private readonly Authorization$authorization,
        private readonly CurrentPerson$currentPerson,private readonly ProjectPolicy$policy,private readonly ProjectRepository$projects,
        private readonly ProjectParticipantRepository$participants,private readonly PersonHourAllocationRepository$allocations,
        private readonly PersonHourAllocationService$service,private readonly PersonMonthConverter$converter,
        private readonly Csrf$csrf,private readonly Flash$flash,private readonly UrlGenerator$urls
        ,private readonly PersonRepository$people,private readonly PersonCapacityService$capacity,private readonly DecimalHours$decimals,
        private readonly WorkPackageRepository$workPackages
    ){}
    public function index(array$p):Response
    {
        $this->authorization->user();[$project,$participant]=$this->context($p);$listing=$this->service->listing($participant,$this->request->queryData());$navigation=$this->returnContext($project,$participant);
        return new Response($this->view->render('person_hour_allocations/index',[
            'title'=>'Allocations — '.$participant->personName(),'project'=>$project,'participant'=>$participant,
            'page'=>$listing['page'],'filters'=>$listing['filters'],'converter'=>$this->converter,
            'canManage'=>$this->canManage($project),'totals'=>$this->allocations->totalsForParticipant($participant->id),
            'workPackages'=>$this->workPackageOptions($project),
            'navigation'=>$navigation,
        ]));
    }
    public function unassignedIndex(array$p):Response
    {
        $user=$this->authorization->user();$person=$this->currentPerson->get();$project=$this->projects->findById($this->id($p['projectId']??null))??throw new HttpException(404,'Project not found.');
        return new Response($this->view->render('person_hour_allocations/unassigned',[
            'title'=>'Classify unassigned allocations — '.$project->acronym,'project'=>$project,
            'allocations'=>$this->allocations->findLegacyUnassignedByProject($project->id),
            'totals'=>$this->allocations->totalsForUnassignedProject($project->id),
            'canManage'=>$this->policy->canManageAllocations($user,$person,$project),
        ]));
    }
    public function show(array$p):Response
    {
        $user=$this->authorization->user();$person=$this->currentPerson->get();[$project,$participant]=$this->context($p);$allocation=$this->allocation($p,$project,$participant);
        $canNotes=$this->policy->canViewAllocationNotes($user,$person,$project);
        return new Response($this->view->render('person_hour_allocations/show',[
            'title'=>$allocation->monthLabel().' — '.$participant->personName(),'project'=>$project,'participant'=>$participant,
            'allocation'=>$canNotes?$allocation:$allocation->withoutNotes(),'canViewNotes'=>$canNotes,
            'canManage'=>$this->policy->canManageAllocations($user,$person,$project),'converter'=>$this->converter,
            'personMonthTotals'=>$this->allocations->totalsForPersonAndMonth($participant->personId,$allocation->year,$allocation->month),
            'capacitySummary'=>$this->capacitySummary($participant,$allocation->year,$allocation->month),
            'decimals'=>$this->decimals,'navigation'=>$this->returnContext($project,$participant,$allocation->year),
        ]));
    }
    public function createForm(array$p):Response
    {
        [$user,$person,$project,$participant]=$this->management($p);
        if($this->workPackageOptions($project)===[])return$this->unavailable($project,$participant);
        return$this->form('Add monthly allocation','create',$project,$participant,null,[],$this->emptyValues(),200);
    }
    public function create(array$p):Response
    {
        [$user,$person,$project,$participant]=$this->management($p);$this->requireCsrf();$input=$this->request->postData();$errors=$this->service->validate($project,$participant,$input);
        if($errors!==[])return$this->form('Add monthly allocation','create',$project,$participant,null,$errors,$input,422);
        try{$created=$this->service->create($project,$participant,$input,$user,$person);}
        catch(DuplicatePersonHourAllocationException$e){return$this->form('Add monthly allocation','create',$project,$participant,null,['month'=>$e->getMessage()],$input,422);}
        $navigation=$this->returnContext($project,$participant,$created->year);$this->flash->add('success',$created->monthLabel().' allocation was created.');return Response::redirect($this->urls->to($this->base($project,$participant).'/'.$created->id,$navigation['contextQuery']));
    }
    public function editForm(array$p):Response
    {
        [,,$project,$participant]=$this->management($p);$allocation=$this->allocation($p,$project,$participant);
        if($allocation->workPackageId===null)return$this->reclassificationForm($project,$participant,$allocation,[],['work_package_id'=>'']);
        return$this->form('Edit monthly allocation','edit',$project,$participant,$allocation,[],$this->values($allocation),200);
    }
    public function update(array$p):Response
    {
        [$user,$person,$project,$participant]=$this->management($p);$allocation=$this->allocation($p,$project,$participant);$this->requireCsrf();$input=$this->request->postData();$errors=$this->service->validate($project,$participant,$input,$allocation->id);
        if($allocation->workPackageId===null){
            $errors=$this->service->validateReclassification($project,$participant,$allocation,$input);
            if($errors!==[])return$this->reclassificationForm($project,$participant,$allocation,$errors,$input,422);
            try{$updated=$this->service->reclassifyLegacy($project,$participant,$allocation,$input,$user,$person);}
            catch(DuplicatePersonHourAllocationException$e){return$this->reclassificationForm($project,$participant,$allocation,['work_package_id'=>$e->getMessage()],$input,422);}
            $this->flash->add('success',$updated->monthLabel().' allocation was classified as '.$updated->workPackageLabel().'.');
            $navigation=$this->returnContext($project,$participant,$updated->year);return Response::redirect($this->urls->to($this->base($project,$participant).'/'.$updated->id,$navigation['contextQuery']));
        }
        if($errors!==[])return$this->form('Edit monthly allocation','edit',$project,$participant,$allocation,$errors,$input,422);
        try{$updated=$this->service->update($project,$participant,$allocation,$input,$user,$person);}
        catch(DuplicatePersonHourAllocationException$e){return$this->form('Edit monthly allocation','edit',$project,$participant,$allocation,['month'=>$e->getMessage()],$input,422);}
        $navigation=$this->returnContext($project,$participant,$updated->year);$this->flash->add('success',$updated->monthLabel().' allocation was updated.');return Response::redirect($this->urls->to($this->base($project,$participant).'/'.$updated->id,$navigation['contextQuery']));
    }
    public function removeForm(array$p):Response
    {
        [,,$project,$participant]=$this->management($p);$allocation=$this->allocation($p,$project,$participant);
        return new Response($this->view->render('person_hour_allocations/remove',['title'=>'Remove allocation','project'=>$project,'participant'=>$participant,'allocation'=>$allocation->withoutNotes(),'csrfToken'=>$this->csrf->token(),'navigation'=>$this->returnContext($project,$participant,$allocation->year)]));
    }
    public function remove(array$p):Response
    {
        [$user,$person,$project,$participant]=$this->management($p);$allocation=$this->allocation($p,$project,$participant);$this->requireCsrf();$label=$allocation->monthLabel();
        $navigation=$this->returnContext($project,$participant,$allocation->year);
        $this->service->remove($project,$participant,$allocation,$user,$person);$this->flash->add('success',$label.' allocation was removed.');
        return Response::redirect($this->urls->to($this->base($project,$participant),$navigation['contextQuery']));
    }
    private function context(array$p):array
    {
        $project=$this->projects->findById($this->id($p['projectId']??null))??throw new HttpException(404,'Project not found.');
        $participant=$this->participants->findById($this->id($p['participantId']??null));
        if($participant===null||$participant->projectId!==$project->id)throw new HttpException(404,'Participant not found.');
        $person=$this->currentPerson->get();$this->policy->requireView($this->authorization->user(),$person,$project,$person?->id===$participant->personId);
        return[$project,$participant];
    }
    private function allocation(array$p,Project$project,ProjectParticipant$participant):PersonHourAllocation
    {
        $allocation=$this->allocations->findById($this->id($p['allocationId']??null));
        if($allocation===null||$allocation->projectParticipantId!==$participant->id||$allocation->projectId!==$project->id)throw new HttpException(404,'Allocation not found.');
        return$allocation;
    }
    private function management(array$p):array
    {
        $user=$this->authorization->user();$person=$this->currentPerson->get();[$project,$participant]=$this->context($p);$this->policy->requireManageParticipants($user,$person,$project);return[$user,$person,$project,$participant];
    }
    private function canManage(Project$project):bool{return$this->policy->canManageAllocations($this->authorization->user(),$this->currentPerson->get(),$project);}
    private function id(mixed$value):int{$id=filter_var($value,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);if($id===false)throw new HttpException(404,'Record not found.');return(int)$id;}
    private function requireCsrf():void{$token=$this->request->post('_csrf');if(!is_string($token)||!$this->csrf->validate($token))throw new HttpException(403,'Invalid CSRF token.');}
    private function base(Project$project,ProjectParticipant$participant):string{return'/projects/'.$project->id.'/participants/'.$participant->id.'/allocations';}
    private function returnContext(Project$project,ProjectParticipant$participant,?int$fallbackYear=null):array
    {
        $year=filter_var($this->request->query('year',$fallbackYear),FILTER_VALIDATE_INT,['options'=>['min_range'=>PersonHourAllocation::MIN_YEAR,'max_range'=>PersonHourAllocation::MAX_YEAR]]);
        $year=$year===false?null:(int)$year;
        $origin=(string)$this->request->query('context','');
        if($origin==='project')$origin='project-overview'; // Milestone 14 compatibility.
        if(!in_array($origin,['project-overview','capacity'],true))$origin='allocation-list';
        $user=$this->authorization->user();$person=$this->currentPerson->get();
        if($year===null||($origin==='capacity'&&!($user->isAdmin()||($user->isProjectManager()&&$person!==null))))$origin='allocation-list';
        $backQuery=$year===null?[]:['year'=>$year];
        [$backPath,$backLabel]=match($origin){
            'project-overview'=>['/projects/'.$project->id,'Back to Project Overview'],
            'capacity'=>['/capacity','Back to Capacity'],
            default=>[$this->base($project,$participant),'Back to All Allocations'],
        };
        $contextQuery=$origin==='allocation-list'?[]:['context'=>$origin]+$backQuery;
        return compact('origin','year','backPath','backQuery','backLabel','contextQuery');
    }
    private function form(string$title,string$mode,Project$project,ProjectParticipant$participant,?PersonHourAllocation$allocation,array$errors,array$values,int$status):Response
    {
        $year=filter_var($values['year']??date('Y'),FILTER_VALIDATE_INT)?:date('Y');$month=filter_var($values['month']??date('n'),FILTER_VALIDATE_INT)?:date('n');
        return new Response($this->view->render('person_hour_allocations/form',['title'=>$title,'mode'=>$mode,'project'=>$project,'participant'=>$participant,'allocation'=>$allocation,'errors'=>$errors,'values'=>$values,'converter'=>$this->converter,'csrfToken'=>$this->csrf->token(),'capacitySummary'=>$this->capacitySummary($participant,(int)$year,(int)$month,$allocation),'decimals'=>$this->decimals,'workPackages'=>$this->workPackageOptions($project),'navigation'=>$this->returnContext($project,$participant,(int)$year)]),$status);
    }
    private function reclassificationForm(Project$p,ProjectParticipant$participant,PersonHourAllocation$a,array$errors,array$values,int$status=200):Response
    {
        return new Response($this->view->render('person_hour_allocations/reclassify',['title'=>'Classify legacy unassigned allocation','project'=>$p,'participant'=>$participant,'allocation'=>$a,'errors'=>$errors,'values'=>$values,'csrfToken'=>$this->csrf->token(),'workPackages'=>$this->workPackageOptions($p)]),$status);
    }
    private function unavailable(Project$p,ProjectParticipant$participant):Response
    {
        return new Response($this->view->render('person_hour_allocations/unavailable',['title'=>'Work Package required','project'=>$p,'participant'=>$participant]),409);
    }
    private function emptyValues():array{return['work_package_id'=>'','year'=>(string)(int)date('Y'),'month'=>(string)(int)date('n'),'allocated_hours'=>'','notes'=>''];}
    private function values(PersonHourAllocation$a):array{return['work_package_id'=>$a->workPackageId===null?'':(string)$a->workPackageId,'year'=>(string)$a->year,'month'=>(string)$a->month,'allocated_hours'=>$a->allocatedHours()??'','notes'=>$a->notes??''];}
    private function capacitySummary(ProjectParticipant$p,int$year,int$month,?PersonHourAllocation$exclude=null):\App\Models\MonthlyCapacitySummary{$person=$this->people->findById($p->personId)??throw new HttpException(404,'Person not found.');return$this->capacity->month($person,$year,$month,$exclude);}
    private function workPackageOptions(Project$p):array{return$this->workPackages->optionsForProject($p->id);}
}
