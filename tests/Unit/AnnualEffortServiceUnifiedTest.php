<?php
declare(strict_types=1);
namespace Tests\Unit;

use App\Auth\ProjectPolicy;
use App\Models\Project;
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
            $participants->method('allForProject')->willReturn([]);$wps->method('optionsForProject')->willReturn([]);$effort->method('classifiedForProjectYear')->willReturn([]);$effort->method('capacityData')->willReturn([]);$effort->method('projectPersonTotals')->willReturn([]);$effort->method('unassignedSummary')->willReturn(['count'=>0,'planned'=>'0.00','actual'=>'0.00']);$effort->method('snapshotToken')->willReturn('token');
            $service=new AnnualEffortService($projects,$participants,$wps,$effort,new PersonHourAllocationValidator(),new ProjectPolicy(),new DecimalHours());
            self::assertSame((int)date('n'),$service->page($project,(int)date('Y'),UserFactory::make(),null)->currentMonth);
            self::assertNull($service->page($project,(int)date('Y')+1,UserFactory::make(),null)->currentMonth);
        }finally{date_default_timezone_set($old);}
    }
    private function project():Project{$n=new DateTimeImmutable('2026-01-01');return new Project(1,'TEST','Test',null,null,null,null,null,null,1,null,null,'active',null,null,null,null,$n,$n);}
}
