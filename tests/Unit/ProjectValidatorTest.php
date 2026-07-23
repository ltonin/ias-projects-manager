<?php
declare(strict_types=1);
namespace Tests\Unit;
use App\Models\Project;
use App\Validation\ProjectValidator;
use PHPUnit\Framework\TestCase;
final class ProjectValidatorTest extends TestCase
{
    public function testValidProjectStatusesBudgetCurrencyAndUrls():void{$v=new ProjectValidator();foreach(Project::STATUS_LABELS as $s=>$l)self::assertSame([],$v->validate($this->valid(['status'=>$s])));self::assertSame([],$v->validate($this->valid(['total_budget'=>'0.00','currency'=>'eur','website_url'=>'https://example.test'])));self::assertSame([],$v->validate($this->valid(['website_url'=>'http://example.test'])));}
    public function testRequiredDatesBudgetCurrencyUrlsAndLengths():void{$v=new ProjectValidator();foreach([
        ['acronym'=>''],['title'=>''],['status'=>'wrong'],['start_date'=>'bad'],['start_date'=>'2026-02-02','end_date'=>'2026-02-01'],
        ['total_budget'=>'-1','currency'=>'EUR'],['total_budget'=>'1.234','currency'=>'EUR'],['total_budget'=>'1','currency'=>''],['total_budget'=>'','currency'=>'EUR'],
        ['currency'=>'EU','total_budget'=>'1'],['website_url'=>'javascript:alert(1)'],['description'=>str_repeat('d',5001)],['notes'=>str_repeat('n',5001)]
    ]as$bad)self::assertNotSame([],$v->validate($this->valid($bad)));}
    /** @param array<string,string> $o @return array<string,string> */private function valid(array $o=[]):array{return$o+['acronym'=>'TEST','title'=>'Test project','description'=>'','internal_code'=>'','grant_agreement_number'=>'','funding_agency'=>'','funding_programme'=>'','coordinator_organization'=>'','manager_person_id'=>'','start_date'=>'','end_date'=>'','status'=>'planned','total_budget'=>'','currency'=>'','website_url'=>'','notes'=>''];}
}
