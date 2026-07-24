<?php
declare(strict_types=1);
namespace App\Controllers;

use App\Auth\Authorization;
use App\Auth\Csrf;
use App\Auth\CurrentPerson;
use App\Auth\ProjectPolicy;
use App\Exceptions\HttpException;
use App\Http\Request;
use App\Http\Response;
use App\Models\Project;
use App\Repositories\ProjectRepository;
use App\Repositories\ProjectTrashRepository;
use App\Services\ProjectTrashService;
use App\Support\Flash;
use App\Support\UrlGenerator;
use App\Support\View;

final class ProjectTrashController
{
    public function __construct(private readonly Request$request,private readonly View$view,private readonly Authorization$authorization,
        private readonly CurrentPerson$currentPerson,private readonly ProjectPolicy$policy,private readonly ProjectRepository$projects,
        private readonly ProjectTrashRepository$trash,private readonly ProjectTrashService$service,private readonly Csrf$csrf,
        private readonly Flash$flash,private readonly UrlGenerator$urls){}
    public function moveForm(array$p):Response
    {
        $u=$this->authorization->user();$person=$this->currentPerson->get();$project=$this->active($p);$this->policy->requireEdit($u,$person,$project);
        return new Response($this->view->render('projects/trash_move',['title'=>'Move project to Trash','project'=>$project,'summary'=>$this->operationalSummary($project),'csrfToken'=>$this->csrf->token()]));
    }
    public function move(array$p):Response
    {
        $u=$this->authorization->user();$project=$this->active($p);$this->csrf();$this->service->move($project,$u,$this->currentPerson->get());
        $this->flash->add('success','Project moved to Trash. An administrator can restore it.');return Response::redirect($this->urls->to('/projects'));
    }
    public function index():Response
    {
        $this->authorization->admin();return new Response($this->view->render('projects/trash_index',['title'=>'Deleted projects','projects'=>$this->trash->listDeleted(),'csrfToken'=>$this->csrf->token()]));
    }
    public function show(array$p):Response
    {
        $this->authorization->admin();$project=$this->deleted($p);$summary=$this->trash->summary($project->id)??throw new HttpException(404,'Deleted project not found.');
        return new Response($this->view->render('projects/trash_show',['title'=>$project->acronym.' — Deleted project','project'=>$project,'summary'=>$summary,'csrfToken'=>$this->csrf->token()]));
    }
    public function restore(array$p):Response
    {
        $u=$this->authorization->admin();$project=$this->deleted($p);$this->csrf();
        try{$this->service->restore($project,$u);}catch(\DomainException$e){$this->flash->add('danger',$e->getMessage());return Response::redirect($this->urls->to('/admin/projects/trash/'.$project->id));}
        $this->flash->add('success','Project restored successfully.');$year=$project->startDate?->format('Y')??date('Y');
        return Response::redirect($this->urls->to('/projects/'.$project->id,['year'=>$year]));
    }
    public function deleteForm(array$p):Response
    {
        $this->authorization->admin();$project=$this->deleted($p);return new Response($this->view->render('projects/trash_delete',['title'=>'Delete project permanently','project'=>$project,'summary'=>$this->trash->summary($project->id),'error'=>null,'csrfToken'=>$this->csrf->token()]));
    }
    public function delete(array$p):Response
    {
        $u=$this->authorization->admin();$project=$this->deleted($p);$this->csrf();$confirmation=trim((string)$this->request->post('confirmation',''));
        try{$counts=$this->service->permanentlyDelete($project,$u,$confirmation);}
        catch(\InvalidArgumentException$e){return new Response($this->view->render('projects/trash_delete',['title'=>'Delete project permanently','project'=>$project,'summary'=>$this->trash->summary($project->id),'error'=>$e->getMessage(),'csrfToken'=>$this->csrf->token()]),422);}
        $this->flash->add('success','Project deleted permanently with '.array_sum($counts).' dependent records.');return Response::redirect($this->urls->to('/admin/projects/trash'));
    }
    private function active(array$p):Project{return$this->projects->findById($this->id($p))??throw new HttpException(404,'Project not found.');}
    private function deleted(array$p):Project{$project=$this->projects->findIncludingDeleted($this->id($p));if($project===null||!$project->isDeleted())throw new HttpException(404,'Deleted project not found.');return$project;}
    private function id(array$p):int{$id=filter_var($p['id']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);if($id===false)throw new HttpException(404,'Project not found.');return(int)$id;}
    private function csrf():void{$token=$this->request->post('_csrf');if(!is_string($token)||!$this->csrf->validate($token))throw new HttpException(403,'Invalid CSRF token.');}
    private function operationalSummary(Project$p):array{return$this->trash->summary($p->id)??['work_package_count'=>0,'participant_count'=>0,'allocation_count'=>0];}
}
