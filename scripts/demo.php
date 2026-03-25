#!/usr/bin/env php
<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This script must be run from the command line.\n");
    exit(1);
}

$root = dirname(__DIR__);
chdir($root);

$args = array_slice($argv, 1);

if (in_array('--help', $args, true) || in_array('-h', $args, true)) {
    echo <<<TEXT
Evala demo bootstrap

Usage:
  php scripts/demo.php
  php scripts/demo.php --setup-only
  php scripts/demo.php --run-only
  php scripts/demo.php --with-gepa

Options:
  --setup-only  Install dependencies, prepare .env, migrate, seed, and build assets.
  --run-only    Skip setup and only start the local web, queue, and Reverb processes.
  --with-gepa   Install the local GEPA Python runtime during setup.

TEXT;

    exit(0);
}

$setupOnly = in_array('--setup-only', $args, true);
$runOnly = in_array('--run-only', $args, true);
$withGepa = in_array('--with-gepa', $args, true);

if ($setupOnly && $runOnly) {
    fail('Choose either --setup-only or --run-only, not both.');
}

$php = PHP_BINARY;
$composer = resolveExecutable('composer');
$npm = resolveExecutable('npm');

$envPath = $root.DIRECTORY_SEPARATOR.'.env';
$envExamplePath = $root.DIRECTORY_SEPARATOR.'.env.example';
$freshEnv = false;

if (! $runOnly) {
    if (! file_exists($root.DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR.'autoload.php')) {
        runStep('Installing Composer dependencies', [$composer, 'install', '--no-interaction', '--prefer-dist']);
    } else {
        info('Composer dependencies already installed.');
    }

    if (! is_dir($root.DIRECTORY_SEPARATOR.'node_modules')) {
        $npmInstallCommand = file_exists($root.DIRECTORY_SEPARATOR.'package-lock.json')
            ? [$npm, 'ci']
            : [$npm, 'install'];

        runStep('Installing frontend dependencies', $npmInstallCommand);
    } else {
        info('Frontend dependencies already installed.');
    }

    if (! file_exists($envPath)) {
        if (! copy($envExamplePath, $envPath)) {
            fail('Unable to create .env from .env.example.');
        }

        $freshEnv = true;
        info('Created .env from .env.example.');
    } else {
        info('Using existing .env file.');
    }

    $env = loadEnvFile($envPath);

    if ($freshEnv) {
        $sqlitePath = normalizePath($root.DIRECTORY_SEPARATOR.'database'.DIRECTORY_SEPARATOR.'database.sqlite');

        upsertEnvValues($envPath, [
            'APP_NAME' => 'Evala',
            'APP_URL' => 'http://127.0.0.1:8000',
            'DB_CONNECTION' => 'sqlite',
            'DB_DATABASE' => $sqlitePath,
            'BROADCAST_CONNECTION' => 'reverb',
            'QUEUE_CONNECTION' => 'database',
            'CACHE_STORE' => 'database',
            'SESSION_DRIVER' => 'database',
            'REVERB_HOST' => '127.0.0.1',
            'REVERB_PORT' => '8080',
            'REVERB_SCHEME' => 'http',
            'REVERB_SERVER_HOST' => '127.0.0.1',
            'REVERB_SERVER_PORT' => '8080',
        ]);

        $env = loadEnvFile($envPath);
        info('Applied local demo defaults to .env.');
    }

    ensureSqliteDatabaseExists($env, $root);

    if ($freshEnv || empty($env['APP_KEY'] ?? null)) {
        runStep('Generating application key', [$php, 'artisan', 'key:generate', '--force', '--no-interaction']);
    } else {
        info('Application key already present.');
    }

    if ($freshEnv) {
        runStep('Preparing seeded demo data', [$php, 'artisan', 'migrate:fresh', '--seed', '--force', '--no-interaction']);
    } else {
        runStep('Applying database migrations', [$php, 'artisan', 'migrate', '--force', '--no-interaction']);
    }

    if ($withGepa) {
        runStep('Installing GEPA runtime', [$php, 'artisan', 'gepa:install-runtime', '--no-interaction']);
    }

    runStep('Building frontend assets', [$npm, 'run', 'build']);

    if ($setupOnly) {
        summary();
        exit(0);
    }
}

