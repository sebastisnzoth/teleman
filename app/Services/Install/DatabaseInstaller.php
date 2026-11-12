<?php

declare(strict_types=1);

namespace App\Services\Install;

use App\Support\EnvWriter;
use App\Support\Installation;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Database\Migrations\Migrator;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use PDO;
use RuntimeException;

/**
 * Sets up the database in time-budgeted, resumable steps.
 *
 * A full migrate + seed takes ~20 s on a fast local disk and minutes on
 * shared hosting, while a single web request is typically killed after
 * 30–60 s. So instead of one long request, the browser calls step() over and
 * over; each call does as much as fits in its budget and reports progress.
 * Migrations are tracked by the migrations table itself, seeders by a small
 * progress file, so a run that is cut off — timeout, dropped connection,
 * closed tab — simply continues on the next call.
 *
 * Nothing here caches config or rebuilds the container mid-request: the new
 * connection is applied to the running application, and .env is updated
 * for the requests that follow.
 */
class DatabaseInstaller
{
    public const DRIVERS = ['mysql', 'mariadb', 'sqlite'];

    public function __construct(private Migrator $migrator) {}

    /**
     * Verify the credentials, persist them to .env and switch the running
     * application to the new connection.
     *
     * @param  array{driver:string,host?:string,port?:int|string,database:string,username?:string,password?:string}  $credentials
     */
    public function configure(array $credentials): void
    {
        $driver = $credentials['driver'] ?? 'mysql';

        if (! in_array($driver, self::DRIVERS, true)) {
            throw new RuntimeException("Unsupported database driver [{$driver}].");
        }

        $resolvedHost = $this->probe($credentials);

        if ($resolvedHost !== '') {
            $credentials['host'] = $resolvedHost;
        }

        $connection = $this->connectionConfig($credentials);

        EnvWriter::make()->set([
            'DB_CONNECTION' => $driver,
            'DB_HOST' => $connection['host'] ?? '',
            'DB_PORT' => $connection['port'] ?? '',
            'DB_DATABASE' => $connection['database'],
            'DB_USERNAME' => $connection['username'] ?? '',
            'DB_PASSWORD' => $connection['password'] ?? '',
        ]);

        // A cached config would keep pointing at the old database.
        File::delete(base_path('bootstrap/cache/config.php'));

        config([
            "database.connections.{$driver}" => $connection,
            'database.default' => $driver,
        ]);
        DB::purge($driver);
        DB::setDefaultConnection($driver);

        // A different target means a fresh seeding run.
        if ($this->progress()['target'] !== $this->targetId($connection)) {
            $this->writeProgress(['target' => $this->targetId($connection), 'seeders' => [], 'finished' => false]);
        }
    }

    /**
     * Open a raw PDO connection with the given credentials and return the
     * host that answered, throwing a human-readable error when none does.
     *
     * "localhost" means the Unix socket to PHP's MySQL driver while
     * "127.0.0.1" means TCP, and which one a host actually serves varies.
     * A non-technical buyer cannot be expected to know, so the loopback
     * twin is tried before giving up.
     *
     * @param  array<string, mixed>  $credentials
     */
    public function probe(array $credentials): string
    {
        $driver = $credentials['driver'] ?? 'mysql';

        if ($driver === 'sqlite') {
            $path = (string) ($credentials['database'] ?? '');
            if ($path !== ':memory:' && ! is_dir(dirname($path))) {
                throw new RuntimeException("Folder for SQLite file does not exist: {$path}");
            }

            try {
                new PDO('sqlite:'.$path);
            } catch (\PDOException $e) {
                throw new RuntimeException('Database connection failed: '.$e->getMessage(), 0, $e);
            }

            return '';
        }

        $host = trim((string) ($credentials['host'] ?? '127.0.0.1'));
        $candidates = match ($host) {
            'localhost' => ['localhost', '127.0.0.1'],
            '127.0.0.1' => ['127.0.0.1', 'localhost'],
            default => [$host],
        };

        $failures = [];

        foreach ($candidates as $candidate) {
            try {
                $dsn = sprintf(
                    'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
                    $candidate,
                    (int) ($credentials['port'] ?? 3306),
                    $credentials['database'] ?? ''
                );

                new PDO($dsn, (string) ($credentials['username'] ?? ''), (string) ($credentials['password'] ?? ''), [
                    PDO::ATTR_TIMEOUT => 5,
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                ]);

                return $candidate;
            } catch (\PDOException $e) {
                $failures[] = "{$candidate}: ".$e->getMessage();
            }
        }

        throw new RuntimeException('Database connection failed ('.implode('; ', $failures).')');
    }

