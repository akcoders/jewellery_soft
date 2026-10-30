<?php

declare(strict_types=1);

/** Pull the production MySQL database over SSH into this checkout's local database. */
namespace Jewellery\DatabaseSync;

use RuntimeException;
use Throwable;

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('CLI only.');
}

umask(0077);

/** Run without a local shell; only the SSH remote command needs shell quoting. */
function run(array $command, $input = null, $output = null): void
{
    $process = proc_open($command, [0 => $input ?? ['file', '/dev/null', 'r'], 1 => $output ?? STDOUT, 2 => STDERR], $pipes);
    if (! is_resource($process) || proc_close($process) !== 0) {
        throw new RuntimeException(basename($command[0]) . ' failed. See the error above.');
    }
}

function capture(array $command): string
{
    $output = tmpfile();
    if ($output === false) {
        throw new RuntimeException('Cannot create a temporary output file.');
    }
    try {
        run($command, null, $output);
        rewind($output);

        return stream_get_contents($output);
    } finally {
        fclose($output);
    }
}

/** Use CI's own dotenv/config parsing without booting the application or opening a DB. */
function databaseConfig(string $root): array
{
    if (! is_file($root . '/.env') || ! is_file($root . '/vendor/autoload.php')) {
        throw new RuntimeException('Project path must contain .env and vendor/autoload.php.');
    }
    defined('APPPATH') || define('APPPATH', $root . '/app/');
    require_once $root . '/vendor/autoload.php';
    (new \CodeIgniter\Config\DotEnv($root))->load();
    require_once $root . '/app/Config/Database.php';

    $config = new class extends \Config\Database {
        public function __construct()
        {
            // Skip service discovery and the application constructor's testing override.
            $this->initEnvValue($this->default, 'default', \Config\Database::class, 'database');
        }
    };
    $db = $config->default;
    if (($db['DBDriver'] ?? '') !== 'MySQLi' || ! empty($db['DSN'])) {
        throw new RuntimeException('Only the default MySQLi connection with separate credentials is supported (no DSN).');
    }
    if (empty($db['hostname']) || empty($db['username']) || empty($db['database'])) {
        throw new RuntimeException('Default database hostname, username and database are required.');
    }
    if (in_array(strtolower($db['database']), ['mysql', 'sys', 'information_schema', 'performance_schema'], true)) {
        throw new RuntimeException('System databases cannot be synchronized.');
    }

    return $db;
}

function optionValue(string $value): string
{
    return '"' . strtr($value, ["\\" => "\\\\", '"' => '\\"', "\n" => '\\n', "\r" => '\\r', "\t" => '\\t']) . '"';
}

/** Passwords stay in a private, temporary option file, never process arguments. */
function credentialsFile(array $db): string
{
    $path = tempnam(sys_get_temp_dir(), 'jewellery-db-');
    if ($path === false) {
        throw new RuntimeException('Cannot create database credentials file.');
    }
    register_shutdown_function(static fn () => is_file($path) && unlink($path));
    $options = ['user' => $db['username'], 'password' => $db['password'], 'port' => (string) $db['port']];
    $options[str_starts_with($db['hostname'], '/') ? 'socket' : 'host'] = $db['hostname'];
    $contents = "[client]\n";
    foreach ($options as $key => $value) {
        $contents .= $key . '=' . optionValue((string) $value) . "\n";
    }
    if (file_put_contents($path, $contents) === false || ! chmod($path, 0600)) {
        throw new RuntimeException('Cannot write private database credentials file.');
    }

    return $path;
}

