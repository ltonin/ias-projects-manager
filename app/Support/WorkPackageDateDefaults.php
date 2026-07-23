<?php
declare(strict_types=1);
namespace App\Support;

use App\Models\Project;

final class WorkPackageDateDefaults
{
    /** @return array{start_date:string,end_date:string} */
    public function forProject(Project$p):array{return['start_date'=>$p->startDate?->format('Y-m-d')??'','end_date'=>$p->endDate?->format('Y-m-d')??''];}
}
