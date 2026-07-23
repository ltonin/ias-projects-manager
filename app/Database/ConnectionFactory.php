<?php

declare(strict_types=1);

namespace App\Database;

use App\Support\Config;
use PDO;
use PDOException;
use RuntimeException;

final class ConnectionFactory
{
    public function __construct(private readonly Config $config)
    {
    }

    public function create(): PDO
    {
        $host = $this->config->requireString('database.host');
        $port = (int) $this->config->get('database.port', 3306);
        $name = $this->config->requireString('database.name');
        $charset = (string) $this->config->get('database.charset', 'utf8mb4');
        $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', $host, $port, $name, $charset);

        try {
            return new PDO($dsn, $this->config->requireString('database.user'), (string) $this->config->get('database.password', ''), [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        } catch (PDOException $exception) {
            throw new RuntimeException('Database connection is unavailable.', 0, $exception);
        }
    }
}
