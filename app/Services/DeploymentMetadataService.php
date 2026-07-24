<?php

declare(strict_types=1);

namespace App\Services;

use App\Support\Config;
use DateTimeImmutable;

final class DeploymentMetadataService
{
    public function __construct(
        private readonly string $metadataPath,
        private readonly Config $config,
    ) {}

    /** @return array{version:string,build_version:string,commit:string,build_date:?string,deployment_date:?string,environment:string} */
    public function read(): array
    {
        $stored = [];
        if (is_file($this->metadataPath) && is_readable($this->metadataPath)) {
            $decoded = json_decode((string) file_get_contents($this->metadataPath), true);
            if (is_array($decoded)) $stored = $decoded;
        }

        return [
            'version' => $this->text($stored['version'] ?? $this->config->get('app.version', 'unknown')),
            'build_version' => $this->text($stored['build_version'] ?? 'unknown'),
            'commit' => $this->shortCommit($this->text($stored['commit'] ?? 'unknown')),
            'build_date' => $this->date($stored['build_date'] ?? null),
            'deployment_date' => $this->date($stored['deployment_date'] ?? null),
            'environment' => $this->config->requireString('app.environment'),
        ];
    }

    private function text(mixed $value): string
    {
        $value = trim(is_scalar($value) ? (string) $value : '');
        return $value === '' ? 'unknown' : mb_substr($value, 0, 255);
    }

    private function shortCommit(string $commit): string
    {
        return preg_match('/^[a-f0-9]{7,64}$/i', $commit) === 1 ? substr($commit, 0, 12) : $commit;
    }

    private function date(mixed $value): ?string
    {
        if (!is_string($value) || trim($value) === '') return null;
        try {
            return (new DateTimeImmutable($value))->format(DATE_ATOM);
        } catch (\Throwable) {
            return null;
        }
    }
}