    /**
     * Has configure() been run for the current target?
     */
    public function isConfigured(): bool
    {
        return $this->progress()['target'] !== null;
    }

    /**
     * What is currently in the target database.
     *
     * @return array{has_migrations_table: bool, table_count: int}
     */
    public function inspect(): array
    {
        $tables = $this->tableNames();

        return [
            'has_migrations_table' => in_array('migrations', $tables, true),
            'table_count' => count($tables),
        ];
    }

    /**
     * Drop everything in the target database. Only ever called after the
     * user explicitly asked for it.
     */
    public function wipe(): void
    {
        Schema::dropAllViews();
        Schema::dropAllTables();
        $this->writeProgress(['target' => $this->progress()['target'], 'seeders' => [], 'finished' => false]);
    }

    /**
     * Where the installation currently stands, without doing any work.
     *
     * @return array{stage: string, completed: int, total: int, current: ?string, done: bool, reason?: string}
     */
    public function status(): array
    {
        $progress = $this->progress();

        if ($progress['finished']) {
            return $this->report('done', 0, 0, null, true);
        }

        $pending = $this->pendingMigrations();
        $total = count($this->migrationFiles());

        if ($pending !== []) {
            return $this->report('migrate', $total - count($pending), $total, null, false);
        }

        $seeders = DatabaseSeeder::SEEDERS;
        $done = array_values(array_intersect($seeders, $progress['seeders']));

        if (count($done) < count($seeders)) {
            return $this->report('seed', count($done), count($seeders), null, false);
        }

        return $this->report('finalize', 0, 1, null, false);
    }

    /**
     * Do as much as fits in $budgetSeconds, then report. Always makes at
     * least one unit of progress so a tiny budget cannot stall forever.
     */
    public function step(float $budgetSeconds): array
    {
        $deadline = microtime(true) + max(0.0, $budgetSeconds);

        // The host's PHP limit is the real ceiling; ask for headroom.
        @set_time_limit((int) max(60, $budgetSeconds + 60));

        $status = $this->status();

        if ($status['done']) {
            return $status;
        }

        if (! $this->migrator->repositoryExists()) {
            $inspection = $this->inspect();

            if ($inspection['table_count'] > 0) {
                // Someone else's tables. Refuse rather than migrate on top of
                // them; the user can ask for a wipe explicitly.
                return $this->report('blocked', 0, 0, null, false, 'not_empty');
            }

            $this->migrator->getRepository()->createRepository();
        }

        // Stage 1: migrations, one file at a time until the budget is spent.
        $files = $this->migrationFiles();
        $current = null;

        foreach ($this->pendingMigrations() as $name) {
            $current = $name;
            $this->migrator->runPending([$files[$name]], []);

            if (microtime(true) >= $deadline) {
                break;
            }
        }

        if ($this->pendingMigrations() !== []) {
            return $this->status() + ['current' => $current];
        }

        // Stage 2: seeders, each atomic, recorded only once it committed.
        $progress = $this->progress();

        foreach (DatabaseSeeder::SEEDERS as $seeder) {
            if (in_array($seeder, $progress['seeders'], true)) {
                continue;
            }

            if ($current !== null && microtime(true) >= $deadline) {
                return $this->status() + ['current' => $current];
            }

            $current = class_basename($seeder);
            $this->runSeeder($seeder);

            $progress['seeders'][] = $seeder;
            $this->writeProgress($progress);
        }

        // Stage 3: finishing touches that must never fail the install.
        $this->finalize();

        $progress['finished'] = true;
        $this->writeProgress($progress);

        return $this->report('done', 0, 0, null, true);
    }

