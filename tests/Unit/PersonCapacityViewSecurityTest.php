<?php
declare(strict_types=1);
namespace Tests\Unit;
use App\Models\EffectiveCapacity;
use App\Models\MonthlyCapacitySummary;
use App\Models\Person;
use App\Support\DecimalHours;
use App\Support\Flash;
use App\Support\UrlGenerator;
use App\Support\View;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
final class PersonCapacityViewSecurityTest extends TestCase
{
    protected function setUp():void{$_SESSION=[];}
    public function testAnnualReadViewContainsNoOverrideNotesAndEscapesPerson():void
    {
        $n=new DateTimeImmutable('2027-01-01');$p=new Person(1,null,'<script>Ada</script>','Lovelace',null,null,'researcher',true,null,null,true,null,$n,$n,null,'125.00');
        $months=[];for($m=1;$m<=12;$m++)$months[]=new MonthlyCapacitySummary(2027,$m,new EffectiveCapacity($m===1?'80.00':'125.00',$m===1?'override':'standard','125.00',$m===1?'80.00':null),'0.00','0.00',$m===1?1:null);
        $html=(new View(dirname(__DIR__,2).'/views',new UrlGenerator('https://example.test'),new Flash()))->render('capacity/show',['title'=>'Capacity','person'=>$p,'year'=>2027,'months'=>$months,'isAdmin'=>false,'decimals'=>new DecimalHours()]);
        self::assertStringNotContainsString('private capacity note',$html);self::assertStringContainsString('&lt;script&gt;Ada&lt;/script&gt;',$html);self::assertStringNotContainsString('<script>Ada</script>',$html);self::assertStringNotContainsString('Edit',$html);
    }
}
