<?php
declare(strict_types=1);
namespace Tests\Unit;

use App\Models\User;
use App\Repositories\GlobalAnnualOverviewRepository;
use App\Services\GlobalAnnualOverviewService;
use App\Support\DecimalHours;
use PHPUnit\Framework\TestCase;
use Tests\Fakes\InMemoryProjectRepository;
use Tests\Support\UserFactory;

final class GlobalOverviewYearTest extends TestCase
{
    public function testSelectedYearChangesProjectSetAtInclusiveBoundaries():void
    {
        $projects=new InMemoryProjectRepository();
        foreach([
            ['A','2024-06-01','2026-03-31'],['B','2026-01-01','2026-12-31'],['C','2025-09-01',null],
            ['JAN1','2024-01-01','2025-01-01'],['DEC31','2026-12-31','2027-01-01'],['OPEN',null,null],
        ]as[$code,$start,$end])$projects->create($this->data($code,$start,$end));
        $overview=new class implements GlobalAnnualOverviewRepository{
            public array$years=[];public function hierarchy(array$ids,int$year):array{$this->years[]=$year;return[];}
            public function warnings(array$ids,int$year):array{$this->years[]=$year;return[];}
        };
        $service=new GlobalAnnualOverviewService($projects,$overview,new DecimalHours());$user=UserFactory::make(role:User::ROLE_VIEWER);
        self::assertSame(['A','JAN1','OPEN'],array_column(array_column($service->page($user,null,2024)->projects,'project'),'acronym'));
        self::assertSame(['A','C','JAN1','OPEN'],array_column(array_column($service->page($user,null,2025)->projects,'project'),'acronym'));
        self::assertSame(['A','B','C','DEC31','OPEN'],array_column(array_column($service->page($user,null,2026)->projects,'project'),'acronym'));
        self::assertSame(['C','DEC31','OPEN'],array_column(array_column($service->page($user,null,2027)->projects,'project'),'acronym'));
        self::assertSame([2024,2024,2025,2025,2026,2026,2027,2027],$overview->years);
    }
    private function data(string$code,?string$start,?string$end):array{return['acronym'=>$code,'title'=>$code,'description'=>null,'internal_code'=>null,'grant_agreement_number'=>null,'funding_agency'=>null,'funding_programme'=>null,'coordinator_organization'=>null,'manager_person_id'=>null,'start_date'=>$start,'end_date'=>$end,'status'=>'completed','total_budget'=>null,'currency'=>null,'hours_per_pm'=>'125.00','website_url'=>null,'notes'=>null];}
}
