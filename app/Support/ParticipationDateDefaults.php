<?php
declare(strict_types=1);
namespace App\Support;

use App\Models\Project;

final class ParticipationDateDefaults
{
    /** @return array{participation_start:string,participation_end:string} */
    public function forProject(Project$p):array{return['participation_start'=>$p->startDate?->format('Y-m-d')??'','participation_end'=>$p->endDate?->format('Y-m-d')??''];}
    /** @return array{participation_start:string,participation_end:string} */
    public function forPerson(Project$p,?string$personFrom,?string$personTo):array
    {
        $start=$p->startDate?->format('Y-m-d');$end=$p->endDate?->format('Y-m-d');
        if($personFrom!==null&&($start===null||$personFrom>$start))$start=$personFrom;
        if($personTo!==null&&($end===null||$personTo<$end))$end=$personTo;
        return['participation_start'=>$start??'','participation_end'=>$end??''];
    }
}
