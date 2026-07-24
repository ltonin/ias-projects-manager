<?php
declare(strict_types=1);
namespace App\Repositories;

use App\Models\Project;

interface ProjectTrashRepository
{
    /** @return list<array<string,mixed>> */
    public function listDeleted():array;
    /** @return array<string,mixed>|null */
    public function summary(int$projectId):?array;
    public function softDelete(Project$project,int$userId,?int$requiredManagerPersonId):void;
    public function restore(Project$project,int$userId):void;
    /** @return array<string,int> */
    public function permanentlyDelete(Project$project,int$userId):array;
}
