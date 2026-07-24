<?php
declare(strict_types=1);
namespace Tests\Unit;

use App\Auth\ProjectPolicy;
use App\Models\Project;
use App\Models\ProjectParticipant;
use App\Models\PersonHourAllocation;
use App\Models\WorkPackage;
use App\Repositories\AnnualEffortRepository;
use App\Repositories\ProjectParticipantRepository;
use App\Repositories\ProjectRepository;
use App\Repositories\WorkPackageRepository;
use App\Services\AnnualEffortService;
use App\Support\DecimalHours;
use App\Validation\PersonHourAllocationValidator;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Tests\Support\UserFactory;

final class AnnualEffortServiceUnifiedTest extends TestCase
{
    public function testCurrentMonthComesFromConfiguredServerTimezoneAndOtherYearsHaveNone():void
    {
        $old=date_default_timezone_get();date_default_timezone_set('Europe/Rome');
        try{
            $project=$this->project();$projects=$this->createMock(ProjectRepository::class);$participants=$this->createMock(ProjectParticipantRepository::class);$wps=$this->createMock(WorkPackageRepository::class);$effort=$this->createMock(AnnualEffortRepository::class);
            $participants->method('allForProject')->willReturn([]);$wps->method('optionsForProject')->willReturn([]);$effort->method('forProjectYear')->willReturn([]);$effort->method('capacityData')->willReturn([]);$effort->method('projectPersonTotals')->willReturn([]);$effort->method('unassignedSummary')->willReturn(['count'=>0,'planned'=>'0.00','actual'=>'0.00']);$effort->method('snapshotToken')->willReturn('token');
            $service=new AnnualEffortService($projects,$participants,$wps,$effort,new PersonHourAllocationValidator(),new ProjectPolicy(),new DecimalHours());
            self::assertSame((int)date('n'),$service->page($project,(int)date('Y'),UserFactory::make(),null)->currentMonth);
            self::assertNull($service->page($project,(int)date('Y')+1,UserFactory::make(),null)->currentMonth);
        }finally{date_default_timezone_set($old);}
    }
    public function testProjectLevelSectionAndSaveWorkWithoutAnyWorkPackage():void
    {
        $project=$this->project();$participant=$this->participant();$projects=$this->createMock(ProjectRepository::class);$participants=$this->createMock(ProjectParticipantRepository::class);$wps=$this->createMock(WorkPackageRepository::class);$effort=$this->createMock(AnnualEffortRepository::class);
        $projects->method('findById')->with(1)->willReturn($project);$participants->method('allForProject')->willReturn([$participant]);$wps->method('optionsForProject')->willReturn([]);
        $effort->method('forProjectYear')->willReturn([]);$effort->method('capacityData')->willReturn([]);$effort->method('snapshotToken')->willReturn('token');
        $effort->expects(self::once())->method('save')->with(1,2027,[['participant_id'=>1,'work_package_id'=>null,'month'=>2,'value'=>'12.50'],['participant_id'=>1,'work_package_id'=>null,'month'=>3,'value'=>null]],'token',null)->willReturn(1);
        $service=new AnnualEffortService($projects,$participants,$wps,$effort,new PersonHourAllocationValidator(),new ProjectPolicy(),new DecimalHours());
        $page=$service->page($project,2027,UserFactory::make(),null);
        self::assertCount(1,$page->sections);self::assertSame(0,$page->sections[0]['workPackage']->id);self::assertSame('Project-level effort',$page->sections[0]['workPackage']->title);
        self::assertSame(['changed'=>1],$service->save($project,2027,[0=>[1=>[2=>'12.5',3=>'0']]],'token',UserFactory::make(),null));
    }
    public function testMonthlyProjectTotalsAggregateProjectLevelAndWorkPackagesWithCanonicalMonthKeys():void
    {
        $rows=[
            $this->allocation(null,1,'1.00'),$this->allocation(null,3,'3.00'),
            $this->allocation(1,1,'10.00'),$this->allocation(1,2,'5.00'),$this->allocation(1,3,'2.00'),
            $this->allocation(2,1,'4.00'),$this->allocation(2,2,'8.00'),$this->allocation(2,3,'0.00'),
            $this->allocation(1,9,'1.25'),$this->allocation(2,9,'2.50'),
            $this->allocation(1,10,'0.10'),$this->allocation(2,10,'0.20'),
            $this->allocation(1,12,'1.00'),$this->allocation(2,12,'2.00'),
        ];
        $page=$this->pageWith($rows,[$this->workPackage(1),$this->workPackage(2)]);
        self::assertSame('15.00',$page->projectMonthlyHours[1]);
        self::assertSame('13.00',$page->projectMonthlyHours[2]);
        self::assertSame('5.00',$page->projectMonthlyHours[3]);
        self::assertSame('0.00',$page->projectMonthlyHours[4]);
        self::assertSame('3.75',$page->projectMonthlyHours[9]);
        self::assertSame('0.30',$page->projectMonthlyHours[10]);
        self::assertSame('3.00',$page->projectMonthlyHours[12]);
        self::assertSame('40.05',$page->projectAnnualHours);
    }
    public function testMonthlyTotalsWorkWithOnlyWorkPackagesOrOnlyProjectLevelEffort():void
    {
        $wpOnly=$this->pageWith([$this->allocation(1,1,'2.50'),$this->allocation(2,1,'1.25')],[$this->workPackage(1),$this->workPackage(2)]);
        self::assertSame('3.75',$wpOnly->projectMonthlyHours[1]);self::assertSame('3.75',$wpOnly->projectAnnualHours);
        $projectOnly=$this->pageWith([$this->allocation(null,12,'7.25')],[]);
        self::assertSame('7.25',$projectOnly->projectMonthlyHours[12]);self::assertSame('7.25',$projectOnly->projectAnnualHours);
    }
    /** @param list<PersonHourAllocation> $rows @param list<WorkPackage> $workPackages */
    private function pageWith(array$rows,array$workPackages):\App\Models\AnnualEffortPage
    {
        $projects=$this->createMock(ProjectRepository::class);$participants=$this->createMock(ProjectParticipantRepository::class);$wps=$this->createMock(WorkPackageRepository::class);$effort=$this->createMock(AnnualEffortRepository::class);
        $participants->method('allForProject')->willReturn([$this->participant()]);$wps->method('optionsForProject')->willReturn($workPackages);
        $effort->method('forProjectYear')->willReturn($rows);$effort->method('capacityData')->willReturn([]);$effort->method('snapshotToken')->willReturn('token');
        return(new AnnualEffortService($projects,$participants,$wps,$effort,new PersonHourAllocationValidator(),new ProjectPolicy(),new DecimalHours()))->page($this->project(),2027,UserFactory::make(),null);
    }
    private function workPackage(int$id):WorkPackage{$n=new DateTimeImmutable('2026-01-01');return new WorkPackage($id,1,'WP'.$id,'Work package '.$id,null,null,null,null,true,null,$n,$n,'TEST','Test');}
    private function allocation(?int$wpId,int$month,string$hours):PersonHourAllocation
    {
        $n=new DateTimeImmutable('2026-01-01');return new PersonHourAllocation($month+(($wpId??0)*20),1,2027,$month,$hours,$hours,null,$n,$n,1,1,'Ada Lovelace','researcher','TEST','Test','active','125.00',$wpId,$wpId===null?null:'WP'.$wpId,$wpId===null?null:'Work package '.$wpId,$wpId===null?null:true);
    }
    private function project():Project{$n=new DateTimeImmutable('2026-01-01');return new Project(1,'TEST','Test',null,null,null,null,null,null,1,null,null,'active',null,null,null,null,$n,$n);}
    private function participant():ProjectParticipant{$n=new DateTimeImmutable('2026-01-01');return new ProjectParticipant(1,1,1,'researcher',null,null,true,null,$n,$n,'Ada','Lovelace',null,null,'researcher',true,true,null,null,null,null,'TEST','Test','active');}
}
