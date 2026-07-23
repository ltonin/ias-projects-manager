<?php
declare(strict_types=1);
namespace App\Validation;

use App\Models\Project;
use DateTimeImmutable;

final class WorkPackageValidator
{
    /** @param array<string,mixed> $input @return array<string,string> */
    public function validate(array$input,Project$project):array
    {
        $e=[];$code=trim((string)($input['code']??''));$title=trim((string)($input['title']??''));
        if($code==='')$e['code']='Work Package code is required.';elseif(mb_strlen($code)>50)$e['code']='Code must not exceed 50 characters.';
        if($title==='')$e['title']='Work Package title is required.';elseif(mb_strlen($title)>255)$e['title']='Title must not exceed 255 characters.';
        if(mb_strlen(trim((string)($input['notes']??'')))>2000)$e['notes']='Notes must not exceed 2,000 characters.';
        $start=$this->date($input['start_date']??null,'start_date',$e);$end=$this->date($input['end_date']??null,'end_date',$e);
        if($start&&$end&&$start>$end)$e['end_date']='Work Package end must not precede its start.';
        if($start&&$project->startDate&&$start<$project->startDate)$e['start_date']='Work Package start must not precede the project start date.';
        if($end&&$project->endDate&&$end>$project->endDate)$e['end_date']='Work Package end must not follow the project end date.';
        if($end&&$project->startDate&&$end<$project->startDate)$e['end_date']='Work Package end must not precede the project start date.';
        if($start&&$project->endDate&&$start>$project->endDate)$e['start_date']='Work Package start must not follow the project end date.';
        $rp=trim((string)($input['responsible_participant_id']??''));if($rp!==''&&filter_var($rp,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]])===false)$e['responsible_participant_id']='Select a valid project participant.';
        return$e;
    }
    /** @param array<string,string> $e */
    private function date(mixed$v,string$f,array&$e):?DateTimeImmutable{$v=trim((string)$v);if($v==='')return null;$d=DateTimeImmutable::createFromFormat('!Y-m-d',$v);if($d===false||$d->format('Y-m-d')!==$v){$e[$f]='Enter a valid date.';return null;}return$d;}
}