    private function runSeeder(string $seeder): void
    {
        // A transaction makes each seeder all-or-nothing, so an interrupted
        // one is re-run from scratch instead of leaving half its rows behind.
        DB::transaction(function () use ($seeder): void {
            $exitCode = Artisan::call('db:seed', ['--class' => $seeder, '--force' => true, '--no-interaction' => true]);

            if ($exitCode !== 0) {
                throw new RuntimeException(class_basename($seeder).' failed: '.trim(Artisan::output()));
            }
        });
    }

    private function finalize(): void
    {
        // symlink() needs elevated rights on Windows and is forbidden on some
        // hosts. Uploads simply fall back to the private disk; do not fail
        // the whole installation over it.
        rescue(function (): void {
            if (! file_exists(public_path('storage'))) {
                Artisan::call('storage:link', ['--force' => true]);
            }
        }, null, false);
    }

    /**
     * @return array<string, string> name => path
     */
    private function migrationFiles(): array
    {
        return $this->migrator->getMigrationFiles([database_path('migrations')]);
    }

    /**
     * @return list<string>
     */
    private function pendingMigrations(): array
    {
        $ran = $this->migrator->repositoryExists()
            ? $this->migrator->getRepository()->getRan()
            : [];

        return array_values(array_diff(array_keys($this->migrationFiles()), $ran));
    }

    /**
     * @return list<string>
     */
    private function tableNames(): array
    {
        // Ask for the connection's own schema explicitly. With no schema
        // given, a privileged MySQL user is shown every database on the
        // server, and an empty database looked "full" (1,098 tables).
        $schema = Schema::getCurrentSchemaName();

        return array_values(array_filter(
            Schema::getTableListing($schema, false),
            fn (string $name): bool => ! str_starts_with($name, 'sqlite_')
        ));
    }

    /**
     * @param  array<string, mixed>  $credentials
     * @return array<string, mixed>
     */
    private function connectionConfig(array $credentials): array
    {
        if (($credentials['driver'] ?? 'mysql') === 'sqlite') {
            return [
                'driver' => 'sqlite',
                'database' => (string) $credentials['database'],
                'prefix' => '',
                'foreign_key_constraints' => true,
            ];
        }

        return [
            'driver' => $credentials['driver'],
            'host' => (string) ($credentials['host'] ?? '127.0.0.1'),
            'port' => (string) ($credentials['port'] ?? '3306'),
            'database' => (string) $credentials['database'],
            'username' => (string) ($credentials['username'] ?? ''),
            'password' => (string) ($credentials['password'] ?? ''),
            'unix_socket' => '',
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'prefix_indexes' => true,
            'strict' => true,
            'engine' => null,
            'options' => [],
        ];
    }

    /**
     * @param  array<string, mixed>  $connection
     */
    private function targetId(array $connection): string
    {
        return $connection['driver'].':'.($connection['host'] ?? '').':'.$connection['database'];
    }

    /**
     * @return array{target: ?string, seeders: list<string>, finished: bool}
     */
    private function progress(): array
    {
        $path = Installation::progressPath();
        $data = is_file($path) ? json_decode((string) file_get_contents($path), true) : null;

        return [
            'target' => is_array($data) ? ($data['target'] ?? null) : null,
            'seeders' => is_array($data) && is_array($data['seeders'] ?? null) ? array_values($data['seeders']) : [],
            'finished' => is_array($data) && ($data['finished'] ?? false) === true,
        ];
    }

    /**
     * @param  array{target: ?string, seeders: list<string>, finished: bool}  $progress
     */
    private function writeProgress(array $progress): void
    {
        File::ensureDirectoryExists(dirname(Installation::progressPath()));
        File::put(Installation::progressPath(), json_encode($progress, JSON_PRETTY_PRINT));
    }

    /**
     * @return array{stage: string, completed: int, total: int, current: ?string, done: bool, reason?: string}
     */
    private function report(string $stage, int $completed, int $total, ?string $current, bool $done, ?string $reason = null): array
    {
        $report = compact('stage', 'completed', 'total', 'current', 'done');

        if ($reason !== null) {
            $report['reason'] = $reason;
        }

        return $report;
    }
}
