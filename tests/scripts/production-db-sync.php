<?php

declare(strict_types=1);

/**
 * Standalone integration checks: php tests/scripts/production-db-sync.php
 * All projects, credentials, SQL, and client commands below are disposable fakes.
 * The repository's .env and real SSH/database clients are never used.
 */

umask(0077);

function check(bool $condition, string $message): void
{
    if (! $condition) {
        throw new RuntimeException($message);
    }
}

function writeFixture(string $path, string $contents): void
{
    if (! is_dir(dirname($path))) {
        mkdir(dirname($path), 0700, true);
    }
    check(file_put_contents($path, $contents) !== false, 'Cannot create fixture: ' . $path);
}

function removeFixture(string $path): void
{
    if (is_dir($path) && ! is_link($path)) {
        foreach (new FilesystemIterator($path) as $entry) {
            removeFixture($entry->getPathname());
        }
        rmdir($path);
    } else {
        unlink($path);
    }
}

function fixtureProject(string $root, bool $production, array $overrides = []): void
{
    // This minimal CI double loads only our JSON fixture .env, never real secrets.
    writeFixture($root . '/vendor/autoload.php', <<<'PHP'
<?php
namespace CodeIgniter\Config;
class DotEnv
{
    public function __construct(private string $root) {}
    public function load(): void
    {
        foreach (json_decode(file_get_contents($this->root . '/.env'), true, 512, JSON_THROW_ON_ERROR) as $key => $value) {
            $_ENV[$key] = $value;
        }
    }
}
PHP);
    writeFixture($root . '/app/Config/Database.php', <<<'PHP'
<?php
namespace Config;
class Database
{
    public array $default = [
        'DSN' => '', 'hostname' => '', 'username' => '', 'password' => '',
        'database' => '', 'DBDriver' => 'MySQLi', 'port' => 3306,
        'charset' => 'utf8mb4', 'DBCollat' => 'utf8mb4_unicode_ci',
    ];
    protected function initEnvValue(array &$values, string $prefix, string $class, string $name): void
    {
        foreach ($values as $key => $value) {
            $values[$key] = $_ENV[$name . '.' . $prefix . '.' . $key] ?? $value;
        }
    }
}
PHP);
    writeFixture($root . '/.env', json_encode($overrides + [
        'CI_ENVIRONMENT' => $production ? 'production' : 'development',
        'database.default.hostname' => $production ? 'production.invalid' : '127.0.0.1',
        'database.default.username' => $production ? 'production_reader' : 'local_writer',
        'database.default.password' => $production ? 'dummy_production_password' : 'dummy_local_password',
        'database.default.database' => $production ? 'production_fixture' : 'local_fixture',
    ], JSON_THROW_ON_ERROR));
}

