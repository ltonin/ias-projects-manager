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
        $this->loadDotEnv($this->projectRoot . '/.env');
        $example = $this->loadFile($this->projectRoot . '/config/config.example.php');
        $localPath = $this->projectRoot . '/config/config.php';
        $local = is_file($localPath) ? $this->loadFile($localPath) : [];
        $environment = $this->environmentOverrides();

        return new Config(array_replace_recursive($example, $local, $environment));
    }

    private function loadDotEnv(string $path): void
    {
        if (!is_file($path) || !is_readable($path)) return;
        $lines = file($path, FILE_IGNORE_NEW_LINES);
        if ($lines === false) throw new RuntimeException('The .env file could not be read.');
        foreach ($lines as $number => $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) continue;
            if (str_starts_with($line, 'export ')) $line = trim(substr($line, 7));
            $separator = strpos($line, '=');
            if ($separator === false) throw new RuntimeException('Invalid .env entry on line ' . ($number + 1) . '.');
            $name = trim(substr($line, 0, $separator));
            if (!preg_match('/^[A-Z][A-Z0-9_]*$/', $name)) throw new RuntimeException('Invalid .env variable name on line ' . ($number + 1) . '.');
            if (getenv($name) !== false) continue;
            $value = trim(substr($line, $separator + 1));
            if ($value !== '' && (($value[0] === '"' && str_ends_with($value, '"')) || ($value[0] === "'" && str_ends_with($value, "'")))) {
                $quote = $value[0];$value = substr($value, 1, -1);
                if ($quote === '"') $value = str_replace(['\\n','\\r','\\t','\\"','\\\\'], ["\n","\r","\t",'"','\\'], $value);
            } elseif (($comment = strpos($value, ' #')) !== false) {
                $value = rtrim(substr($value, 0, $comment));
            }
            putenv($name . '=' . $value);$_ENV[$name] = $value;
        }
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
            'APP_URL' => ['app', 'base_url'],
            'APP_BASE_PATH' => ['app', 'base_path'],
            'APP_CLEAN_URLS' => ['app', 'clean_urls'],
            'APP_TIMEZONE' => ['app', 'timezone'],
            'APP_SESSION_NAME' => ['app', 'session_name'],
            'APP_VERSION' => ['app', 'version'],
            'APP_LOG_PATH' => ['app', 'log_path'],
            'SESSION_IDLE_TIMEOUT' => ['app', 'session_idle_timeout'],
            'SESSION_ABSOLUTE_TIMEOUT' => ['app', 'session_absolute_timeout'],
            'PASSWORD_MIN_LENGTH' => ['app', 'password_min_length'],
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
            if (in_array($name, ['APP_DEBUG','APP_CLEAN_URLS'], true)) {
                $value = filter_var($value, FILTER_VALIDATE_BOOL);
            } elseif (in_array($name, ['DB_PORT', 'SESSION_IDLE_TIMEOUT', 'SESSION_ABSOLUTE_TIMEOUT', 'PASSWORD_MIN_LENGTH'], true)) {
                $value = (int) $value;
            }
            $overrides[$section][$key] = $value;
        }
        if (!isset($overrides['app']['base_url']) && getenv('APP_BASE_URL') !== false) {
            $overrides['app']['base_url'] = (string) getenv('APP_BASE_URL');
        }

        return $overrides;
    }
}
