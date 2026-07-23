<?php
declare(strict_types=1);
namespace App\Support;

use App\Models\WorkPackage;

final class NaturalCodeOrder
{
    public static function compare(WorkPackage $left,WorkPackage $right):int
    {
        $natural=strnatcasecmp($left->code,$right->code);
        if($natural!==0)return$natural;
        $exact=strcmp($left->code,$right->code);
        return$exact!==0?$exact:$left->id<=>$right->id;
    }
    /** @param list<WorkPackage> $items @return list<WorkPackage> */
    public static function sort(array$items):array{usort($items,[self::class,'compare']);return$items;}
}
