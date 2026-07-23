<?php
declare(strict_types=1);
namespace Tests\Support;
use App\Models\Person;
use DateTimeImmutable;
final class PersonFactory
{
    public static function make(int $id=1,?int $userId=1,bool $active=true):Person{$n=new DateTimeImmutable('2026-01-01');return new Person($id,$userId,'Test','Manager','manager@example.test','University','researcher',true,null,null,$active,null,$n,$n,'manager.user');}
}
