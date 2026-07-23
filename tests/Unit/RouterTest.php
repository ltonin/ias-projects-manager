<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Http\Request;
use App\Http\Response;
use App\Routing\Router;
use PHPUnit\Framework\TestCase;

final class RouterTest extends TestCase
{
    public function testMatchesStaticRoute(): void
    {
        $router = new Router();
        $router->get('/health', fn (array $parameters): Response => new Response('ok'));
        self::assertSame('ok', $router->dispatch(new Request('GET', '/health'))->body());
    }

    public function testMatchesParameterRoute(): void
    {
        $router = new Router();
        $router->get('/projects/{id}', fn (array $parameters): Response => new Response($parameters['id']));
        self::assertSame('42', $router->dispatch(new Request('GET', '/projects/42'))->body());
    }
}
