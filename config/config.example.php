<?php

declare(strict_types=1);

return [
    'app' => [
        'name' => 'Research Project Manager',
        'version' => '12.0.0-staging',
        'environment' => 'development',
        'debug' => true,
        'base_url' => 'http://localhost:8080',
        'base_path' => '',
        'clean_urls' => true,
        'timezone' => 'Europe/Rome',
        'session_name' => 'research_project_manager',
        'session_idle_timeout' => 1800,
        'session_absolute_timeout' => 28800,
        'password_min_length' => 12,
        'log_path' => 'storage/logs/application.log',
    ],
    'database' => [
        'host' => 'database',
        'port' => 3306,
        'name' => 'research_project_manager',
        'user' => 'app',
        'password' => '',
        'charset' => 'utf8mb4',
    ],
];
