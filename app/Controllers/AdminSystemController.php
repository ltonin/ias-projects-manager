<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Auth\Authorization;
use App\Auth\Csrf;
use App\Database\ConnectionFactory;
use App\Exceptions\AuthorizationException;
use App\Exceptions\HttpException;
use App\Http\Request;
use App\Http\Response;
use App\Services\DatabaseBackupService;
use App\Services\DeploymentMetadataService;
use App\Support\Config;
use App\Support\View;
use DateTimeImmutable;
use PDO;
use Throwable;

final class AdminSystemController
{
    public function __construct(
        private readonly Request $request,
        private readonly View $view,
        private readonly Authorization $authorization,
        private readonly ConnectionFactory $connections,
        private readonly Config $config,
        private readonly DeploymentMetadataService $deployment,
        private readonly DatabaseBackupService $backups,
        private readonly Csrf $csrf,
        private readonly string $projectRoot,
        private readonly string $logDirectory,
    ) {}

    public function show(): Response
    {
        $this->admin();
        $expected = $this->expectedMigrations();
        $database = ['connected' => false, 'message' => 'Unavailable', 'version' => 'Unavailable', 'size' => null];
        $statistics = ['projects'=>null,'workPackages'=>null,'people'=>null,'users'=>null,'allocations'=>null,'activeProjects'=>null,'archivedProjects'=>null,'deletedProjects'=>null];
        $diagnostics = $this->emptyDiagnostics($expected);
        try {
            $pdo = $this->connections->create();
            $pdo->query('SELECT 1');
            $database = [
                'connected' => true,
                'message' => 'Connected',
                'version' => (string) $pdo->query('SELECT VERSION()')->fetchColumn(),
                'size' => $this->databaseSize($pdo),
            ];
            $statistics = $this->statistics($pdo);
            $diagnostics = $this->diagnostics($pdo, $expected);
        } catch (Throwable $exception) {
            error_log($exception->__toString());
        }

        $metadata = $this->deployment->read();
        $writable = $this->writableDirectories();
        return new Response($this->view->render('admin/system', [
            'title' => 'System',
            'metadata' => $metadata,
            'applicationUrl' => $this->config->requireString('app.base_url'),
            'basePath' => (string) $this->config->get('app.base_path', ''),
            'runtime' => [
                'phpVersion' => PHP_VERSION,
                'webServer' => (string) ($_SERVER['SERVER_SOFTWARE'] ?? PHP_SAPI),
                'timezone' => date_default_timezone_get(),
                'serverTime' => (new DateTimeImmutable())->format(DATE_ATOM),
                'uptime' => $this->uptime($metadata['deployment_date']),
                'sessionPath' => (string) (session_save_path() ?: sys_get_temp_dir()),
                'logDirectory' => $this->relative($this->logDirectory),
            ],
            'database' => $database,
            'statistics' => $statistics,
            'diagnostics' => $diagnostics,
            'writable' => $writable,
            'csrfToken' => $this->csrf->token(),
        ]));
    }