summary();
info('Starting local demo services. Press Ctrl+C to stop them.');

$processes = [
    startLongRunningProcess('server', [$php, 'artisan', 'serve', '--host=127.0.0.1', '--port=8000']),
    startLongRunningProcess('queue', [$php, 'artisan', 'queue:work', '--tries=1', '--timeout=0', '--queue=default']),
    startLongRunningProcess('reverb', [$php, 'artisan', 'reverb:start', '--host=127.0.0.1', '--port=8080', '--hostname=127.0.0.1']),
];

register_shutdown_function(function () use (&$processes): void {
    foreach ($processes as $process) {
        if (! is_resource($process['proc'])) {
            continue;
        }

        proc_terminate($process['proc']);

        foreach ($process['pipes'] as $pipe) {
            if (is_resource($pipe)) {
                fclose($pipe);
            }
        }

        proc_close($process['proc']);
    }
});

streamProcessOutput($processes);

function runStep(string $title, array $command): void
{
    info($title.'...');

    passthru(buildCommand($command), $exitCode);

    if ($exitCode !== 0) {
        fail($title.' failed with exit code '.$exitCode.'.');
    }
}

function startLongRunningProcess(string $label, array $command): array
{
    $descriptorSpec = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];

    $process = proc_open(buildCommand($command), $descriptorSpec, $pipes, getcwd());

    if (! is_resource($process)) {
        fail('Unable to start '.$label.' process.');
    }

    fclose($pipes[0]);
    stream_set_blocking($pipes[1], false);
    stream_set_blocking($pipes[2], false);

    return [
        'label' => $label,
        'proc' => $process,
        'pipes' => $pipes,
        'buffers' => [
            1 => '',
            2 => '',
        ],
    ];
}

function streamProcessOutput(array &$processes): void
{
    while (true) {
        $read = [];

        foreach ($processes as $process) {
            foreach ([1, 2] as $index) {
                if (isset($process['pipes'][$index]) && is_resource($process['pipes'][$index]) && ! feof($process['pipes'][$index])) {
                    $read[] = $process['pipes'][$index];
                }
            }
        }

        if ($read === []) {
            break;
        }

        $write = null;
        $except = null;
        $selected = @stream_select($read, $write, $except, 0, 200000);

        if ($selected === false) {
            usleep(200000);
        } elseif ($selected > 0) {
            foreach ($processes as &$process) {
                foreach ([1, 2] as $index) {
                    $pipe = $process['pipes'][$index] ?? null;

                    if (! is_resource($pipe) || ! in_array($pipe, $read, true)) {
                        continue;
                    }

                    $chunk = stream_get_contents($pipe);

                    if ($chunk === false || $chunk === '') {
                        continue;
                    }

                    $process['buffers'][$index] .= $chunk;
                    flushProcessBuffer($process, $index);
                }
            }
            unset($process);
        }

        foreach ($processes as $process) {
            $status = proc_get_status($process['proc']);

            if ($status['running']) {
                continue;
            }

            flushRemainingBuffers($process);
            fail('The '.$process['label'].' process exited unexpectedly with code '.($status['exitcode'] ?? 1).'.');
        }
    }
}

function flushProcessBuffer(array &$process, int $index): void
{
    while (($newline = strpos($process['buffers'][$index], "\n")) !== false) {
        $line = substr($process['buffers'][$index], 0, $newline);
        $process['buffers'][$index] = substr($process['buffers'][$index], $newline + 1);
        outputPrefixedLine($process['label'], $line);
    }
}

function flushRemainingBuffers(array $process): void
{
    foreach ([1, 2] as $index) {
        $buffer = trim($process['buffers'][$index]);

        if ($buffer !== '') {
            outputPrefixedLine($process['label'], $buffer);
        }
    }
}

function outputPrefixedLine(string $label, string $line): void
{
    $trimmed = rtrim($line, "\r");

    if ($trimmed === '') {
        return;
    }

    fwrite(STDOUT, '['.$label.'] '.$trimmed.PHP_EOL);
}

