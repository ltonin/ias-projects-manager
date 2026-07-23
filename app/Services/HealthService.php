<?php

declare(strict_types=1);

namespace App\Services;

use App\Database\ConnectionFactory;
use Throwable;

final class HealthService
{
    public function __construct(private readonly ConnectionFactory $connections)
    {
    }

    /** @return array{application: string, database: string} */
    public function check(): array
    {
        try {
            $statement = $this->connections->create()->query('SELECT 1');
            $available = $statement !== false && $statement->fetchColumn() !== false;
        } catch (Throwable) {
            $available = false;
        }

        return ['application' => 'ok', 'database' => $available ? 'available' : 'unavailable'];
    }
}