    public function backup(): Response
    {
        $user = $this->admin();
        $token = $this->request->post('_csrf');
        if (!is_string($token) || !$this->csrf->validate($token)) throw new HttpException(403, 'Invalid CSRF token.');
        $filename = 'iaslab-projects-' . date('Y-m-d-Hi') . '.sql';
        $connections = $this->connections;
        $backups = $this->backups;
        $actor = $user->id;
        error_log(json_encode(['event'=>'database_backup_requested','user_id'=>$actor,'time'=>date(DATE_ATOM)], JSON_UNESCAPED_SLASHES));

        return Response::stream(static function () use ($connections, $backups, $actor): void {
            $backups->export($connections->create(), static function (string $chunk): void {
                echo $chunk;
                if (ob_get_level() > 0) @ob_flush();
                flush();
            });
            error_log(json_encode(['event'=>'database_backup_completed','user_id'=>$actor,'time'=>date(DATE_ATOM)], JSON_UNESCAPED_SLASHES));
        }, [
            'Content-Type' => 'application/sql; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Cache-Control' => 'no-store, private',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function admin(): \App\Models\User
    {
        $user = $this->authorization->user();
        if (!$user->isAdmin()) throw new AuthorizationException('System operations are restricted to administrators.');
        return $user;
    }

    /** @return array<string,int> */
    private function statistics(PDO $pdo): array
    {
        $scalar = static fn(string $sql): int => (int) $pdo->query($sql)->fetchColumn();
        return [
            'projects' => $scalar('SELECT COUNT(*) FROM projects WHERE deleted_at IS NULL'),
            'workPackages' => $scalar('SELECT COUNT(*) FROM work_packages'),
            'people' => $scalar('SELECT COUNT(*) FROM people'),
            'users' => $scalar('SELECT COUNT(*) FROM users'),
            'allocations' => $scalar('SELECT COUNT(*) FROM person_hour_allocations'),
            'activeProjects' => $scalar("SELECT COUNT(*) FROM projects WHERE status='active' AND deleted_at IS NULL"),
            'archivedProjects' => $scalar("SELECT COUNT(*) FROM projects WHERE status IN ('completed','archived') AND deleted_at IS NULL"),
            'deletedProjects' => $scalar('SELECT COUNT(*) FROM projects WHERE deleted_at IS NOT NULL'),
        ];
    }

    /** @param list<string> $expected @return array<string,mixed> */
    private function diagnostics(PDO $pdo, array $expected): array
    {
        $scalar = static fn(string $sql): int => (int) $pdo->query($sql)->fetchColumn();
        $applied = $this->appliedMigrations($pdo);
        return [
            'missingLinkedPerson' => $scalar('SELECT COUNT(*) FROM users u LEFT JOIN people p ON p.user_id=u.id WHERE p.id IS NULL'),
            'duplicateUsernames' => $scalar('SELECT COUNT(*) FROM (SELECT LOWER(username) FROM users GROUP BY LOWER(username) HAVING COUNT(*)>1) duplicates'),
            'duplicateEmails' => $scalar('SELECT COUNT(*) FROM (SELECT LOWER(email) FROM users GROUP BY LOWER(email) HAVING COUNT(*)>1) duplicates'),
            'orphanAllocations' => $scalar('SELECT COUNT(*) FROM person_hour_allocations a LEFT JOIN project_participants pp ON pp.id=a.project_participant_id WHERE pp.id IS NULL'),
            'missingWorkPackages' => $scalar('SELECT COUNT(*) FROM projects p LEFT JOIN work_packages wp ON wp.project_id=p.id WHERE p.deleted_at IS NULL AND wp.id IS NULL'),
            'missingParticipants' => $scalar('SELECT COUNT(*) FROM projects p LEFT JOIN project_participants pp ON pp.project_id=p.id WHERE p.deleted_at IS NULL AND pp.id IS NULL'),
            'invalidAnnualCapacity' => $scalar('SELECT COUNT(*) FROM people WHERE annual_capacity_hours IS NULL OR annual_capacity_hours<0 OR annual_capacity_hours>999999.99'),
            'migrations' => [
                'applied' => $applied,
                'expected' => $expected,
                'pending' => array_values(array_diff($expected, $applied)),
                'failed' => array_values(array_diff($applied, $expected)),
            ],
        ];
    }

    /** @param list<string> $expected @return array<string,mixed> */
    private function emptyDiagnostics(array $expected): array
    {
        return [
            'missingLinkedPerson'=>null,'duplicateUsernames'=>null,'duplicateEmails'=>null,'orphanAllocations'=>null,
            'missingWorkPackages'=>null,'missingParticipants'=>null,'invalidAnnualCapacity'=>null,
            'migrations'=>['applied'=>[],'expected'=>$expected,'pending'=>$expected,'failed'=>[]],
        ];
    }

    private function databaseSize(PDO $pdo): string
    {
        $statement = $pdo->query("SELECT COALESCE(SUM(DATA_LENGTH+INDEX_LENGTH),0) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE()");
        $bytes = (int) $statement->fetchColumn();
        return $bytes < 1048576 ? number_format($bytes / 1024, 1) . ' KiB' : number_format($bytes / 1048576, 1) . ' MiB';
    }

    /** @return list<array{label:string,path:string,writable:bool}> */
    private function writableDirectories(): array
    {
        $paths = [
            ['Log directory', $this->logDirectory],
            ['Session directory', (string) (session_save_path() ?: sys_get_temp_dir())],
            ['Storage directory', $this->projectRoot . '/storage'],
        ];
        return array_map(fn(array $item): array => ['label'=>$item[0],'path'=>$this->relative($item[1]),'writable'=>is_dir($item[1])&&is_writable($item[1])], $paths);
    }

    /** @return list<string> */
    private function expectedMigrations(): array
    {
        $files = glob($this->projectRoot . '/database/migrations/*.sql') ?: [];
        $versions = [];
        foreach ($files as $file) if (preg_match('/^(\d+)_/', basename($file), $matches)) $versions[] = $matches[1];
        sort($versions, SORT_STRING);
        return $versions;
    }

    /** @return list<string> */
    private function appliedMigrations(PDO $pdo): array
    {
        return array_map('strval', $pdo->query('SELECT version FROM schema_versions ORDER BY version')->fetchAll(PDO::FETCH_COLUMN));
    }

    private function uptime(?string $deploymentDate): string
    {
        if ($deploymentDate === null) return 'Unknown (deployment metadata unavailable)';
        try {
            $seconds = max(0, time() - (new DateTimeImmutable($deploymentDate))->getTimestamp());
            return sprintf('%d days, %d hours', intdiv($seconds, 86400), intdiv($seconds % 86400, 3600));
        } catch (Throwable) {
            return 'Unknown';
        }
    }

    private function relative(string $path): string
    {
        return str_starts_with($path, $this->projectRoot . '/') ? substr($path, strlen($this->projectRoot) + 1) : $path;
    }
}
