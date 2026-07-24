<?php
declare(strict_types=1);
namespace Tests\Unit;

use App\Auth\ProjectPolicy;
use App\Exceptions\AuthorizationException;
use App\Models\Person;
use App\Models\Project;
use App\Models\User;
use App\Repositories\ProjectTrashRepository;
use App\Services\ProjectTrashService;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Tests\Support\PersonFactory;
use Tests\Support\UserFactory;

final class Milestone19ProjectTrashTest extends TestCase
{
    public function testAdministratorAndOwningManagerMayMoveButOtherManagerMayNot():void
    {
        $repo=$this->repository();$service=new ProjectTrashService($repo,new ProjectPolicy());$project=$this->project();
        $admin=UserFactory::make();$service->move($project,$admin,null);self::assertSame([[$project->id,$admin->id,null]],$repo->soft);
        $manager=UserFactory::make(id:2,role:User::ROLE_PROJECT_MANAGER);$person=PersonFactory::make(id:7);
        $service->move($project,$manager,$person);self::assertSame(7,$repo->soft[1][2]);
        $this->expectException(AuthorizationException::class);$service->move($project,$manager,PersonFactory::make(id:8));
    }
    public function testRestoreAndPermanentDeleteAreAdminOnlyAndConfirmationIsExact():void
    {
        $repo=$this->repository();$service=new ProjectTrashService($repo,new ProjectPolicy());$deleted=$this->project(new DateTimeImmutable('2026-07-24'));
        $admin=UserFactory::make();$service->restore($deleted,$admin);self::assertCount(1,$repo->restored);
        try{$service->permanentlyDelete($deleted,$admin,'wrong');self::fail('Confirmation must be exact.');}catch(\InvalidArgumentException){}
        self::assertSame(['work_packages'=>2,'participants'=>3,'allocations'=>4],$service->permanentlyDelete($deleted,$admin,'SAFE'));
        $this->expectException(AuthorizationException::class);$service->restore($deleted,UserFactory::make(role:User::ROLE_PROJECT_MANAGER));
    }
    public function testSchemaQueryScopeCapacityTransactionAndUiAreExplicit():void
    {
        $root=dirname(__DIR__,2);
        $migration=(string)file_get_contents($root.'/database/migrations/012_add_project_trash.sql');
        foreach(['deleted_at','deleted_by_user_id','project_deletion_audit','project_permanently_deleted']as$text)self::assertStringContainsString($text,$migration);
        $projects=(string)file_get_contents($root.'/app/Repositories/PdoProjectRepository.php');
        self::assertStringContainsString('pr.deleted_at IS NULL',$projects);self::assertStringContainsString('findIncludingDeleted',$projects);
        $capacity=(string)file_get_contents($root.'/app/Repositories/PdoPersonCapacityRepository.php');
        self::assertGreaterThanOrEqual(2,substr_count($capacity,'pr.deleted_at IS NULL'));
        $allocations=(string)file_get_contents($root.'/app/Repositories/PdoPersonHourAllocationRepository.php');
        self::assertGreaterThanOrEqual(6,substr_count($allocations,'pr.deleted_at IS NULL'));
        $trash=(string)file_get_contents($root.'/app/Repositories/PdoProjectTrashRepository.php');
        foreach(['beginTransaction','rollBack','project_permanently_deleted','DELETE a FROM person_hour_allocations','DELETE FROM projects WHERE id=:id AND deleted_at IS NOT NULL']as$text)self::assertStringContainsString($text,$trash);
        $delete=(string)file_get_contents($root.'/views/projects/trash_delete.php');
        self::assertStringContainsString('data-delete-submit disabled',$delete);self::assertStringContainsString('Administration → System',$delete);
    }
    private function project(?DateTimeImmutable$deleted=null):Project
    {
        $now=new DateTimeImmutable('2026-01-01');return new Project(10,'SAFE','Safe project',null,null,null,null,null,null,7,$now,$now,'active',null,null,null,null,$now,$now,'Owner',null,'125.00',$deleted,$deleted?1:null);
    }
    private function repository():ProjectTrashRepository
    {
        return new class implements ProjectTrashRepository{
            public array$soft=[],$restored=[],$deleted=[];
            public function listDeleted():array{return[];}public function summary(int$projectId):?array{return[];}
            public function softDelete(Project$p,int$u,?int$m):void{$this->soft[]=[$p->id,$u,$m];}
            public function restore(Project$p,int$u):void{$this->restored[]=[$p->id,$u];}
            public function permanentlyDelete(Project$p,int$u):array{$this->deleted[]=[$p->id,$u];return['work_packages'=>2,'participants'=>3,'allocations'=>4];}
        };
    }
}
