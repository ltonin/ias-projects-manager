<?php
declare(strict_types=1);
namespace App\Controllers;

use App\Auth\Authorization;
use App\Auth\Csrf;
use App\Auth\CurrentPerson;
use App\Exceptions\HttpException;
use App\Exceptions\StaleAnnualEffortException;
use App\Http\Request;
use App\Http\Response;
use App\Models\PersonHourAllocation;
use App\Repositories\ProjectRepository;
use App\Services\AnnualEffortService;
use App\Support\Flash;
use App\Support\PersonMonthConverter;
use App\Support\UrlGenerator;
use App\Support\View;

final class AnnualEffortController
{
    public function __construct(private readonly Request$request,private readonly View$view,private readonly Authorization$authorization,private readonly CurrentPerson$currentPerson,private readonly ProjectRepository$projects,private readonly AnnualEffortService$service,private readonly Csrf$csrf,private readonly Flash$flash,private readonly UrlGenerator$urls,private readonly PersonMonthConverter$converter){}
    public function readOnly(array$p):Response
    {
        $u=$this->authorization->user();$person=$this->currentPerson->get();$project=$this->project(['projectId'=>$p['id']??null]);
        $accessible=false;foreach($this->projects->accessibleFor($u->role,$person?->id)as$item)if($item->id===$project->id){$accessible=true;break;}
        if(!$accessible)throw new \App\Exceptions\AuthorizationException('Project access is not permitted.');
        $year=$this->year($this->request->query('year'),$project);
        $page=$this->service->page($project,$year,$u,$person);
        return$this->render($this->readOnlyPage($page),null,200,[],false,$page->canManage);
    }
    public function show(array$p):Response{$u=$this->authorization->user();$person=$this->currentPerson->get();$project=$this->project($p);$year=$this->year($this->request->query('year'),$project);$page=$this->service->page($project,$year,$u,$person);if(!$page->canManage)throw new \App\Exceptions\AuthorizationException('Hour editing is not permitted for this project.');return$this->render($page,null,200,[],true);}
    public function save(array$p):Response
    {
        $u=$this->authorization->user();$person=$this->currentPerson->get();$project=$this->project($p);$this->requireCsrf();$year=$this->year($this->request->post('year'),$project);$token=(string)$this->request->post('concurrency_token','');$payload=$this->payload();
        if($payload===null)return$this->render($this->service->page($project,$year,$u,$person),'Malformed effort grid payload.',422,[]);
        try{$result=$this->service->save($project,$year,$payload,$token,$u,$person);}catch(StaleAnnualEffortException$e){return$this->render($this->service->page($project,$year,$u,$person),$e->getMessage(),409,$payload);}catch(\InvalidArgumentException$e){return$this->render($this->service->page($project,$year,$u,$person),$e->getMessage(),422,$payload);}
        $this->flash->add('success',$result['changed'].' effort allocation change'.($result['changed']===1?'':'s').' saved.');return Response::redirect($this->urls->to('/projects/'.$project->id.'/effort',['year'=>$year]));
    }
    private function render(\App\Models\AnnualEffortPage$page,?string$error=null,int$status=200,array$submitted=[],bool$editMode=true,?bool$canEditHours=null):Response{return new Response($this->view->render('annual_effort/show',['title'=>($editMode?'Edit hours':'Project').' — '.$page->project->acronym,'page'=>$page,'error'=>$error,'submitted'=>$submitted,'csrfToken'=>$editMode?$this->csrf->token():'','converter'=>$this->converter,'editMode'=>$editMode,'canEditHours'=>$canEditHours??$page->canManage]),$status);}
    private function readOnlyPage(\App\Models\AnnualEffortPage$p):\App\Models\AnnualEffortPage
    {
        $hasWorkPackages=count(array_filter($p->sections,static fn(array$s):bool=>$s['workPackage']->id!==0))>0;
        $sections=array_values(array_filter($p->sections,static fn(array$s):bool=>!($hasWorkPackages&&$s['workPackage']->id===0&&$s['annualHours']==='0.00')));
        return new \App\Models\AnnualEffortPage($p->project,$p->year,false,$sections,$p->projectMonthlyHours,$p->projectAnnualHours,$p->workPackagesWithEffort,$p->participantsWithEffort,$p->capacity,$p->unassigned,'',$p->divergentCount,$p->currentMonth);
    }
    private function project(array$p):\App\Models\Project{$id=filter_var($p['projectId']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);if($id===false)throw new HttpException(404,'Project not found.');return$this->projects->findById((int)$id)??throw new HttpException(404,'Project not found.');}
    private function year(mixed$v,\App\Models\Project$p):int{$year=filter_var($v,FILTER_VALIDATE_INT,['options'=>['min_range'=>PersonHourAllocation::MIN_YEAR,'max_range'=>PersonHourAllocation::MAX_YEAR]]);return$year===false?$this->service->defaultYear($p,(int)date('Y')):(int)$year;}
    private function requireCsrf():void{$t=$this->request->post('_csrf');if(!is_string($t)||!$this->csrf->validate($t))throw new HttpException(403,'Invalid CSRF token.');}
    private function payload():?array
    {
        $json=$this->request->post('allocations_json');
        if(is_string($json)){
            if(strlen($json)>2_000_000)return null;
            try{$decoded=json_decode($json,true,512,JSON_THROW_ON_ERROR);}catch(\JsonException){return null;}
            return is_array($decoded)?$decoded:null;
        }
        $fallback=$this->request->post('allocations',[]);
        return is_array($fallback)?$fallback:null;
    }
}