function ensureSqliteDatabaseExists(array $env, string $root): void
{
    if (($env['DB_CONNECTION'] ?? null) !== 'sqlite') {
        return;
    }

    $database = trim((string) ($env['DB_DATABASE'] ?? ''));

    if ($database === '') {
        $database = normalizePath($root.DIRECTORY_SEPARATOR.'database'.DIRECTORY_SEPARATOR.'database.sqlite');
        upsertEnvValues($root.DIRECTORY_SEPARATOR.'.env', ['DB_DATABASE' => $database]);
    }

    $databasePath = isAbsolutePath($database)
        ? $database
        : normalizePath($root.DIRECTORY_SEPARATOR.$database);

    $directory = dirname($databasePath);

    if (! is_dir($directory) && ! mkdir($directory, 0777, true) && ! is_dir($directory)) {
        fail('Unable to create SQLite directory at '.$directory.'.');
    }

    if (! file_exists($databasePath) && ! touch($databasePath)) {
        fail('Unable to create SQLite database at '.$databasePath.'.');
    }
}

function upsertEnvValues(string $path, array $values): void
{
    $lines = file_exists($path)
        ? preg_split("/\r\n|\n|\r/", (string) file_get_contents($path))
        : [];

    $updated = [];
    $seen = [];

    foreach ($lines as $line) {
        $trimmed = ltrim($line);

        if ($trimmed === '' || str_starts_with($trimmed, '#') || ! str_contains($line, '=')) {
            $updated[] = $line;
            continue;
        }

        [$key] = explode('=', $line, 2);
        $key = trim($key);

        if (! array_key_exists($key, $values)) {
            $updated[] = $line;
            continue;
        }

        $updated[] = $key.'='.$values[$key];
        $seen[$key] = true;
    }

    foreach ($values as $key => $value) {
        if (! isset($seen[$key])) {
            $updated[] = $key.'='.$value;
        }
    }

    file_put_contents($path, implode(PHP_EOL, $updated).PHP_EOL);
}

function loadEnvFile(string $path): array
{
    if (! file_exists($path)) {
        return [];
    }

    $env = [];
    $lines = preg_split("/\r\n|\n|\r/", (string) file_get_contents($path));

    foreach ($lines as $line) {
        $trimmed = trim($line);

        if ($trimmed === '' || str_starts_with($trimmed, '#') || ! str_contains($line, '=')) {
            continue;
        }

        [$key, $value] = explode('=', $line, 2);
        $env[trim($key)] = trim($value, " \t\n\r\0\x0B\"'");
    }

    return $env;
}

function buildCommand(array $parts): string
{
    return implode(' ', array_map(escapeCommandPart(...), $parts));
}

function escapeCommandPart(string $value): string
{
    return escapeshellarg($value);
}

function summary(): void
{
    echo PHP_EOL;
    echo 'Evala demo URL: http://127.0.0.1:8000'.PHP_EOL;
    echo 'Demo accounts: admin@promptlab.local / password, team@promptlab.local / password'.PHP_EOL;
    echo PHP_EOL;
}

function info(string $message): void
{
    fwrite(STDOUT, '[demo] '.$message.PHP_EOL);
}

function fail(string $message): never
{
    fwrite(STDERR, '[demo] '.$message.PHP_EOL);
    exit(1);
}

function normalizePath(string $path): string
{
    return str_replace('\\', '/', $path);
}

function isAbsolutePath(string $path): bool
{
    if ($path === '') {
        return false;
    }

    return preg_match('/^(?:[A-Za-z]:[\/\\\\]|\/|\\\\\\\\)/', $path) === 1;
}

function isWindows(): bool
{
    return DIRECTORY_SEPARATOR === '\\';
}

function resolveExecutable(string $name): string
{
    $lookupCommand = isWindows()
        ? 'where.exe '.escapeshellarg($name).' 2>NUL'
        : 'command -v '.escapeshellarg($name).' 2>/dev/null';

    $output = [];
    $exitCode = 0;

    exec($lookupCommand, $output, $exitCode);

    if ($exitCode !== 0 || $output === []) {
        fail('Required executable not found in PATH: '.$name.'.');
    }

    if (! isWindows()) {
        return trim($output[0]);
    }

    $matches = array_values(array_filter(
        array_map('trim', $output),
        static fn (string $path): bool => $path !== ''
    ));

    foreach (['.bat', '.cmd', '.exe'] as $extension) {
        foreach ($matches as $match) {
            if (str_ends_with(strtolower($match), $extension)) {
                return $match;
            }
        }
    }

    return $matches[0];
}
