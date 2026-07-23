<?php
declare(strict_types=1);
namespace App\Controllers;

use App\Auth\Authorization;
use App\Auth\Csrf;
use App\Auth\CurrentPerson;
use App\Auth\ProjectPolicy;
use App\Exceptions\DuplicateWorkPackageCodeException;
use App\Exceptions\HttpException;
use App\Http\Request;
use App\Http\Response;
use App\Models\Project;
use App\Models\WorkPackage;
use App\Repositories\ProjectRepository;
use App\Repositories\WorkPackageRepository;
use App\Repositories\PersonHourAllocationRepository;
use App\Exceptions\WorkPackageHasAllocationsException;
use App\Services\WorkPackageService;
use App\Support\Flash;
use App\Support\UrlGenerator;
use App\Support\View;

final class WorkPackageController
{
    public function __construct(private readonly Request$request,private readonly View$view,private readonly Authorization$authorization,private readonly CurrentPerson$currentPerson,private readonly ProjectPolicy$policy,private readonly ProjectRepository$projects,private readonly WorkPackageRepository$workPackages,private readonly WorkPackageService$service,private readonly Csrf$csrf,private readonly Flash$flash,private readonly UrlGenerator$urls,private readonly PersonHourAllocationRepository$allocations){}
    public function index(array$p):Response{$u=$this->authorization->user();$person=$this->currentPerson->get();$project=$this->project($p);$l=$this->service->listing($project,$this->request->queryData());return new Response($this->view->render('work_packages/index',['title'=>'Work Packages — '.$project->acronym,'project'=>$project,'page'=>$l['page'],'filters'=>$l['filters'],'options'=>$this->workPackages->responsibleOptions($project->id),'canManage'=>$this->policy->canManageWorkPackages($u,$person,$project),'csrfToken'=>$this->csrf->token(),'configurationYear'=>$this->year($this->request->query('year'))]));}
    public function show(array$p):Response{$u=$this->authorization->user();$person=$this->currentPerson->get();$project=$this->project($p);$wp=$this->wp($p,$project);$notes=$this->policy->canViewWorkPackageNotes($u,$person,$project);return new Response($this->view->render('work_packages/show',['title'=>$wp->displayTitle(),'project'=>$project,'workPackage'=>$notes?$wp:$wp->withoutNotes(),'canViewNotes'=>$notes,'canManage'=>$this->policy->canManageWorkPackages($u,$person,$project),'csrfToken'=>$this->csrf->token(),'effortTotals'=>$this->allocations->totalsForWorkPackage($wp->id),'unifiedEffortTotals'=>$this->allocations->unifiedTotalsForWorkPackage($wp->id),'divergentEffortCount'=>$this->allocations->divergentCountForWorkPackage($wp->id),'recentAllocations'=>$this->allocations->listForWorkPackage($wp->id),'converter'=>new \App\Support\PersonMonthConverter(),'hasAllocations'=>$this->allocations->hasAllocationsForWorkPackage($wp->id)]));}
    public function createForm(array$p):Response{[$u,$person,$project]=$this->management($p);return$this->form('Add Work Package','create',$project,null,[],$this->empty($project),200,$this->year($this->request->query('year')));}
    public function create(array$p):Response{[$u,$person,$project]=$this->management($p);$this->csrf();$input=$this->request->postData();$year=$this->year($input['return_year']??null);$e=$this->service->validate($project,$input);if($e!==[])return$this->form('Add Work Package','create',$project,null,$e,$input,422,$year);try{$wp=$this->service->create($project,$input,$u,$person);}catch(DuplicateWorkPackageCodeException$e){return$this->form('Add Work Package','create',$project,null,['code'=>$e->getMessage()],$input,422,$year);}$this->flash->add('success',$wp->displayTitle().' was added to '.$project->acronym.'.');return Response::redirect($this->urls->to($this->base($project),$year===null?[]:['year'=>$year]));}
    public function editForm(array$p):Response{[,,$project]=$this->management($p);$wp=$this->wp($p,$project);return$this->form('Edit Work Package','edit',$project,$wp,[],$this->values($wp),200,$this->year($this->request->query('year')));}
    public function update(array$p):Response{[$u,$person,$project]=$this->management($p);$wp=$this->wp($p,$project);$this->csrf();$input=$this->request->postData();$year=$this->year($input['return_year']??null);$e=$this->service->validate($project,$input,$wp->id);if($e!==[])return$this->form('Edit Work Package','edit',$project,$wp,$e,$input,422,$year);try{$updated=$this->service->update($project,$wp,$input,$u,$person);}catch(DuplicateWorkPackageCodeException$e){return$this->form('Edit Work Package','edit',$project,$wp,['code'=>$e->getMessage()],$input,422,$year);}$this->flash->add('success',$updated->displayTitle().' was updated.');return Response::redirect($this->urls->to($this->base($project),$year===null?[]:['year'=>$year]));}
    public function activate(array$p):Response{return$this->active($p,true);}public function deactivate(array$p):Response{return$this->active($p,false);}
    public function removeForm(array$p):Response{[,,$project]=$this->management($p);$wp=$this->wp($p,$project);return new Response($this->view->render('work_packages/remove',['title'=>'Remove Work Package','project'=>$project,'workPackage'=>$wp->withoutNotes(),'csrfToken'=>$this->csrf->token(),'hasAllocations'=>$this->allocations->hasAllocationsForWorkPackage($wp->id)]));}
    public function remove(array$p):Response{[$u,$person,$project]=$this->management($p);$wp=$this->wp($p,$project);$this->csrf();try{$this->service->delete($project,$wp,$u,$person);}catch(WorkPackageHasAllocationsException$e){throw new HttpException(409,$e->getMessage());}$this->flash->add('success',$wp->displayTitle().' was removed.');return Response::redirect($this->urls->to($this->base($project)));}
    private function active(array$p,bool$a):Response{[$u,$person,$project]=$this->management($p);$wp=$this->wp($p,$project);$this->csrf();$wp=$this->service->setActive($project,$wp,$a,$u,$person);$this->flash->add('success',$wp->code.' is now '.($a?'active.':'inactive.'));return Response::redirect($this->urls->to($this->base($project).'/'.$wp->id));}
    private function management(array$p):array{$u=$this->authorization->user();$person=$this->currentPerson->get();$project=$this->project($p);$this->policy->requireManageWorkPackages($u,$person,$project);return[$u,$person,$project];}
    private function project(array$p):Project{$id=$this->id($p['projectId']??null);return$this->projects->findById($id)??throw new HttpException(404,'Project not found.');}
    private function wp(array$p,Project$project):WorkPackage{$wp=$this->workPackages->findById($this->id($p['workPackageId']??null));if($wp===null||$wp->projectId!==$project->id)throw new HttpException(404,'Work Package not found.');return$wp;}
    private function id(mixed$v):int{$id=filter_var($v,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);if($id===false)throw new HttpException(404,'Record not found.');return(int)$id;}
    private function csrf():void{$t=$this->request->post('_csrf');if(!is_string($t)||!$this->csrf->validate($t))throw new HttpException(403,'Invalid CSRF token.');}
    private function base(Project$p):string{return'/projects/'.$p->id.'/work-packages';}
    private function form(string$title,string$mode,Project$p,?WorkPackage$wp,array$e,array$v,int$status=200,?int$year=null):Response{return new Response($this->view->render('work_packages/form',['title'=>$title,'mode'=>$mode,'project'=>$p,'workPackage'=>$wp,'errors'=>$e,'values'=>$v,'options'=>$this->workPackages->responsibleOptions($p->id),'csrfToken'=>$this->csrf->token(),'configurationYear'=>$year]),$status);}
    private function year(mixed$v):?int{$year=filter_var($v,FILTER_VALIDATE_INT,['options'=>['min_range'=>2000,'max_range'=>2100]]);return$year===false?null:(int)$year;}
    private function empty(Project$p):array{return['code'=>'','title'=>'','description'=>'']+(new \App\Support\WorkPackageDateDefaults())->forProject($p)+['responsible_participant_id'=>'','is_active'=>'1','notes'=>''];}
    private function values(WorkPackage$w):array{return['code'=>$w->code,'title'=>$w->title,'description'=>$w->description??'','start_date'=>$w->startDate?->format('Y-m-d')??'','end_date'=>$w->endDate?->format('Y-m-d')??'','responsible_participant_id'=>$w->responsibleParticipantId===null?'':(string)$w->responsibleParticipantId,'is_active'=>$w->isActive?'1':'0','notes'=>$w->notes??''];}
}
