<?php
declare(strict_types=1);
namespace Tests\Unit;

use App\Auth\ProjectPolicy;
use App\Database\ConnectionFactory;
use App\Models\User;
use App\Repositories\PdoAnnualEffortRepository;
use App\Repositories\PdoProjectParticipantRepository;
use App\Repositories\PdoProjectRepository;
use App\Repositories\PdoWorkPackageRepository;
use App\Services\AnnualEffortService;
use App\Support\ConfigLoader;
use App\Support\DecimalHours;
use App\Support\Flash;
use App\Support\PersonMonthConverter;
use App\Support\UrlGenerator;
use App\Support\View;
use App\Validation\PersonHourAllocationValidator;
use DateTimeImmutable;
use PDO;
use PHPUnit\Framework\TestCase;

final class AnnualEffortPersistenceIntegrationTest extends TestCase
{
    public function testDatabaseRepositoryAndRenderedTotalsAgreeAcrossSaveReloadAndMixedEffort():void
    {
        try{$config=(new ConfigLoader(dirname(__DIR__,2)))->load();$factory=new ConnectionFactory($config);$pdo=$factory->create();}
        catch(\Throwable){self::markTestSkipped('Configured MySQL is unavailable.');}
        if(!$this->hasTrashSchema($pdo))self::markTestSkipped('Current database schema is not migrated.');
        $suffix=bin2hex(random_bytes(5));$projectId=$personId=$participantId=null;
        try{
            $pdo->prepare('INSERT INTO people(first_name,last_name,institutional_email,position_type,is_internal,is_active,annual_capacity_hours) VALUES("Persistence","Fixture",:email,"researcher",1,1,1500.00)')->execute(['email'=>"effort-$suffix@example.test"]);$personId=(int)$pdo->lastInsertId();
            $pdo->prepare('INSERT INTO projects(acronym,title,manager_person_id,start_date,end_date,status,hours_per_pm) VALUES(:code,"Persistence fixture",NULL,"2027-01-01","2027-12-31","active",125.00)')->execute(['code'=>'FX'.strtoupper($suffix)]);$projectId=(int)$pdo->lastInsertId();
            $pdo->prepare('INSERT INTO project_participants(project_id,person_id,project_role,is_active) VALUES(:project,:person,"researcher",1)')->execute(['project'=>$projectId,'person'=>$personId]);$participantId=(int)$pdo->lastInsertId();
            $repo=new PdoAnnualEffortRepository($factory);$token=$repo->snapshotToken([]);
            self::assertSame(3,$repo->save($projectId,2027,[
                ['participant_id'=>$participantId,'work_package_id'=>null,'month'=>1,'value'=>'10.00'],
                ['participant_id'=>$participantId,'work_package_id'=>null,'month'=>2,'value'=>'20.00'],
                ['participant_id'=>$participantId,'work_package_id'=>null,'month'=>3,'value'=>'5.50'],
            ],$token,null));
            $rows=$repo->forProjectYear($projectId,2027);self::assertCount(3,$rows);self::assertSame('35.50',$this->sum($rows));
            $service=$this->service($factory,$repo);$project=(new PdoProjectRepository($factory))->findById($projectId);self::assertNotNull($project);
            $page=$service->page($project,2027,$this->admin(),null);self::assertSame('35.50',$page->projectAnnualHours);
            $html=(new View(dirname(__DIR__,2).'/views',new UrlGenerator('https://example.test'),new Flash()))->render('annual_effort/show',['title'=>'Fixture','page'=>$page,'error'=>null,'submitted'=>[],'csrfToken'=>'csrf','converter'=>new PersonMonthConverter(),'editMode'=>true,'canEditHours'=>true]);
            self::assertSame(['10.00','20.00','5.50'],array_map(static fn(int$m):string=>$page->projectMonthlyHours[$m],[1,2,3]));
            self::assertStringContainsString('data-month-total="1"',$html);self::assertStringContainsString('data-project-month="1">10.00',$html);
            self::assertStringContainsString('data-project-month="2">20.00',$html);self::assertStringContainsString('data-project-month="12">0.00',$html);
            self::assertStringContainsString('data-project-annual>35.50 h',$html);self::assertStringContainsString('data-project-pm>0.284 PM',$html);

            $pdo->prepare('INSERT INTO work_packages(project_id,code,title,is_active) VALUES(:p1,"WP1","First",1),(:p2,"WP2","Second",1)')->execute(['p1'=>$projectId,'p2'=>$projectId]);
            $wpIds=array_map('intval',$pdo->query('SELECT id FROM work_packages WHERE project_id='.$projectId.' ORDER BY id')->fetchAll(PDO::FETCH_COLUMN));
            $token=$repo->snapshotToken($rows);$repo->save($projectId,2027,[
                ['participant_id'=>$participantId,'work_package_id'=>$wpIds[0],'month'=>1,'value'=>'4.00'],
                ['participant_id'=>$participantId,'work_package_id'=>$wpIds[1],'month'=>2,'value'=>'6.00'],
            ],$token,null);
            $mixed=$repo->forProjectYear($projectId,2027);self::assertSame('45.50',$this->sum($mixed));
            $mixedPage=$service->page($project,2027,$this->admin(),null);self::assertSame('45.50',$mixedPage->projectAnnualHours);
            self::assertSame('14.00',$mixedPage->projectMonthlyHours[1]);self::assertSame('26.00',$mixedPage->projectMonthlyHours[2]);self::assertSame('5.50',$mixedPage->projectMonthlyHours[3]);
            $token=$repo->snapshotToken($mixed);self::assertSame(3,$repo->save($projectId,2027,[
                ['participant_id'=>$participantId,'work_package_id'=>null,'month'=>1,'value'=>null],
                ['participant_id'=>$participantId,'work_package_id'=>null,'month'=>2,'value'=>null],
                ['participant_id'=>$participantId,'work_package_id'=>null,'month'=>3,'value'=>null],
            ],$token,null));
            $remaining=$repo->forProjectYear($projectId,2027);self::assertCount(2,$remaining);$clearedPage=$service->page($project,2027,$this->admin(),null);self::assertSame('10.00',$clearedPage->projectAnnualHours);
            self::assertSame('4.00',$clearedPage->projectMonthlyHours[1]);self::assertSame('6.00',$clearedPage->projectMonthlyHours[2]);
            $readOnly=(new View(dirname(__DIR__,2).'/views',new UrlGenerator('https://example.test'),new Flash()))->render('annual_effort/show',['title'=>'Fixture','page'=>$clearedPage,'error'=>null,'submitted'=>[],'csrfToken'=>'','converter'=>new PersonMonthConverter(),'editMode'=>false,'canEditHours'=>true]);
            self::assertStringNotContainsString('data-project-level-section',$readOnly);self::assertStringContainsString('data-project-annual>10.00 h',$readOnly);
        }finally{$this->cleanup($pdo,$projectId,$personId);}
    }
    private function service(ConnectionFactory$f,PdoAnnualEffortRepository$r):AnnualEffortService{return new AnnualEffortService(new PdoProjectRepository($f),new PdoProjectParticipantRepository($f),new PdoWorkPackageRepository($f),$r,new PersonHourAllocationValidator(),new ProjectPolicy(),new DecimalHours());}
    private function admin():User{$n=new DateTimeImmutable('2026-01-01');return new User(1,'fixture-admin','fixture@example.test','hash','Fixture','Admin',User::ROLE_ADMIN,true,null,$n,$n);}
    private function sum(array$rows):string{$c=0;foreach($rows as$r)$c+=(new DecimalHours())->cents($r->allocatedHours()??'0.00');return(new DecimalHours())->format($c);}
    private function hasTrashSchema(PDO$p):bool{$s=$p->query("SHOW COLUMNS FROM projects LIKE 'deleted_at'");return$s->fetch()!==false;}
    private function cleanup(PDO$p,?int$projectId,?int$personId):void
    {
        if($projectId!==null){$s=$p->prepare('DELETE a FROM person_hour_allocations a JOIN project_participants pp ON pp.id=a.project_participant_id WHERE pp.project_id=:p');$s->execute(['p'=>$projectId]);$p->prepare('UPDATE work_packages SET responsible_participant_id=NULL WHERE project_id=:p')->execute(['p'=>$projectId]);$p->prepare('DELETE FROM work_packages WHERE project_id=:p')->execute(['p'=>$projectId]);$p->prepare('DELETE FROM project_participants WHERE project_id=:p')->execute(['p'=>$projectId]);$p->prepare('DELETE FROM projects WHERE id=:p')->execute(['p'=>$projectId]);}
        if($personId!==null)$p->prepare('DELETE FROM people WHERE id=:p')->execute(['p'=>$personId]);
    }
}
