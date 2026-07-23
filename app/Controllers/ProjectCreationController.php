<?php
declare(strict_types=1);
namespace App\Controllers;

use App\Auth\Authorization;
use App\Auth\Csrf;
use App\Auth\CurrentPerson;
use App\Auth\ProjectPolicy;
use App\Auth\SessionManager;
use App\Exceptions\HttpException;
use App\Http\Request;
use App\Http\Response;
use App\Models\Project;
use App\Models\ProjectParticipant;
use App\Repositories\PersonRepository;
use App\Repositories\ProjectRepository;
use App\Services\ProjectCreationWorkflowService;
use App\Services\ProjectService;
use App\Support\Flash;
use App\Support\UrlGenerator;
use App\Support\View;

final class ProjectCreationController
{
    private const KEY='project_creation_workflow';
    public function __construct(
        private readonly Request$request,private readonly View$view,private readonly Authorization$authorization,
        private readonly CurrentPerson$currentPerson,private readonly ProjectPolicy$policy,private readonly ProjectService$projects,
        private readonly ProjectRepository$projectRepository,private readonly PersonRepository$people,
        private readonly ProjectCreationWorkflowService$workflow,private readonly SessionManager$session,
        private readonly Csrf$csrf,private readonly Flash$flash,private readonly UrlGenerator$urls,
    ){}
    public function show():Response
    {
        [$user,$person]=$this->actor();$step=(string)$this->request->query('step','details');
        if(!in_array($step,['details','work-packages','participants','review'],true))throw new HttpException(404,'Creation step not found.');
        $state=$this->state($user->id);
        if($step!=='details'&&!isset($state['details']))return Response::redirect($this->urls->to('/projects/create'));
        return$this->render($step,$state,[],$user,$person);
    }
    public function submit():Response
    {
        [$user,$person]=$this->actor();$this->requireCsrf();$step=(string)$this->request->post('step','');
        $state=$this->state($user->id);
        if($step==='cancel'){$this->session->remove(self::KEY);return Response::redirect($this->urls->to('/'));}
        if($step==='details'){
            $values=$this->details();if($user->isProjectManager())$values['manager_person_id']=(string)$person?->id;
            $errors=$this->projects->validate($values);if($errors!==[])return$this->render('details',$state,$errors,$user,$person,$values,422);
            $state['details']=$values;$this->save($user->id,$state);return Response::redirect($this->urls->to('/projects/create',['step'=>'work-packages']));
        }
        if(!isset($state['details']))throw new HttpException(409,'The project creation session is incomplete.');
        if($step==='work-packages'){
            [$wps,$errors]=$this->workPackages($state['details']);if($errors!==[])return$this->render('work-packages',$state,$errors,$user,$person,['work_packages'=>$wps],422);
            $state['work_packages']=$wps;$this->save($user->id,$state);return Response::redirect($this->urls->to('/projects/create',['step'=>'participants']));
        }
        if($step==='participants'){
            [$participants,$errors]=$this->participants($state['details']);if($errors!==[])return$this->render('participants',$state,$errors,$user,$person,['participants'=>$participants],422);
            $state['participants']=$participants;$this->save($user->id,$state);return Response::redirect($this->urls->to('/projects/create',['step'=>'review']));
        }
        if($step==='create'){
            if(!isset($state['participants'],$state['work_packages']))throw new HttpException(409,'The project creation session is incomplete.');
            $this->policy->requireCreate($user,$person);
            try{$id=$this->workflow->create($state['details'],$state['work_packages'],$state['participants']);}
            catch(\Throwable$exception){return$this->render('review',$state,['create'=>'Project creation failed. No project data was saved. '.$exception->getMessage()],$user,$person,[],422);}
            $this->session->remove(self::KEY);$this->flash->add('success','Project, Work Packages, and participants were created together.');
            return Response::redirect($this->urls->to('/projects/'.$id));
        }
        throw new HttpException(422,'Invalid creation step.');
    }
    private function render(string$step,array$state,array$errors,$user,$person,array$override=[],int$status=200):Response
    {
        $personPage=$this->people->search(['search'=>'','active'=>'active','internal'=>'all','position_type'=>'','linked'=>'all'],1,200);
        return new Response($this->view->render('projects/create_workflow',[
            'title'=>'Create project','step'=>$step,'state'=>$state,'errors'=>$errors,'override'=>$override,
            'csrfToken'=>$this->csrf->token(),'managerOptions'=>$user->isAdmin()?$this->projectRepository->managerOptions():[],
            'people'=>$personPage->items,'roles'=>ProjectParticipant::ROLE_LABELS,'statuses'=>Project::STATUS_LABELS,
        ]),$status);
    }
    private function actor():array{$user=$this->authorization->user();$person=$this->currentPerson->get();$this->policy->requireCreate($user,$person);return[$user,$person];}
    private function details():array
    {
        $out=[];foreach(['acronym','title','description','manager_person_id','start_date','end_date','status','hours_per_pm','notes']as$key)$out[$key]=trim((string)$this->request->post($key,''));
        foreach(['internal_code','grant_agreement_number','funding_agency','funding_programme','coordinator_organization','total_budget','currency','website_url']as$key)$out[$key]='';
        return$out;
    }
    private function workPackages(array$details):array
    {
        $raw=$this->request->post('work_packages',[]);if(!is_array($raw))return[[],['work_packages'=>'Invalid Work Package list.']];
        $out=[];$errors=[];$codes=[];
        foreach($raw as$i=>$row){if(!is_array($row))continue;$code=trim((string)($row['code']??''));$title=trim((string)($row['title']??''));if($code===''&&$title==='')continue;
            $start=trim((string)($row['start_date']??$details['start_date']));$end=trim((string)($row['end_date']??$details['end_date']));
            if($code===''||strlen($code)>50)$errors["wp_$i"]='Each Work Package needs a code of at most 50 characters.';
            if($title===''||strlen($title)>255)$errors["wp_title_$i"]='Each Work Package needs a title of at most 255 characters.';
            $fold=mb_strtolower($code);if(isset($codes[$fold]))$errors["wp_duplicate_$i"]='Work Package codes must be unique.';$codes[$fold]=true;
            if($start!==''&&$end!==''&&$end<$start)$errors["wp_dates_$i"]='A Work Package end date cannot precede its start date.';
            if(($start!==''&&$details['start_date']!==''&&$start<$details['start_date'])||($end!==''&&$details['end_date']!==''&&$end>$details['end_date']))$errors["wp_range_$i"]='Work Package dates must stay within project dates.';
            $out[]=['code'=>$code,'title'=>$title,'start_date'=>$start,'end_date'=>$end];
        }return[$out,$errors];
    }
    private function participants(array$details):array
    {
        $raw=$this->request->post('participants',[]);if(!is_array($raw))return[[],['participants'=>'Invalid participant list.']];
        $out=[];$errors=[];$seen=[];
        foreach($raw as$i=>$row){if(!is_array($row))continue;$id=filter_var($row['person_id']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);if($id===false)continue;
            $id=(int)$id;$role=(string)($row['project_role']??'researcher');$person=$this->people->findById($id);
            if($person===null)$errors["participant_$i"]='A selected person no longer exists.';
            if(isset($seen[$id]))$errors["participant_duplicate_$i"]='A person may be selected only once.';$seen[$id]=true;
            if(!array_key_exists($role,ProjectParticipant::ROLE_LABELS))$errors["participant_role_$i"]='Select a valid participant role.';
            $start=trim((string)($row['participation_start']??$details['start_date']));$end=trim((string)($row['participation_end']??$details['end_date']));
            if($start!==''&&$end!==''&&$end<$start)$errors["participant_dates_$i"]='A participation end date cannot precede its start date.';
            $lower=array_filter([$details['start_date'],$person?->activeFrom?->format('Y-m-d')]);$upper=array_filter([$details['end_date'],$person?->activeTo?->format('Y-m-d')]);
            if(($start!==''&&$lower!==[]&&$start<max($lower))||($end!==''&&$upper!==[]&&$end>min($upper)))$errors["participant_range_$i"]='Participation dates must stay within the project and person active intervals.';
            $out[]=['person_id'=>$id,'person_name'=>$person?->fullName()??'Unavailable person','project_role'=>$role,'participation_start'=>$start,'participation_end'=>$end];
        }return[$out,$errors];
    }
    private function state(int$userId):array{$state=$this->session->get(self::KEY,[]);return is_array($state)&&($state['user_id']??null)===$userId?$state:['user_id'=>$userId];}
    private function save(int$userId,array$state):void{$state['user_id']=$userId;$this->session->put(self::KEY,$state);}
    private function requireCsrf():void{$token=$this->request->post('_csrf');if(!is_string($token)||!$this->csrf->validate($token))throw new HttpException(403,'Invalid CSRF token.');}
}