function runFixture(string $suite, string $scenario, array $arguments = ['--yes'], array $overrides = []): array
{
    $root = $suite . '/' . $scenario;
    $local = $root . '/local project';
    $remote = $root . "/production project's checkout";
    fixtureProject($local, false, $overrides);
    fixtureProject($remote, true);
    mkdir($root . '/tmp', 0700);
    writeFixture($local . '/scripts/sync-production-db.php', file_get_contents(dirname(__DIR__, 2) . '/scripts/sync-production-db.php'));

    $fakeClient = "#!/usr/bin/env php\n" . <<<'PHP'
<?php
declare(strict_types=1);
$tool = basename($argv[0]);
$scenario = getenv('DB_SYNC_TEST_SCENARIO');
$event = ['tool' => $tool, 'arguments' => array_slice($argv, 1)];
foreach ($argv as $argument) {
    if (str_starts_with($argument, '--defaults-file=')) {
        $path = substr($argument, strlen('--defaults-file='));
        $event['credentials'] = ['path' => $path, 'mode' => fileperms($path) & 0777];
        if ($event['credentials']['mode'] !== 0600) {
            fwrite(STDERR, "Credentials must have mode 0600.\n");
            exit(99);
        }
        $contents = file_get_contents($path);
        $event['production_credentials'] = str_contains($contents, 'user="production_reader"');
        $event['local_credentials'] = str_contains($contents, 'user="local_writer"');
    }
}
$record = static function (string $name, array $extra = []) use (&$event): void {
    file_put_contents(getenv('DB_SYNC_TEST_LOG'), json_encode(['event' => $name] + $extra + $event) . "\n", FILE_APPEND | LOCK_EX);
};
if ($tool === 'ssh') {
    $record('ssh');
    if ($scenario === 'ssh_failure') {
        fwrite(STDERR, "Mock SSH failed.\n");
        exit(21);
    }
    // Execute the actual quoted remote PHP command and its streamed source locally.
    $process = proc_open(['/bin/sh', '-c', end($argv)], [0 => STDIN, 1 => STDOUT, 2 => STDERR], $pipes);
    exit(is_resource($process) ? proc_close($process) : 22);
}
if (in_array('--version', $argv, true)) {
    $record('version');
    echo "Fixture client 1.0\n";
    exit;
}
if (in_array('--help', $argv, true)) {
    $record('help');
    echo "no-login-paths no-tablespaces set-gtid-purged column-statistics\n";
    echo "An expired password can put the connection in expired password sandbox mode.\n";
    if ($tool === 'mysql' && $scenario === 'mariadb_modern_client') {
        echo "  sandbox (\\-) Switch to sandbox mode.\n";
    }
    exit;
}
if ($tool === 'production-dump' || $tool === 'local-dump') {
    $production = $tool === 'production-dump';
    $record($production ? 'production_dump' : 'local_backup');
    if (! $production && in_array($scenario, ['events_disabled', 'legacy_routine_metadata'], true) && ! in_array('--skip-events', $argv, true)) {
        fwrite(STDERR, "Mock SHOW EVENTS failed: event scheduler is disabled.\n");
        exit(27);
    }
    if (! $production && $scenario === 'legacy_routine_metadata' && ! in_array('--skip-routines', $argv, true)) {
        fwrite(STDERR, "Mock SHOW FUNCTION STATUS failed: stale mysql.proc schema.\n");
        exit(28);
    }
    if ($production && $scenario === 'production_banner') {
        echo "Unexpected SSH startup banner\n";
    }
    if ($production && str_starts_with($scenario, 'mariadb_')) {
        echo $scenario === 'mariadb_plain_old_client'
            ? "/*!999999\\- enable the sandbox mode */\n"
            : "/*M!999999\\- enable the sandbox mode */\n";
        echo "-- MariaDB dump 10.19 Distrib 11.8.9-MariaDB\n";
    } else {
        echo "-- MySQL dump 10.13 Distrib 8.0.0\n";
    }
    echo $production ? "-- production fixture\nCREATE TABLE example (id INT);\n" : "-- local recovery fixture\nDROP DATABASE `local_fixture`;\nCREATE DATABASE `local_fixture`;\nUSE `local_fixture`;\n";
    if ($scenario === ($production ? 'production_dump_failure' : 'backup_failure')) {
        fwrite(STDERR, "Mock dump failed.\n");
        exit(23);
    }
    if ($scenario !== ($production ? 'production_incomplete' : 'backup_incomplete')) {
        echo "-- Dump completed on 2026-01-01 00:00:00\n";
    }
    exit;
}
foreach ($argv as $argument) {
    if ($argument === '--execute=SELECT 1') {
        $record('select');
        echo "1\n";
        exit;
    }
    if (str_starts_with($argument, '--execute=DROP DATABASE ')) {
        $record('reset', ['sql' => substr($argument, strlen('--execute='))]);
        if ($scenario === 'reset_failure') {
            fwrite(STDERR, "Mock reset failed.\n");
            exit(25);
        }
        exit;
    }
}
$sql = stream_get_contents(STDIN);
$record('import', ['sql' => $sql]);
if (in_array($scenario, ['mariadb_old_client', 'mariadb_plain_old_client'], true) && str_contains($sql, '\\- enable the sandbox mode')) {
    fwrite(STDERR, "Unknown command '\\-'.\n");
    exit(26);
}
if ($scenario === 'import_failure') {
    fwrite(STDERR, "Mock import failed.\n");
    exit(24);
}
PHP;
    foreach (['ssh', 'mysql', 'local-dump', 'production-dump'] as $tool) {
        writeFixture($root . '/bin/' . $tool, $fakeClient);
        chmod($root . '/bin/' . $tool, 0700);
    }
    symlink(PHP_BINARY, $root . '/bin/php');
    writeFixture($local . '/scripts/db-sync.json', json_encode([
        'ssh' => ['destination' => 'fixture@production.invalid', 'port' => 2222],
        'remote' => ['project_path' => $remote, 'php_binary' => PHP_BINARY, 'dump_binary' => $root . '/bin/production-dump'],
        'local' => ['mysql_binary' => $root . '/bin/mysql', 'dump_binary' => $root . '/bin/local-dump']
            + (in_array($scenario, ['events_disabled', 'legacy_routine_metadata'], true) ? ['dump_events' => false] : [])
            + ($scenario === 'legacy_routine_metadata' ? ['dump_routines' => false] : []),
    ], JSON_THROW_ON_ERROR));

    $log = $root . '/calls.jsonl';
    $stdout = $root . '/stdout';
    $stderr = $root . '/stderr';
    $process = proc_open([PHP_BINARY, $local . '/scripts/sync-production-db.php', ...$arguments], [
        0 => ['file', '/dev/null', 'r'], 1 => ['file', $stdout, 'w'], 2 => ['file', $stderr, 'w'],
    ], $pipes, $local, [
        'PATH' => $root . '/bin:/usr/bin:/bin', 'TMPDIR' => $root . '/tmp',
        'DB_SYNC_TEST_LOG' => $log, 'DB_SYNC_TEST_SCENARIO' => $scenario,
    ]);
    check(is_resource($process), 'Cannot start fixture subprocess.');
    $status = proc_close($process);
    $calls = is_file($log) ? array_map(static fn (string $line): array => json_decode($line, true, 512, JSON_THROW_ON_ERROR), file($log, FILE_IGNORE_NEW_LINES)) : [];
    $output = file_get_contents($stdout) . file_get_contents($stderr);
    foreach ($calls as $call) {
        if (isset($call['credentials'])) {
            check($call['credentials']['mode'] === 0600, $scenario . ': credential permissions');
            check(! file_exists($call['credentials']['path']), $scenario . ': temporary credentials were not removed');
            $production = $call['production_credentials'];
            foreach (['--no-login-paths', '--host=' . ($production ? 'production.invalid' : '127.0.0.1'),
                '--port=3306', '--user=' . ($production ? 'production_reader' : 'local_writer'), '--protocol=TCP'] as $argument) {
                check(in_array($argument, $call['arguments'], true), $scenario . ': missing explicit client setting ' . $argument);
            }
        }
    }
    check(glob($root . '/tmp/jewellery-db-*') === [], $scenario . ': leaked credentials file');
    check(! str_contains($output . json_encode($calls), 'dummy_local_password'), $scenario . ': local password exposed');
    check(! str_contains($output . json_encode($calls), 'dummy_production_password'), $scenario . ': production password exposed');
    $backups = $local . '/writable/backups/db-sync';
    check(glob($backups . '/*.partial') === [], $scenario . ': partial dump not cleaned up');

    return compact('status', 'output', 'calls', 'backups', 'local');
}