function clientCommand(string $binary, string $credentials, array $db, string $help): array
{
    $command = [$binary, '--defaults-file=' . $credentials];
    if (str_contains($help, 'no-login-paths')) {
        $command[] = '--no-login-paths';
    }
    // Older MySQL clients still read .mylogin.cnf even with --defaults-file.
    // Explicit connection arguments prevent that file from changing the target host.
    $socket = str_starts_with($db['hostname'], '/');
    array_push($command, '--host=' . ($socket ? 'localhost' : $db['hostname']),
        '--port=' . $db['port'], '--user=' . $db['username'],
        '--protocol=' . ($socket || $db['hostname'] === 'localhost' ? 'SOCKET' : 'TCP'));
    if ($socket) {
        $command[] = '--socket=' . $db['hostname'];
    } elseif ($db['hostname'] === 'localhost' && ini_get('mysqli.default_socket')) {
        $command[] = '--socket=' . ini_get('mysqli.default_socket');
    }

    return $command;
}

function dumpCommand(string $binary, string $credentials, array $db, bool $backup = false, array $options = []): array
{
    $help = capture([$binary, '--no-defaults', '--help']);
    $command = [...clientCommand($binary, $credentials, $db, $help), '--single-transaction', '--quick', '--skip-lock-tables',
        '--hex-blob', '--default-character-set=utf8mb4',
        ($options['dump_routines'] ?? true) ? '--routines' : '--skip-routines',
        ($options['dump_events'] ?? true) ? '--events' : '--skip-events', '--triggers', '--comments', '--dump-date'];
    // MySQL and MariaDB do not expose exactly the same dump options.
    foreach (['no-tablespaces' => '--no-tablespaces', 'set-gtid-purged' => '--set-gtid-purged=OFF',
        'column-statistics' => '--column-statistics=0'] as $option => $argument) {
        if (str_contains($help, $option)) {
            $command[] = $argument;
        }
    }
    if ($backup) {
        $command[] = '--add-drop-database';
        $command[] = '--databases';
    }
    $command[] = '--';
    $command[] = $db['database'];

    return $command;
}

function verifyDump(string $path): void
{
    $file = fopen($path, 'rb');
    if ($file === false) {
        throw new RuntimeException('Cannot read SQL dump.');
    }
    try {
        $header = fread($file, 512);
        if (! preg_match('~\A(?:/\*M?!999999[^\r\n]*\*/[ \t]*\r?\n)?-- (?:MySQL|MariaDB) dump\b~', $header)) {
            throw new RuntimeException('SQL dump header is invalid (possible SSH banner/PHP output); local database has not been replaced.');
        }
        fseek($file, -min(filesize($path), 4096), SEEK_END);
        if (! preg_match('/(?:\A|\n)-- Dump completed on [^\r\n]+\r?\n?\z/', stream_get_contents($file))) {
            throw new RuntimeException('SQL dump is incomplete; local database has not been replaced.');
        }
    } finally {
        fclose($file);
    }
}

function identifier(string $value): string
{
    return '`' . str_replace('`', '``', $value) . '`';
}

function supportsSandboxCommand(string $help): bool
{
    // "expired password sandbox mode" is unrelated to the sandbox (\-) command.
    return preg_match('/^[ \t]*sandbox[ \t]+\(/mi', $help) === 1;
}

/** Older clients cannot parse recent MariaDB dumps' sandbox client command. */
function importFile(string $path, bool $supportsSandbox)
{
    $input = fopen($path, 'rb');
    if ($input === false) {
        throw new RuntimeException('Cannot open production SQL for import.');
    }
    $firstLine = rtrim((string) fgets($input, 512), "\r\n \t");
    $sandboxHeaders = ['/*M!999999\\- enable the sandbox mode */', '/*!999999\\- enable the sandbox mode */'];
    if ($supportsSandbox || ! in_array($firstLine, $sandboxHeaders, true)) {
        rewind($input);
    }

    return $input;
}

