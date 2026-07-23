<?php
declare(strict_types=1);
namespace App\Repositories;

use App\Database\ConnectionFactory;
use PDO;

final class PdoGlobalAnnualOverviewRepository implements GlobalAnnualOverviewRepository
{
    private ?PDO $pdo=null;
    public function __construct(private readonly ConnectionFactory $connections){}
    public function hierarchy(array $projectIds,int $year):array
    {
        if($projectIds===[])return[];
        $marks=implode(',',array_fill(0,count($projectIds),'?'));
        $sql="SELECT wp.project_id,wp.id work_package_id,wp.code work_package_code,wp.title work_package_title,
            wp.responsible_participant_id,wp.is_active work_package_active,
            pp.id participant_id,pp.person_id,pp.project_role,pp.is_active participant_active,
            CONCAT(pe.first_name,' ',pe.last_name) person_name,
            a.id allocation_id,a.month,a.planned_hours,a.actual_hours
            FROM work_packages wp
            LEFT JOIN project_participants pp ON pp.project_id=wp.project_id
            LEFT JOIN people pe ON pe.id=pp.person_id
            LEFT JOIN person_hour_allocations a ON a.work_package_id=wp.id
                AND a.project_participant_id=pp.id AND a.year=?
            WHERE wp.project_id IN ($marks)
            ORDER BY wp.project_id,wp.code,wp.id,pe.last_name,pe.first_name,pp.id,a.month";
        $statement=$this->connection()->prepare($sql);
        $statement->execute([$year,...$projectIds]);
        return $statement->fetchAll();
    }
    public function warnings(array $projectIds,int $year):array
    {
        if($projectIds===[])return[];
        $marks=implode(',',array_fill(0,count($projectIds),'?'));
        $statement=$this->connection()->prepare("SELECT pp.project_id,
            SUM(a.work_package_id IS NULL) legacy,
            SUM(a.work_package_id IS NOT NULL AND NOT(a.planned_hours <=> a.actual_hours)) divergent
            FROM person_hour_allocations a JOIN project_participants pp ON pp.id=a.project_participant_id
            WHERE a.year=? AND pp.project_id IN ($marks) GROUP BY pp.project_id");
        $statement->execute([$year,...$projectIds]);$out=[];
        foreach($statement->fetchAll()as$row)$out[(int)$row['project_id']]=['legacy'=>(int)$row['legacy'],'divergent'=>(int)$row['divergent']];
        return$out;
    }
    private function connection():PDO{return$this->pdo??=$this->connections->create();}
}
