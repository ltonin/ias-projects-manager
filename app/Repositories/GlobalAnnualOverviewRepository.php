<?php
declare(strict_types=1);
namespace App\Repositories;

interface GlobalAnnualOverviewRepository
{
    /**
     * Loads the complete WP × participant hierarchy and its selected-year allocations in one query.
     * @param list<int> $projectIds
     * @return list<array<string,mixed>>
     */
    public function hierarchy(array $projectIds,int $year):array;
    /** @param list<int> $projectIds @return array<int,array{legacy:int,divergent:int}> */
    public function warnings(array $projectIds,int $year):array;
}
