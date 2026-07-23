<?php
declare(strict_types=1);
namespace Tests\Unit;

use App\Services\UserPersonBackfillService;
use PDO;
use PHPUnit\Framework\TestCase;

final class UserPersonBackfillServiceTest extends TestCase
{
    private PDO$pdo;
    protected function setUp():void
    {
        $this->pdo=new PDO('sqlite::memory:');$this->pdo->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
        $this->pdo->exec('CREATE TABLE users(id INTEGER PRIMARY KEY,email TEXT UNIQUE,first_name TEXT,last_name TEXT,is_active INTEGER);
            CREATE TABLE people(id INTEGER PRIMARY KEY AUTOINCREMENT,user_id INTEGER UNIQUE,first_name TEXT,last_name TEXT,institutional_email TEXT UNIQUE,affiliation TEXT,position_type TEXT,is_internal INTEGER,active_from TEXT,active_to TEXT,is_active INTEGER,default_monthly_capacity_hours TEXT,notes TEXT)');
    }
    public function testBackfillMapsDefaultsAndSecondRunIsIdempotent():void
    {
        $this->user(1,'admin@example.test','Admin','User');$service=new UserPersonBackfillService($this->pdo);
        $first=$service->run();$second=$service->run();$row=$this->pdo->query('SELECT * FROM people')->fetch();
        self::assertSame(1,$first['people_created']);self::assertSame(1,$first['links_created']);self::assertSame(0,$first['remaining_unlinked']);
        self::assertSame(0,$second['people_created']);self::assertSame('Admin',$row['first_name']);self::assertSame('admin@example.test',$row['institutional_email']);
        self::assertSame('other',$row['position_type']);self::assertSame(0,(int)$row['is_internal']);self::assertSame(125.0,(float)$row['default_monthly_capacity_hours']);
    }
    public function testAmbiguousEmailIsSkippedAndExistingLinkUnchanged():void
    {
        $this->user(1,'same@example.test','One','User');$this->user(2,'linked@example.test','Two','User');
        $this->pdo->exec("INSERT INTO people(user_id,first_name,last_name,institutional_email,position_type,is_internal,is_active,default_monthly_capacity_hours) VALUES(NULL,'Curated','Person','same@example.test','researcher',1,1,'80.00'),(2,'Linked','Person','linked@example.test','researcher',1,1,'90.00')");
        $r=(new UserPersonBackfillService($this->pdo))->run();
        self::assertSame(1,$r['ambiguous_skipped']);self::assertSame(0,$r['people_created']);self::assertSame(2,(int)$this->pdo->query('SELECT COUNT(*) FROM people')->fetchColumn());
    }
    public function testInducedFailureRollsBackWholeBackfillAndDryRunWritesNothing():void
    {
        $this->user(1,'one@example.test','One','User');$this->user(2,'two@example.test','Two','User');$service=new UserPersonBackfillService($this->pdo);
        $dry=$service->run(true);self::assertSame(2,$dry['unlinked_found']);self::assertSame(0,(int)$this->pdo->query('SELECT COUNT(*) FROM people')->fetchColumn());
        try{$service->run(false,static fn()=>throw new \RuntimeException('induced'));self::fail();}catch(\RuntimeException){}
        self::assertSame(0,(int)$this->pdo->query('SELECT COUNT(*) FROM people')->fetchColumn());
    }
    private function user(int$id,string$email,string$first,string$last):void{$s=$this->pdo->prepare('INSERT INTO users VALUES(?,?,?,?,1)');$s->execute([$id,$email,$first,$last]);}
}
