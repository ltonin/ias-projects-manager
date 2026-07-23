<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Auth\ProjectPolicy;
use App\Exceptions\AuthorizationException;
use App\Exceptions\DuplicatePersonHourAllocationException;
use App\Exceptions\ParticipantHasAllocationsException;
use App\Models\ParticipantPersonOption;
use App\Models\User;
use App\Models\WorkPackage;
use App\Repositories\WorkPackageRepository;
use App\Services\PersonHourAllocationService;
use App\Services\ProjectParticipantService;
use App\Support\PersonMonthConverter;
use App\Validation\PersonHourAllocationValidator;
use App\Validation\ProjectParticipantValidator;
use PHPUnit\Framework\TestCase;
use Tests\Fakes\InMemoryPersonHourAllocationRepository;
use Tests\Fakes\InMemoryPersonRepository;
use Tests\Fakes\InMemoryProjectParticipantRepository;
use Tests\Fakes\InMemoryProjectRepository;
use Tests\Support\UserFactory;

final class PersonHourAllocationServiceTest extends TestCase
{
    public function testAdminCreatesPlannedActualBothZeroAndAboveOnePm():void
    {
        [$service,$allocations,,,,$project,$participant]=$this->context();$admin=UserFactory::make();
        foreach([
            ['year'=>'2027','month'=>'1','planned_hours'=>'62.5','actual_hours'=>''],
            ['year'=>'2027','month'=>'2','planned_hours'=>'','actual_hours'=>'75'],
            ['year'=>'2027','month'=>'3','planned_hours'=>'150','actual_hours'=>'125'],
            ['year'=>'2027','month'=>'4','planned_hours'=>'0','actual_hours'=>'0.00'],
        ]as$input)$service->create($project,$participant,$this->input($input),$admin,null);
        self::assertSame(4,$allocations->countForParticipant($participant->id));
        self::assertSame('212.50',$allocations->totalsForParticipant($participant->id)->plannedHours);
        self::assertSame('200.00',$allocations->totalsForProject($project->id)->actualHours);
        self::assertSame('0.00',$allocations->findByParticipantAndMonth($participant->id,2027,4)?->plannedHours);
    }
    public function testOwnerMayManageWhileOtherRolesCannotAndOwnershipIsRechecked():void
    {
        [$service,$allocations,,, $ownerPerson,$project,$participant]=$this->context();$owner=UserFactory::make(role:User::ROLE_PROJECT_MANAGER);
        $created=$service->create($project,$participant,$this->input(),$owner,$ownerPerson);self::assertSame('1.00',$created->plannedHours);
        foreach([[User::ROLE_PROJECT_MANAGER,null],[User::ROLE_PARTICIPANT,null],[User::ROLE_VIEWER,null]]as[$role,$person]){
            try{$service->create($project,$participant,$this->input(['month'=>(string)(2+$allocations->countForParticipant(1))]),UserFactory::make(role:$role),$person);self::fail($role.' wrote allocation');}catch(AuthorizationException){self::assertTrue(true);}
        }
        $allocations->contexts[$participant->id]['manager_id']=99;
        $this->expectException(AuthorizationException::class);
        $service->update($project,$participant,$created,$this->input(['planned_hours'=>'2']),$owner,$ownerPerson);
    }
    public function testDuplicateValidationUpdateRemovalAndFactorChangePreserveHours():void
    {
        [$service,$allocations,$projects,,,$project,$participant]=$this->context();$admin=UserFactory::make();$converter=new PersonMonthConverter();
        $created=$service->create($project,$participant,$this->input(['planned_hours'=>'75']),$admin,null);
        self::assertSame('0.600',$created->plannedPm($converter));
        self::assertArrayHasKey('month',$service->validate($project,$participant,$this->input()));
        try{$allocations->create(['project_participant_id'=>$participant->id,'work_package_id'=>101,'year'=>2027,'month'=>6,'planned_hours'=>'2.00','actual_hours'=>null,'notes'=>null]);self::fail('race duplicate accepted');}catch(DuplicatePersonHourAllocationException){self::assertTrue(true);}
        $updated=$service->update($project,$participant,$created,$this->input(['year'=>'2027','month'=>'7','planned_hours'=>'75']),$admin,null);self::assertSame(7,$updated->month);
        $changed=$projects->update($project->id,$this->projectData('150.00'));
        $stored=$allocations->findById($updated->id);self::assertSame('75.00',$stored?->plannedHours);
        $recontextualized=new \App\Models\PersonHourAllocation($stored->id,$stored->projectParticipantId,$stored->year,$stored->month,$stored->plannedHours,$stored->actualHours,$stored->notes,$stored->createdAt,$stored->updatedAt,$stored->projectId,$stored->personId,$stored->personName,$stored->projectRole,$stored->projectAcronym,$stored->projectTitle,$stored->projectStatus,$changed->hoursPerPm);
        self::assertSame('0.500',$recontextualized->plannedPm($converter));
        $service->remove($changed,$participant,$recontextualized,$admin,null);self::assertSame(0,$allocations->countForParticipant($participant->id));
    }
    public function testParticipantRemovalBlockedUntilAllocationsDeleted():void
    {
        [$allocationService,$allocations,$projects,$participantRepo,$ownerPerson,$project,$participant,$people]=$this->context();$admin=UserFactory::make();
        $allocation=$allocationService->create($project,$participant,$this->input(),$admin,null);
        $participantService=new ProjectParticipantService($participantRepo,$projects,$people,new ProjectParticipantValidator(),new ProjectPolicy(),$allocations);
        try{$participantService->remove($project,$participant,$admin,null);self::fail('Participant with history removed');}catch(ParticipantHasAllocationsException){self::assertNotNull($participantRepo->findById($participant->id));self::assertNotNull($allocations->findById($allocation->id));}
        $allocationService->remove($project,$participant,$allocation,$admin,null);
        $participantService->remove($project,$participant,$admin,null);self::assertNull($participantRepo->findById($participant->id));self::assertNotNull($people->findById($participant->personId));
    }
    public function testMultipleWorkPackagesAndOneUnassignedRowShareMonthAndAggregatePrecisely():void
    {
        [, $allocations,,,,,$participant]=$this->context();$base=['project_participant_id'=>$participant->id,'year'=>2027,'month'=>6,'actual_hours'=>null,'notes'=>null];
        $allocations->seedLegacy($base+['planned_hours'=>'10.00']);
        $allocations->create($base+['work_package_id'=>101,'planned_hours'=>'30.00']);
        $allocations->create($base+['work_package_id'=>102,'planned_hours'=>'20.00']);
        $totals=$allocations->totalsForParticipant($participant->id);
        self::assertSame('60.00',$totals->plannedHours);self::assertSame(3,$totals->allocationCount);self::assertSame(1,$totals->distinctMonthCount);
        self::assertSame('30.00',$allocations->totalsForWorkPackage(101)->plannedHours);
        self::assertSame('10.00',$allocations->totalsForUnassignedProject(1)->plannedHours);
        try{$allocations->create($base+['work_package_id'=>101,'planned_hours'=>'1.00']);self::fail('Duplicate accepted');}catch(DuplicatePersonHourAllocationException){self::assertTrue(true);}
        try{$allocations->create($base+['work_package_id'=>null,'planned_hours'=>'1.00']);self::fail('Unassigned creation accepted');}catch(\InvalidArgumentException){self::assertTrue(true);}
        $allocations->create(array_merge($base,['work_package_id'=>101,'planned_hours'=>'5.00','month'=>7]));
        self::assertSame(2,$allocations->totalsForWorkPackage(101)->distinctMonthCount);
    }
    public function testMandatoryClassificationAndLegacyReclassificationPreserveStoredEffort():void
    {
        [$service,$allocations,,,,$project,$participant]=$this->context();$admin=UserFactory::make();
        foreach(['','0','unassigned','01']as$value)self::assertArrayHasKey('work_package_id',$service->validate($project,$participant,$this->input(['work_package_id'=>$value])));
        self::assertArrayHasKey('work_package_id',$service->validate($project,$participant,$this->input(['work_package_id'=>'999'])));
        self::assertArrayHasKey('work_package_id',$service->validate($project,$participant,$this->input(['work_package_id'=>'201'])));
        $legacy=$allocations->seedLegacy(['project_participant_id'=>$participant->id,'year'=>2027,'month'=>8,'planned_hours'=>'10.00','actual_hours'=>'7.50','notes'=>'keep me']);
        $before=$allocations->totalsForProject($project->id);$updated=$service->reclassifyLegacy($project,$participant,$legacy,['work_package_id'=>'102'],$admin,null);
        self::assertSame($legacy->id,$updated->id);self::assertSame(102,$updated->workPackageId);self::assertSame('10.00',$updated->plannedHours);self::assertSame('7.50',$updated->actualHours);self::assertSame('keep me',$updated->notes);
        self::assertSame(0,$allocations->totalsForUnassignedProject($project->id)->allocationCount);self::assertSame('10.00',$allocations->totalsForWorkPackage(102)->plannedHours);
        self::assertSame($before->plannedHours,$allocations->totalsForProject($project->id)->plannedHours);
        $duplicateLegacy=$allocations->seedLegacy(['project_participant_id'=>$participant->id,'year'=>2027,'month'=>9,'planned_hours'=>'3.00','actual_hours'=>null,'notes'=>'legacy']);
        $allocations->create(['project_participant_id'=>$participant->id,'work_package_id'=>101,'year'=>2027,'month'=>9,'planned_hours'=>'4.00','actual_hours'=>null,'notes'=>'target']);
        self::assertArrayHasKey('work_package_id',$service->validateReclassification($project,$participant,$duplicateLegacy,['work_package_id'=>'101']));
        self::assertSame(3,$allocations->countForParticipant($participant->id));self::assertNull($allocations->findById($duplicateLegacy->id)?->workPackageId);
        $this->expectException(AuthorizationException::class);
        $service->reclassifyLegacy($project,$participant,$duplicateLegacy,['work_package_id'=>'102'],UserFactory::make(role:User::ROLE_VIEWER),null);
    }
    private function context():array
    {
        $people=new InMemoryPersonRepository();$owner=$people->create($this->personData('Owner'));$member=$people->create($this->personData('Member'));
        $projects=new InMemoryProjectRepository();$project=$projects->create($this->projectData());
        $participantRepo=new InMemoryProjectParticipantRepository([new ParticipantPersonOption($member->id,$member->fullName(),$member->positionLabel(),$member->affiliation,$member->institutionalEmail,true,null,'2025-01-01',null)]);
        $participantRepo->projectManagers[$project->id]=$owner->id;$participant=$participantRepo->create(['project_id'=>$project->id,'person_id'=>$member->id,'project_role'=>'researcher','participation_start'=>'2026-01-01','participation_end'=>'2029-12-31','is_active'=>true,'notes'=>null]);
        $allocations=new InMemoryPersonHourAllocationRepository();$allocations->contexts[$participant->id]=['project_id'=>$project->id,'person_id'=>$member->id,'manager_id'=>$owner->id,'hours_per_pm'=>'125.00'];
        $now=new \DateTimeImmutable('2026-01-01');$packages=[
            101=>new WorkPackage(101,$project->id,'WP1','Research',null,null,null,null,true,null,$now,$now,'TEST','Test'),
            102=>new WorkPackage(102,$project->id,'WP2','Delivery',null,null,null,null,false,null,$now,$now,'TEST','Test'),
            201=>new WorkPackage(201,2,'XWP','Foreign',null,null,null,null,true,null,$now,$now,'OTHER','Other'),
        ];
        $workPackages=$this->createMock(WorkPackageRepository::class);$workPackages->method('findById')->willReturnCallback(static fn(int$id)=>$packages[$id]??null);$workPackages->method('optionsForProject')->willReturn(array_values($packages));
        $service=new PersonHourAllocationService($allocations,$projects,$participantRepo,new PersonHourAllocationValidator(),new ProjectPolicy(),$workPackages);
        return[$service,$allocations,$projects,$participantRepo,$owner,$project,$participant,$people];
    }
    private function input(array$o=[]):array{return$o+['work_package_id'=>'101','year'=>'2027','month'=>'6','planned_hours'=>'1','actual_hours'=>'','notes'=>'private'];}
    private function personData(string$name):array{return['user_id'=>null,'first_name'=>$name,'last_name'=>'Person','institutional_email'=>strtolower($name).'@example.test','affiliation'=>'University','position_type'=>'researcher','is_internal'=>true,'active_from'=>'2025-01-01','active_to'=>null,'is_active'=>true,'notes'=>null];}
    private function projectData(string$factor='125.00'):array{return['acronym'=>'TEST','title'=>'Test','description'=>null,'internal_code'=>null,'grant_agreement_number'=>null,'funding_agency'=>null,'funding_programme'=>null,'coordinator_organization'=>null,'manager_person_id'=>1,'start_date'=>'2026-01-01','end_date'=>'2029-12-31','status'=>'active','total_budget'=>null,'currency'=>null,'hours_per_pm'=>$factor,'website_url'=>null,'notes'=>null];}
}
