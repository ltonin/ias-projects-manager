<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Database\ConnectionFactory;
use App\Http\Response;
use App\Services\DatabaseBackupService;
use App\Services\DeploymentMetadataService;
use App\Support\Config;
use App\Support\ConfigLoader;
use PHPUnit\Framework\TestCase;

final class Milestone16OperationsTest extends TestCase
{
    public function testDeploymentMetadataIsReadWithoutUsingFileModificationTime(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'deployment-');
        self::assertIsString($path);
        file_put_contents($path, json_encode([
            'version'=>'16.0.0','build_version'=>'release-42','commit'=>'abcdef1234567890',
            'build_date'=>'2026-07-24T10:00:00+02:00','deployment_date'=>'2026-07-24T11:00:00+02:00',
        ], JSON_THROW_ON_ERROR));
        $metadata = (new DeploymentMetadataService($path, new Config(['app'=>['version'=>'fallback','environment'=>'testing']])))->read();
        unlink($path);

        self::assertSame('16.0.0', $metadata['version']);
        self::assertSame('abcdef123456', $metadata['commit']);
        self::assertSame('2026-07-24T11:00:00+02:00', $metadata['deployment_date']);
    }

    public function testResponseSupportsProgressiveStreaming(): void
    {
        $response = Response::stream(static function (): void { echo 'first'; echo '-second'; }, ['Content-Type'=>'text/plain']);
        ob_start();
        $response->send();
        $output = ob_get_clean();
        self::assertTrue($response->isStreamed());
        self::assertSame('first-second', $output);
    }

    public function testBackupExportsRestorableSchemaAndDataInMultipleChunks(): void
    {
        $root = dirname(__DIR__, 2);
        $pdo = (new ConnectionFactory((new ConfigLoader($root))->load()))->create();
        $chunks = [];
        (new DatabaseBackupService())->export($pdo, static function (string $chunk) use (&$chunks): void { $chunks[] = $chunk; });
        $sql = implode('', $chunks);

        self::assertGreaterThan(10, count($chunks));
        self::assertStringContainsString('SET NAMES utf8mb4;', $sql);
        self::assertStringContainsString('CREATE TABLE `users`', $sql);
        self::assertStringContainsString('CREATE TABLE `projects`', $sql);
        self::assertStringContainsString('AUTO_INCREMENT', $sql);
        self::assertStringContainsString('FOREIGN KEY', $sql);
        self::assertStringContainsString('SET FOREIGN_KEY_CHECKS=1;', $sql);
    }

    public function testSystemPageAndBackupRouteRemainAdministratorOnlyAndCsrfProtected(): void
    {
        $root = dirname(__DIR__, 2);
        $bootstrap = (string) file_get_contents($root.'/bootstrap/app.php');
        $controller = (string) file_get_contents($root.'/app/Controllers/AdminSystemController.php');
        $view = (string) file_get_contents($root.'/views/admin/system.php');

        self::assertStringContainsString("post('/admin/system/backup'", $bootstrap);
        self::assertStringContainsString('$this->authorization->user()', $controller);
        self::assertStringContainsString('$this->csrf->validate($token)', $controller);
        self::assertStringContainsString('Download SQL Backup', $view);
        self::assertStringContainsString('name="_csrf"', $view);
    }
}
