<?php
declare(strict_types=1);
namespace Tests\Fakes;
use App\Exceptions\AuthorizationException;
use App\Exceptions\DuplicateProjectFieldException;
use App\Models\Project;
use App\Models\ProjectManagerOption;
use App\Models\ProjectPage;
use App\Repositories\ProjectRepository;
use DateTimeImmutable;
final class InMemoryProjectRepository implements ProjectRepository
{
    public function accessibleFor(string $role,?int $personId,int $limit=200):array{return array_slice(array_values(array_filter($this->projects,static fn(Project$p):bool=>!$p->isDeleted())),0,$limit);}
    public function accessibleForYear(string $role,?int $personId,int$year,int$limit=200):array
    {
        $start=sprintf('%04d-01-01',$year);$end=sprintf('%04d-01-01',$year+1);
        return array_slice(array_values(array_filter($this->projects,static fn(Project$p):bool=>
            !$p->isDeleted()&&($p->startDate===null||$p->startDate->format('Y-m-d')<$end)&&($p->endDate===null||$p->endDate->format('Y-m-d')>=$start)
        )),0,$limit);
    }
    /** @var array<int,Project> */ public array $projects=[];
    /** @var array<int,ProjectManagerOption> */ public array $people=[];
    private int $next=1;
    /** @param list<ProjectManagerOption> $people */ public function __construct(array $people=[]){foreach($people as $p)$this->people[$p->id]=$p;}
    public function findById(int $id):?Project{$p=$this->projects[$id]??null;return$p?->isDeleted()?null:$p;}
    public function findIncludingDeleted(int$id):?Project{return$this->projects[$id]??null;}
    public function search(array $f,int $page,int $per):ProjectPage{$items=array_values(array_filter($this->projects,static function(Project $p)use($f){$h=strtolower(implode(' ',[$p->acronym,$p->title,$p->internalCode,$p->grantAgreementNumber,$p->fundingAgency,$p->fundingProgramme,$p->coordinatorOrganization,$p->managerName,$p->managerEmail]));return!$p->isDeleted()&&($f['search']===''||str_contains($h,strtolower($f['search'])))&&($f['status']===''||$p->status===$f['status'])&&($f['manager_person_id']===''||$p->managerPersonId===(int)$f['manager_person_id'])&&($f['funding_agency']===''||$p->fundingAgency===$f['funding_agency'])&&($f['funding_programme']===''||$p->fundingProgramme===$f['funding_programme']);}));usort($items,static fn(Project $a,Project $b)=>[$b->startDate?->format('Y-m-d')??'', $a->acronym,$a->id]<=>[$a->startDate?->format('Y-m-d')??'',$b->acronym,$b->id]);$items=array_map(static fn(Project $p):Project=>$p->withoutNotes(),$items);return new ProjectPage(array_slice($items,($page-1)*$per,$per),count($items),$page,$per);}
    public function create(array $d):Project{$this->duplicates($d,null);return$this->projects[$this->next]=$this->make($this->next++,$d);}
    public function update(int $id,array $d,?int $required=null):Project{$p=$this->projects[$id]??throw new \OutOfBoundsException();if($required!==null&&!$p->isOwnedBy($required))throw new AuthorizationException('Project ownership changed.');$this->duplicates($d,$id);return$this->projects[$id]=$this->make($id,$d);}
    public function updateStatus(int $id,string $status,?int $required=null):Project{$p=$this->projects[$id]??throw new \OutOfBoundsException();if($required!==null&&!$p->isOwnedBy($required))throw new AuthorizationException();$d=$this->data($p);$d['status']=$status;return$this->projects[$id]=$this->make($id,$d);}
    public function acronymExists(string $v,?int $x=null):bool{return$this->exists('acronym',$v,$x);}
    public function internalCodeExists(string $v,?int $x=null):bool{return$this->exists('internalCode',$v,$x);}
    public function grantAgreementNumberExists(string $v,?int $x=null):bool{return$this->exists('grantAgreementNumber',$v,$x);}
    public function personExists(int $id):bool{return isset($this->people[$id]);}
    public function managerOptions():array{return array_values($this->people);}
    private function exists(string $field,string $v,?int $x):bool{foreach($this->projects as $p)if($p->id!==$x&&strtolower((string)$p->$field)===strtolower($v))return true;return false;}
    private function duplicates(array $d,?int $x):void{foreach(['acronym'=>'acronym','internal_code'=>'internalCode','grant_agreement_number'=>'grantAgreementNumber']as$f=>$prop)if($d[$f]!==null&&$this->exists($prop,$d[$f],$x))throw new DuplicateProjectFieldException($f,'duplicate');}
    private function make(int $id,array $d):Project{$now=new DateTimeImmutable('2026-01-01');$m=$d['manager_person_id']===null?null:($this->people[$d['manager_person_id']]??null);return new Project($id,$d['acronym'],$d['title'],$d['description'],$d['internal_code'],$d['grant_agreement_number'],$d['funding_agency'],$d['funding_programme'],$d['coordinator_organization'],$d['manager_person_id'],$d['start_date']===null?null:new DateTimeImmutable($d['start_date']),$d['end_date']===null?null:new DateTimeImmutable($d['end_date']),$d['status'],$d['total_budget'],$d['currency'],$d['website_url'],$d['notes'],$now,$now,$m?->name,null,$d['hours_per_pm']??'125.00');}
    private function data(Project $p):array{return['acronym'=>$p->acronym,'title'=>$p->title,'description'=>$p->description,'internal_code'=>$p->internalCode,'grant_agreement_number'=>$p->grantAgreementNumber,'funding_agency'=>$p->fundingAgency,'funding_programme'=>$p->fundingProgramme,'coordinator_organization'=>$p->coordinatorOrganization,'manager_person_id'=>$p->managerPersonId,'start_date'=>$p->startDate?->format('Y-m-d'),'end_date'=>$p->endDate?->format('Y-m-d'),'status'=>$p->status,'total_budget'=>$p->totalBudget,'currency'=>$p->currency,'hours_per_pm'=>$p->hoursPerPm,'website_url'=>$p->websiteUrl,'notes'=>$p->notes];}
}
