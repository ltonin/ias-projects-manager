<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\ConfigLoader;
use PHPUnit\Framework\TestCase;

final class ConfigLoaderTest extends TestCase
{
    public function testLoadsExampleConfiguration(): void
    {
        $config = (new ConfigLoader(dirname(__DIR__, 2)))->load();
        self::assertSame('Research Project Manager', $config->get('app.name'));
        self::assertSame('utf8mb4', $config->get('database.charset'));
    }
}
