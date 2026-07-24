<?php
declare(strict_types=1);
namespace Tests\Unit;

use App\Auth\ProjectPolicy;
use App\Models\Project;
use App\Models\User;
use App\Models\WorkPackage;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Tests\Support\PersonFactory;
use Tests\Support\UserFactory;

final class WorkPackagePrivacyAndPolicyTest extends TestCase
{
    public function testNotesArePhysicallyRemoved():void{$wp=$this->wp();self::assertSame('secret',$wp->notes);self::assertNull($wp->withoutNotes()->notes);self::assertSame($wp->code,$wp->withoutNotes()->code);}
    public function testAdminAndOwnerManageAndViewNotes():void
    {
        $policy=new ProjectPolicy();$project=$this->project();$person=PersonFactory::make();
        self::assertTrue($policy->canManageWorkPackages(UserFactory::make(role:User::ROLE_ADMIN),null,$project));
        self::assertTrue($policy->canManageWorkPackages(UserFactory::make(role:User::ROLE_PROJECT_MANAGER),$person,$project));
        self::assertTrue($policy->canViewWorkPackageNotes(UserFactory::make(role:User::ROLE_PROJECT_MANAGER),$person,$project));
    }
    public function testReadOnlyAndNonOwnerCannotManageOrSeeNotes():void
    {
        $policy=new ProjectPolicy();$project=$this->project();
        foreach([User::ROLE_PARTICIPANT,User::ROLE_VIEWER]as$role){$u=UserFactory::make(role:$role);self::assertFalse($policy->canManageWorkPackages($u,null,$project));self::assertFalse($policy->canViewWorkPackageNotes($u,null,$project));}
        self::assertFalse($policy->canManageWorkPackages(UserFactory::make(role:User::ROLE_PROJECT_MANAGER),PersonFactory::make(id:2),$project));
        self::assertFalse($policy->canManageWorkPackages(UserFactory::make(role:User::ROLE_PROJECT_MANAGER),null,$project));
    }
    public function testOptionalAndInactiveResponsibilityWarnings():void
    {
        self::assertSame([],$this->wp(responsible:false)->warnings());
        self::assertCount(3,$this->wp(responsible:true)->warnings());
    }
    private function project():Project{$n=new DateTimeImmutable('2026-01-01');return new Project(1,'TEST','Test',null,null,null,null,null,null,1,null,null,'active',null,null,null,null,$n,$n);}
    private function wp(bool$responsible=true):WorkPackage{$n=new DateTimeImmutable('2026-01-01');return new WorkPackage(1,1,'WP1','Research',null,null,null,$responsible?2:null,true,'secret',$n,$n,'TEST','Test',$responsible?'Ada':null,$responsible?'Lovelace':null,$responsible?'researcher':null,$responsible?false:null,$responsible?false:null,$responsible?false:null);}
}