function events(array $result): array
{
    return array_column($result['calls'], 'event');
}

function assertStoppedBeforeReset(array $result, string $message): void
{
    check($result['status'] !== 0, $message . ': expected nonzero exit');
    check(! in_array('reset', events($result), true) && ! in_array('import', events($result), true), $message . ': local data was modified');
}

$suite = realpath(sys_get_temp_dir()) . '/jewellery-sync-tests-' . bin2hex(random_bytes(6));
mkdir($suite, 0700);
$passed = 0;
$exitCode = 0;

try {
    $result = runFixture($suite, 'dry_run', ['--dry-run']);
    check($result['status'] === 0 && $result['calls'] === [], 'Dry run must make no client process calls.');
    check(! is_dir($result['local'] . '/writable'), 'Dry run must not create backup files/directories.');
    echo "PASS dry run makes no SSH/database calls or backup writes\n";
    $passed++;

    $result = runFixture($suite, 'success');
    check($result['status'] === 0, 'Successful sync failed: ' . $result['output']);
    $steps = array_values(array_filter(events($result), static fn (string $event): bool => in_array($event, ['ssh', 'production_dump', 'local_backup', 'reset', 'import'], true)));
    check($steps === ['ssh', 'production_dump', 'local_backup', 'reset', 'import'], 'Download/backup/reset/import order is incorrect.');
    foreach ($result['calls'] as $call) {
        if ($call['event'] === 'production_dump') {
            check($call['production_credentials'] && ! $call['local_credentials'], 'Remote dump must use the production project credentials.');
            check(end($call['arguments']) === 'production_fixture', 'Remote dump must target the production database.');
            check(! in_array('--databases', $call['arguments'], true), 'Production dump must not select/recreate its source database.');
        } elseif ($call['event'] === 'local_backup') {
            check($call['local_credentials'] && ! $call['production_credentials'], 'Local backup must use local credentials.');
            check(in_array('--databases', $call['arguments'], true) && in_array('--add-drop-database', $call['arguments'], true), 'Recovery backup must include database recreation.');
        } elseif ($call['event'] === 'reset') {
            check(str_contains($call['sql'], 'DROP DATABASE `local_fixture`; CREATE DATABASE `local_fixture`'), 'Reset must target the local database.');
        } elseif ($call['event'] === 'import') {
            check(str_contains($call['sql'], '-- production fixture'), 'Import must read the production dump.');
            check(in_array('--database=local_fixture', $call['arguments'], true), 'Import must target the local database.');
        }
    }
    check((fileperms($result['backups']) & 0777) === 0700, 'Backup directory must be private.');
    check(count(glob($result['backups'] . '/production-*.sql')) === 1 && count(glob($result['backups'] . '/local-before-sync-*.sql')) === 1, 'Successful sync must retain both dumps.');
    foreach (glob($result['backups'] . '/*.sql') as $dump) {
        check((fileperms($dump) & 0777) === 0600, 'SQL dumps must be private.');
    }
    echo "PASS real remote PHP payload, production/local separation, ordering, recovery dumps and permissions\n";
    $passed++;

    foreach (['events_disabled', 'legacy_routine_metadata'] as $scenario) {
        $result = runFixture($suite, $scenario);
        check($result['status'] === 0 && in_array('import', events($result), true), $scenario . ': backup must allow a complete sync: ' . $result['output']);
        foreach ($result['calls'] as $call) {
            if ($call['event'] === 'local_backup') {
                check(in_array('--skip-events', $call['arguments'], true) && ! in_array('--events', $call['arguments'], true), $scenario . ': local backup must explicitly skip events');
                $skipRoutines = $scenario === 'legacy_routine_metadata';
                check(in_array($skipRoutines ? '--skip-routines' : '--routines', $call['arguments'], true)
                    && ! in_array($skipRoutines ? '--routines' : '--skip-routines', $call['arguments'], true), $scenario . ': incorrect local routine options');
                foreach (['--triggers', '--add-drop-database', '--databases'] as $argument) {
                    check(in_array($argument, $call['arguments'], true), $scenario . ': backup must retain ' . $argument);
                }
            } elseif ($call['event'] === 'production_dump') {
                check(in_array('--events', $call['arguments'], true) && ! in_array('--skip-events', $call['arguments'], true), $scenario . ': production must include events by default');
                check(in_array('--routines', $call['arguments'], true) && ! in_array('--skip-routines', $call['arguments'], true), $scenario . ': production must include routines by default');
            }
        }
        echo 'PASS ' . $scenario . " applies local metadata options, preserves triggers/recovery options and production defaults\n";
        $passed++;
    }

    foreach (['mariadb_old_client', 'mariadb_plain_old_client', 'mariadb_modern_client'] as $scenario) {
        $result = runFixture($suite, $scenario);
        check($result['status'] === 0, $scenario . ': sync failed: ' . $result['output']);
        $dump = file_get_contents(glob($result['backups'] . '/production-*.sql')[0]);
        $marker = $scenario === 'mariadb_plain_old_client'
            ? "/*!999999\\- enable the sandbox mode */\n"
            : "/*M!999999\\- enable the sandbox mode */\n";
        check(str_starts_with($dump, $marker), $scenario . ': original production dump must retain the sandbox marker');
        $imports = array_values(array_filter($result['calls'], static fn (array $call): bool => $call['event'] === 'import'));
        check(count($imports) === 1, $scenario . ': expected exactly one import');
        $expected = $scenario === 'mariadb_modern_client' ? $dump : substr($dump, strlen($marker));
        check($imports[0]['sql'] === $expected, $scenario . ': imported SQL must preserve every byte apart from the incompatible first-line marker');
        check(str_contains($imports[0]['sql'], 'CREATE TABLE example (id INT);'), $scenario . ': first regular SQL statement must be preserved');
        echo 'PASS ' . $scenario . " handles the sandbox prologue without changing regular SQL or retained dump\n";
        $passed++;
    }

    foreach (['ssh_failure', 'production_dump_failure', 'production_incomplete', 'production_banner', 'backup_failure', 'backup_incomplete'] as $scenario) {
        $result = runFixture($suite, $scenario);
        assertStoppedBeforeReset($result, $scenario);
        if (str_starts_with($scenario, 'production_') || $scenario === 'ssh_failure') {
            check(! in_array('local_backup', events($result), true), $scenario . ': backup should not run after download failure');
        }
        echo 'PASS ' . $scenario . " prevents local replacement and cleans credentials/partial dumps\n";
        $passed++;
    }

    foreach (['reset_failure', 'import_failure'] as $scenario) {
        $result = runFixture($suite, $scenario);
        $backup = glob($result['backups'] . '/local-before-sync-*.sql');
        check($result['status'] !== 0 && count($backup) === 1, $scenario . ': must retain a local recovery backup');
        check(str_contains($result['output'], 'Recovery backup: ' . $backup[0]), $scenario . ': must report the recovery backup path');
        echo 'PASS ' . $scenario . " preserves and reports the recovery backup\n";
        $passed++;
    }

    foreach ([
        'production_environment' => ['CI_ENVIRONMENT' => 'production'],
        'nonlocal_host' => ['database.default.hostname' => 'production.invalid'],
    ] as $scenario => $overrides) {
        $result = runFixture($suite, $scenario, ['--yes'], $overrides);
        assertStoppedBeforeReset($result, $scenario);
        check($result['calls'] === [], $scenario . ': guard must run before any client process');
        echo 'PASS ' . $scenario . " is blocked before client execution\n";
        $passed++;
    }

    $result = runFixture($suite, 'unknown_argument', ['--unknown']);
    check($result['status'] !== 0 && str_contains($result['output'], 'Unknown or incomplete argument: --unknown') && $result['calls'] === [], 'Unknown arguments must fail before any client execution.');
    echo "PASS unknown arguments fail without client execution\n";
    $passed++;
    echo $passed . " integration scenarios passed; no real SSH or database connections were used.\n";
} catch (Throwable $error) {
    fwrite(STDERR, 'FAIL: ' . $error->getMessage() . "\n");
    $exitCode = 1;
} finally {
    removeFixture($suite);
}

exit($exitCode);
