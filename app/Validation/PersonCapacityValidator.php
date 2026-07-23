<?php
declare(strict_types=1);
namespace App\Validation;
use App\Models\PersonHourAllocation;
final class PersonCapacityValidator
{
    public function validateOverride(array$i):array
    {
        $e=[];$y=filter_var($i['year']??null,FILTER_VALIDATE_INT);$m=filter_var($i['month']??null,FILTER_VALIDATE_INT);
        if($y===false||$y<PersonHourAllocation::MIN_YEAR||$y>PersonHourAllocation::MAX_YEAR)$e['year']='Year must be between 2000 and 2100.';
        if($m===false||$m<1||$m>12)$e['month']='Select a valid month.';
        $h=trim((string)($i['available_hours']??''));if(preg_match('/^(?:0|[1-9]\\d{0,5})(?:\\.\\d{1,2})?$/',$h)!==1)$e['available_hours']='Available hours must be between 0.00 and 999999.99 with at most two decimal places.';
        if(mb_strlen(trim((string)($i['notes']??'')))>2000)$e['notes']='Notes must not exceed 2,000 characters.';
        return$e;
    }
}