function main(array $arguments): void
{
    // The same script is streamed to PHP over SSH; no server-side installation is needed.
    if (($arguments[1] ?? '') === '--remote-dump') {
        $remote = json_decode(base64_decode($arguments[2] ?? '', true), true, 512, JSON_THROW_ON_ERROR);
        $db = databaseConfig($remote['project_path']);
        $credentials = credentialsFile($db);
        try {
            run(dumpCommand($remote['dump_binary'], $credentials, $db, false, $remote));
        } finally {
            unlink($credentials);
        }

        return;
    }

    $root = dirname(__DIR__);
    $configPath = $root . '/scripts/db-sync.json';
    $dryRun = false;
    $yes = false;
    for ($i = 1; $i < count($arguments); $i++) {
        $argument = $arguments[$i];
        if ($argument === '--help' || $argument === '-h') {
            echo "Usage: php scripts/sync-production-db.php [--config PATH] [--dry-run] [--yes]\n\n"
                . "Pull production into local over SSH. Replaces the local database after backing it up.\n"
                . "Copy scripts/db-sync.example.json to scripts/db-sync.json and fill SSH settings.\n"
                . "--dry-run  Show the plan without SSH, database connections, or file writes.\n"
                . "--yes      Skip typing the local database name to confirm replacement.\n";

            return;
        }
        if ($argument === '--config' && isset($arguments[$i + 1])) {
            $configPath = $arguments[++$i];
        } elseif (str_starts_with($argument, '--config=')) {
            $configPath = substr($argument, 9);
        } elseif ($argument === '--dry-run') {
            $dryRun = true;
        } elseif ($argument === '--yes') {
            $yes = true;
        } else {
            throw new RuntimeException('Unknown or incomplete argument: ' . $argument);
        }
    }
    if (! is_file($configPath)) {
        throw new RuntimeException('Config not found. Copy scripts/db-sync.example.json to scripts/db-sync.json and fill SSH settings.');
    }
    $config = json_decode(file_get_contents($configPath), true, 512, JSON_THROW_ON_ERROR);
    $ssh = $config['ssh'] ?? [];
    $remote = ($config['remote'] ?? []) + ['php_binary' => 'php', 'dump_binary' => 'mysqldump'];
    $local = ($config['local'] ?? []) + ['mysql_binary' => 'mysql', 'dump_binary' => 'mysqldump'];
    if (! preg_match('/\A(?!-)[a-zA-Z0-9_.@:\[\]-]+\z/', $ssh['destination'] ?? '')) {
        throw new RuntimeException('Set ssh.destination to your SSH host alias or user@host.');
    }
    $port = filter_var($ssh['port'] ?? 22, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 65535]]);
    if ($port === false || ! str_starts_with($remote['project_path'] ?? '', '/')) {
        throw new RuntimeException('Use a valid SSH port and an absolute remote.project_path.');
    }
    $db = databaseConfig($root);
    $environment = $_ENV['CI_ENVIRONMENT'] ?? $_SERVER['CI_ENVIRONMENT'] ?? getenv('CI_ENVIRONMENT');
    if (strtolower(trim((string) $environment)) === 'production') {
        throw new RuntimeException('Run this script from a local development checkout, not CI_ENVIRONMENT=production.');
    }
    if (! in_array($db['hostname'], ['localhost', '127.0.0.1', '::1'], true) && ! str_starts_with($db['hostname'], '/')) {
        throw new RuntimeException('Local database hostname must be localhost, 127.0.0.1, ::1, or a local socket path.');
    }
    $backupDir = $root . '/writable/backups/db-sync';
    echo "Direction: production -> local\nSource: {$ssh['destination']}:{$remote['project_path']}\n"
        . "Replace: {$db['hostname']}:{$db['port']}/{$db['database']}\nBackups: {$backupDir}\n";
    if ($dryRun) {
        echo "Dry run: download production SQL, back up local, recreate local DB, import. No connections or writes made.\n";

        return;
    }
    if (! $yes) {
        fwrite(STDOUT, "Local tables/data will be replaced. Type '{$db['database']}' to continue: ");
        if (trim((string) fgets(STDIN)) !== $db['database']) {
            throw new RuntimeException('Cancelled. Local database has not been changed.');
        }
    }
    // Fail early on missing local tools before downloading production data.
    capture([$local['mysql_binary'], '--version']);
    capture([$local['dump_binary'], '--version']);
    if (! is_dir($backupDir) && ! mkdir($backupDir, 0700, true) && ! is_dir($backupDir)) {
        throw new RuntimeException('Cannot create backup directory.');
    }
    $lock = fopen($backupDir . '/.sync.lock', 'c');
    if ($lock === false || ! flock($lock, LOCK_EX | LOCK_NB)) {
        throw new RuntimeException('Another database sync is running.');
    }
    $credentials = credentialsFile($db);
    $mysqlHelp = capture([$local['mysql_binary'], '--no-defaults', '--help']);
    $mysql = [...clientCommand($local['mysql_binary'], $credentials, $db, $mysqlHelp), '--default-character-set=utf8mb4', '--binary-mode'];
    $stamp = gmdate('Ymd-His') . '-' . bin2hex(random_bytes(3));
    $production = $backupDir . '/production-' . $stamp . '.sql';
    $backup = $backupDir . '/local-before-sync-' . $stamp . '.sql';
    $partial = null;
    try {
        capture([...$mysql, '--database=' . $db['database'], '--execute=SELECT 1']);
        $sshCommand = ['ssh', '-T', '-C', '-o', 'ConnectTimeout=15', '-o', 'ServerAliveInterval=15', '-o', 'ServerAliveCountMax=3', '-p', (string) $port];
        if (! empty($ssh['identity_file'])) {
            if (! is_file($ssh['identity_file'])) {
                throw new RuntimeException('ssh.identity_file does not exist. Use its full path.');
            }
            array_push($sshCommand, '-i', $ssh['identity_file'], '-o', 'IdentitiesOnly=yes');
        }
        $remoteCommand = implode(' ', array_map('escapeshellarg', [$remote['php_binary'], '-r',
            'eval(substr(stream_get_contents(STDIN), 5));', '--', '--remote-dump',
            base64_encode(json_encode($remote, JSON_THROW_ON_ERROR))]));
        array_push($sshCommand, '--', $ssh['destination'], $remoteCommand);
        echo "Downloading production dump over SSH...\n";
        $partial = $production . '.partial';
        $output = fopen($partial, 'xb');
        $source = fopen(__FILE__, 'rb');
        try {
            run($sshCommand, $source, $output);
        } finally {
            fclose($source);
            fclose($output);
        }
        verifyDump($partial);
        if (! rename($partial, $production)) {
            throw new RuntimeException('Cannot save production dump.');
        }
        $partial = $backup . '.partial';
        echo "Backing up local database...\n";
        $output = fopen($partial, 'xb');
        try {
            run(dumpCommand($local['dump_binary'], $credentials, $db, true, $local), null, $output);
        } finally {
            fclose($output);
        }
        verifyDump($partial);
        if (! rename($partial, $backup)) {
            throw new RuntimeException('Cannot save local backup.');
        }
        $partial = null;
        echo "Local backup: {$backup}\nRecreating and importing local database...\n";
        $reset = 'DROP DATABASE ' . identifier($db['database']) . '; CREATE DATABASE ' . identifier($db['database'])
            . ' CHARACTER SET ' . identifier($db['charset']) . ' COLLATE ' . identifier($db['DBCollat']) . ';';
        try {
            run([...$mysql, '--execute=' . $reset]);
            $input = importFile($production, supportsSandboxCommand($mysqlHelp));
            try {
                run([...$mysql, '--database=' . $db['database']], $input);
            } finally {
                fclose($input);
            }
        } catch (Throwable $error) {
            throw new RuntimeException('Local replacement failed; local database may be missing or partially restored. Recovery backup: ' . $backup, 0, $error);
        }
        echo "Sync complete. Production dump: {$production}\nLocal recovery backup: {$backup}\n";
    } finally {
        if ($partial !== null && is_file($partial)) {
            unlink($partial);
        }
        unlink($credentials);
        fclose($lock);
    }
}

try {
    main($argv);
} catch (Throwable $error) {
    fwrite(STDERR, 'Error: ' . $error->getMessage() . "\n");
    exit(1);
}
