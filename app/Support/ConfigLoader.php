<?php

declare(strict_types=1);

namespace App\Support;

use RuntimeException;

final class ConfigLoader
{
    public function __construct(private readonly string $projectRoot)
    {
    }

    public function load(): Config
    {
        $example = $this->loadFile($this->projectRoot . '/config/config.example.php');
        $localPath = $this->projectRoot . '/config/config.php';
        $local = is_file($localPath) ? $this->loadFile($localPath) : [];
        $environment = $this->environmentOverrides();

        return new Config(array_replace_recursive($example, $local, $environment));
    }

    /** @return array<string, mixed> */
    private function loadFile(string $path): array
    {
        $config = require $path;
        if (!is_array($config)) {
            throw new RuntimeException('Configuration files must return an array.');
        }

        return $config;
    }

    /** @return array<string, mixed> */
    private function environmentOverrides(): array
    {
        $mapping = [
            'APP_ENV' => ['app', 'environment'],
            'APP_DEBUG' => ['app', 'debug'],
            'APP_BASE_URL' => ['app', 'base_url'],
            'APP_BASE_PATH' => ['app', 'base_path'],
            'DB_HOST' => ['database', 'host'],
            'DB_PORT' => ['database', 'port'],
            'DB_NAME' => ['database', 'name'],
            'DB_USER' => ['database', 'user'],
            'DB_PASSWORD' => ['database', 'password'],
        ];
        $overrides = [];

        foreach ($mapping as $name => [$section, $key]) {
            $value = getenv($name);
            if ($value === false) {
                continue;
            }
            if ($name === 'APP_DEBUG') {
                $value = filter_var($value, FILTER_VALIDATE_BOOL);
            } elseif ($name === 'DB_PORT') {
                $value = (int) $value;
            }
            $overrides[$section][$key] = $value;
        }

        return $overrides;
    }
}
