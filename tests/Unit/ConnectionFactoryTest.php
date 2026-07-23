<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Database\ConnectionFactory;
use App\Support\Config;
use PDO;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class ConnectionFactoryTest extends TestCase
{
    public function testAcceptsDatabaseConfigurationWithoutConnecting(): void
    {
        $config = new Config(['database' => [
            'host' => 'database',
            'port' => 3306,
            'name' => 'test',
            'user' => 'test',
            'password' => 'not-used',
            'charset' => 'utf8mb4',
        ]]);
        $factory = new ConnectionFactory($config);
        self::assertSame(Config::class, (new ReflectionClass($factory))->getProperty('config')->getType()?->getName());
        self::assertSame(PDO::class, (new ReflectionClass($factory))->getMethod('create')->getReturnType()?->getName());
    }
}
