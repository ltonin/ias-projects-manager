<?php

declare(strict_types=1);

namespace App\Validation;

use App\Models\PersonHourAllocation;
use App\Models\Project;
use App\Models\ProjectParticipant;
use App\Models\WorkPackage;
use DateTimeImmutable;

final class PersonHourAllocationValidator
{
    /** @param array<string,mixed> $input @return array<string,string> */
    public function validate(array $input,Project $project,ProjectParticipant $participant,?WorkPackage $workPackage=null):array
    {
        $errors=[];
        $year=filter_var($input['year']??null,FILTER_VALIDATE_INT);
        $month=filter_var($input['month']??null,FILTER_VALIDATE_INT);
        if($year===false||$year<PersonHourAllocation::MIN_YEAR||$year>PersonHourAllocation::MAX_YEAR)$errors['year']='Year must be between 2000 and 2100.';
        if($month===false||$month<1||$month>12)$errors['month']='Select a valid month.';
        if(array_key_exists('allocated_hours',$input)){
            $value=trim((string)$input['allocated_hours']);
            if($value===''||preg_match('/^(?:0|[1-9]\\d{0,5})(?:\\.\\d{1,2})?$/',$value)!==1)$errors['allocated_hours']='Allocated hours must be a non-negative decimal up to 999999.99 with at most two decimal places.';
        }else{
            foreach(['planned_hours'=>'Planned hours','actual_hours'=>'Actual hours']as$field=>$label){
                $value=trim((string)($input[$field]??''));
                if($value!==''&&preg_match('/^(?:0|[1-9]\\d{0,5})(?:\\.\\d{1,2})?$/',$value)!==1)$errors[$field]=$label.' must be a non-negative decimal up to 999999.99 with at most two decimal places.';
            }
            if(trim((string)($input['planned_hours']??''))===''&&trim((string)($input['actual_hours']??''))==='')$errors['planned_hours']='Enter allocated hours.';
        }
        if(mb_strlen(trim((string)($input['notes']??'')))>2000)$errors['notes']='Notes must not exceed 2,000 characters.';
        if(!isset($errors['year'])&&!isset($errors['month'])){
            $start=new DateTimeImmutable(sprintf('%04d-%02d-01',(int)$year,(int)$month));$end=$start->modify('last day of this month');
            $this->overlap($errors,$start,$end,$project->startDate,$project->endDate,'project');
            $this->overlap($errors,$start,$end,$participant->participationStart,$participant->participationEnd,'participation');
            $this->overlap($errors,$start,$end,$participant->personActiveFrom,$participant->personActiveTo,'person association');
            if($workPackage!==null)$this->overlap($errors,$start,$end,$workPackage->startDate,$workPackage->endDate,'Work Package');
        }
        return$errors;
    }
    /** @param array<string,string> $errors */
    private function overlap(array&$errors,DateTimeImmutable$monthStart,DateTimeImmutable$monthEnd,?DateTimeImmutable$from,?DateTimeImmutable$to,string$label):void
    {
        if(($from!==null&&$monthEnd<$from)||($to!==null&&$monthStart>$to))$errors['month']='The selected month does not overlap the '.$label.' period.';
    }
}
