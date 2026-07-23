<?php
declare(strict_types=1);
namespace App\Controllers;
use App\Auth\Authorization;
use App\Auth\CapacityPolicy;
use App\Auth\CurrentPerson;
use App\Auth\Csrf;
use App\Exceptions\DuplicateCapacityOverrideException;
use App\Exceptions\HttpException;
use App\Http\Request;
use App\Http\Response;
use App\Models\Person;
use App\Models\PersonCapacityOverride;
use App\Models\PersonHourAllocation;
use App\Repositories\PersonCapacityRepository;
use App\Repositories\PersonRepository;
use App\Services\PersonCapacityService;
use App\Support\DecimalHours;
use App\Support\Flash;
use App\Support\UrlGenerator;
use App\Support\View;
final class PersonCapacityController
{
    public function __construct(private readonly Request$request,private readonly View$view,private readonly Authorization$authorization,private readonly CurrentPerson$currentPerson,private readonly CapacityPolicy$policy,private readonly PersonRepository$people,private readonly PersonCapacityRepository$capacity,private readonly PersonCapacityService$service,private readonly DecimalHours$decimals,private readonly Csrf$csrf,private readonly Flash$flash,private readonly UrlGenerator$urls){}
    public function overview():Response
    {
        $user=$this->authorization->user();$person=$this->currentPerson->get();$this->policy->requireGlobal($user,$person);$year=$this->year($this->request->query('year',date('Y')));
        return new Response($this->view->render('capacity/overview',['title'=>'Capacity','page'=>$this->service->overview($user,$person,$year),'decimals'=>$this->decimals]));
    }
    public function show(array$p):Response
    {
        $user=$this->authorization->user();$person=$this->person($p);if(!$this->policy->canViewOwn($user,$this->currentPerson->get(),$person))throw new \App\Exceptions\AuthorizationException('Capacity access is not permitted.');$year=$this->year($this->request->query('year',date('Y')));
        return new Response($this->view->render('capacity/show',['title'=>'Capacity — '.$person->fullName(),'person'=>$person,'year'=>$year,'months'=>$this->service->annual($person,$year),'isAdmin'=>$user->isAdmin(),'decimals'=>$this->decimals]));
    }
    public function createForm(array$p):Response{$this->authorization->admin();$person=$this->person($p);$v=$this->emptyValues();foreach(['year','month']as$k)if($this->request->query($k)!==null)$v[$k]=(string)$this->request->query($k);return$this->form('Create capacity override','create',$person,null,[],$v);}
    public function create(array$p):Response
    {
        $this->authorization->admin();$person=$this->person($p);$this->requireCsrf();$input=$this->request->postData();$errors=$this->service->validateOverride($person,$input);
        if($errors!==[])return$this->form('Create capacity override','create',$person,null,$errors,$input,422);
        try{$o=$this->service->createOverride($person,$input);}catch(DuplicateCapacityOverrideException$e){return$this->form('Create capacity override','create',$person,null,['month'=>$e->getMessage()],$input,422);}
        $this->flash->add('success',$o->monthLabel().' capacity override was created.');return Response::redirect($this->urls->to('/people/'.$person->id.'/capacity',['year'=>$o->year]));
    }
    public function editForm(array$p):Response{$this->authorization->admin();$person=$this->person($p);$o=$this->override($p,$person);return$this->form('Edit capacity override','edit',$person,$o,[],$this->values($o));}
    public function update(array$p):Response
    {
        $this->authorization->admin();$person=$this->person($p);$o=$this->override($p,$person);$this->requireCsrf();$input=$this->request->postData();$errors=$this->service->validateOverride($person,$input,$o->id);
        if($errors!==[])return$this->form('Edit capacity override','edit',$person,$o,$errors,$input,422);
        try{$updated=$this->service->updateOverride($person,$o,$input);}catch(DuplicateCapacityOverrideException$e){return$this->form('Edit capacity override','edit',$person,$o,['month'=>$e->getMessage()],$input,422);}
        $this->flash->add('success',$updated->monthLabel().' capacity override was updated.');return Response::redirect($this->urls->to('/people/'.$person->id.'/capacity',['year'=>$updated->year]));
    }
    public function removeForm(array$p):Response
    {
        $this->authorization->admin();$person=$this->person($p);$o=$this->override($p,$person);$summary=$this->service->month($person,$o->year,$o->month);
        return new Response($this->view->render('capacity/remove',['title'=>'Remove capacity override','person'=>$person,'override'=>$o->withoutNotes(),'summary'=>$summary,'decimals'=>$this->decimals,'csrfToken'=>$this->csrf->token()]));
    }
    public function remove(array$p):Response
    {
        $this->authorization->admin();$person=$this->person($p);$o=$this->override($p,$person);$this->requireCsrf();$year=$o->year;$label=$o->monthLabel();$this->service->removeOverride($person,$o);$this->flash->add('success',$label.' now uses the standard capacity.');return Response::redirect($this->urls->to('/people/'.$person->id.'/capacity',['year'=>$year]));
    }
    private function person(array$p):Person{$id=$this->id($p['personId']??null);return$this->people->findById($id)??throw new HttpException(404,'Person not found.');}
    private function override(array$p,Person$person):PersonCapacityOverride{$o=$this->capacity->findOverrideById($this->id($p['overrideId']??null));if($o===null||$o->personId!==$person->id)throw new HttpException(404,'Capacity override not found.');return$o;}
    private function id(mixed$v):int{$id=filter_var($v,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);if($id===false)throw new HttpException(404,'Record not found.');return(int)$id;}
    private function year(mixed$v):int{$y=filter_var($v,FILTER_VALIDATE_INT,['options'=>['min_range'=>PersonHourAllocation::MIN_YEAR,'max_range'=>PersonHourAllocation::MAX_YEAR]]);return$y===false?(int)date('Y'):(int)$y;}
    private function requireCsrf():void{$t=$this->request->post('_csrf');if(!is_string($t)||!$this->csrf->validate($t))throw new HttpException(403,'Invalid CSRF token.');}
    private function form(string$title,string$mode,Person$person,?PersonCapacityOverride$o,array$errors,array$values,int$status=200):Response
    {
        $year=$this->year($values['year']??date('Y'));$month=filter_var($values['month']??date('n'),FILTER_VALIDATE_INT,['options'=>['min_range'=>1,'max_range'=>12]])?:1;
        return new Response($this->view->render('capacity/form',['title'=>$title,'mode'=>$mode,'person'=>$person,'override'=>$o,'errors'=>$errors,'values'=>$values,'current'=>$this->service->month($person,$year,(int)$month),'decimals'=>$this->decimals,'csrfToken'=>$this->csrf->token()]),$status);
    }
    private function emptyValues():array{return['year'=>(string)date('Y'),'month'=>(string)date('n'),'available_hours'=>'','notes'=>''];}
    private function values(PersonCapacityOverride$o):array{return['year'=>(string)$o->year,'month'=>(string)$o->month,'available_hours'=>$o->availableHours,'notes'=>$o->notes??''];}
}
