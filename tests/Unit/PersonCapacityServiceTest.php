<?php
declare(strict_types=1);
namespace Tests\Unit;
use App\Services\PersonCapacityService;
use App\Support\DecimalHours;
use App\Validation\PersonCapacityValidator;
use PHPUnit\Framework\TestCase;
use Tests\Fakes\InMemoryPersonCapacityRepository;
use Tests\Fakes\InMemoryPersonRepository;
final class PersonCapacityServiceTest extends TestCase
{
    public function testStandardOverrideRemovalAndStandardChangePrecedence():void
    {
        [$s,$repo,$people,$p]=$this->context();self::assertSame('annual',$s->effectiveCapacity($p,2027,1)->source);self::assertSame('125.00',$s->effectiveCapacity($p,2027,1)->effectiveHours);
        $o=$s->createOverride($p,$this->input(['available_hours'=>'80']));self::assertSame('80.00',$s->effectiveCapacity($p,2027,1)->effectiveHours);
        $changed=$people->update($p->id,$this->personData('150.00'));self::assertSame('80.00',$s->effectiveCapacity($changed,2027,1)->effectiveHours);self::assertSame('125.00',$s->effectiveCapacity($changed,2027,2)->effectiveHours);
        $s->removeOverride($changed,$o);self::assertSame('125.00',$s->effectiveCapacity($changed,2027,1)->effectiveHours);
    }
    public function testAnnualTotalsRemainingStatesWarningsAndPrivacy():void
    {
        [$s,$repo,, $p]=$this->context();$repo->totals[$p->id][2027]=[1=>['planned'=>'100.00','actual'=>'125.00'],2=>['planned'=>'125.00','actual'=>'130.50'],3=>['planned'=>'140.00','actual'=>'150.00']];
        $o=$s->createOverride($p,$this->input(['month'=>'3','available_hours'=>'130','notes'=>'private']));$months=$s->annual($p,2027);$d=new DecimalHours();
        self::assertCount(12,$months);self::assertSame('available',$months[0]->plannedStatus($d));self::assertSame('fully_allocated',$months[0]->actualStatus($d));self::assertSame('overallocated',$months[1]->actualStatus($d));self::assertSame('-10.00',$months[2]->plannedRemaining($d));self::assertSame('Planned allocation exceeds monthly capacity by 10.00 hours.',$months[2]->plannedWarning($d));self::assertNull($repo->listOverridesForPersonAndYear($p->id,2027)[0]->notes);self::assertSame('private',$repo->findOverrideById($o->id)?->notes);
    }
    public function testDuplicateDifferentPeopleAndEditExclusion():void
    {
        [$s,$repo,$people,$p]=$this->context();$first=$s->createOverride($p,$this->input());self::assertArrayHasKey('month',$s->validateOverride($p,$this->input()));self::assertSame([],$s->validateOverride($p,$this->input(),$first->id));
        $other=$people->create($this->personData('125.00','Other'));self::assertNotNull($s->createOverride($other,$this->input()));
    }
    private function context():array{$people=new InMemoryPersonRepository();$p=$people->create($this->personData());$repo=new InMemoryPersonCapacityRepository();return[new PersonCapacityService($repo,$people,new PersonCapacityValidator(),new DecimalHours()),$repo,$people,$p];}
    private function input(array$o=[]):array{return$o+['year'=>'2027','month'=>'1','available_hours'=>'125.00','notes'=>''];}
    private function personData(string$c='125.00',string$name='Capacity'):array{return['user_id'=>null,'first_name'=>$name,'last_name'=>'Person','institutional_email'=>strtolower($name).'@example.test','affiliation'=>null,'position_type'=>'researcher','is_internal'=>true,'active_from'=>null,'active_to'=>null,'is_active'=>true,'default_monthly_capacity_hours'=>$c,'annual_capacity_hours'=>'1500.00','notes'=>null];}
}
