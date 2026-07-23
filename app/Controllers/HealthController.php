<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Http\Response;
use App\Services\HealthService;

final class HealthController
{
    public function __construct(private readonly HealthService $health)
    {
    }

    public function show(): Response
    {
        $status = $this->health->check();
        return Response::json($status, $status['database'] === 'available' ? 200 : 503);
    }
}
