<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$options = getopt('', ['version:', 'build-version:', 'commit:', 'build-date::']);
foreach (['version', 'build-version', 'commit'] as $required) {
    if (!isset($options[$required]) || trim((string) $options[$required]) === '') {
        fwrite(STDERR, "Missing --{$required}\n");
        exit(2);
    }
}
$now = new DateTimeImmutable();
$metadata = [
    'version' => (string) $options['version'],
    'build_version' => (string) $options['build-version'],
    'commit' => (string) $options['commit'],
    'build_date' => isset($options['build-date']) && $options['build-date'] !== false ? (string) $options['build-date'] : $now->format(DATE_ATOM),
    'deployment_date' => $now->format(DATE_ATOM),
];
$json = json_encode($metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
$path = $root . '/storage/deployment.json';
if (file_put_contents($path, $json, LOCK_EX) === false) {
    fwrite(STDERR, "Unable to write {$path}\n");
    exit(1);
}
fwrite(STDOUT, "Deployment metadata written to storage/deployment.json\n");
