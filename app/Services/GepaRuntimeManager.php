<?php

namespace App\Services;

use App\Exceptions\TerminalOperationException;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;
use ZipArchive;

class GepaRuntimeManager
{
    public function pythonBinary(): string
    {
        return (string) config('gepa.python_binary');
    }

    public function scriptPath(): string
    {
        return (string) config('gepa.script_path');
    }

    public function requirementsPath(): string
    {
        return (string) config('gepa.requirements_path');
    }

    public function runtimeRoot(): string
    {
        return (string) config('gepa.runtime_root');
    }

    public function runRoot(): string
    {
        return (string) config('gepa.run_root');
    }

    public function optimizationProcessTimeoutSeconds(): int
    {
        return (int) config('gepa.optimization_process_timeout_seconds', 3600);
    }

    public function isInstalled(): bool
    {
        return File::exists($this->pythonBinary());
    }

    public function ensureReady(): void
    {
        if (! $this->isInstalled()) {
            throw new RuntimeException(
                'GEPA runtime is not installed. Run "php artisan gepa:install-runtime" first.'
            );
        }

        if (! File::exists($this->scriptPath())) {
            throw new RuntimeException('GEPA optimizer script is missing at '.$this->scriptPath().'.');
        }

        if (! File::exists($this->requirementsPath())) {
            throw new RuntimeException('GEPA runtime requirements file is missing at '.$this->requirementsPath().'.');
        }

        File::ensureDirectoryExists($this->runRoot());
    }

    public function install(): void
    {
        $runtimeRoot = $this->runtimeRoot();
        $downloadsRoot = $runtimeRoot.DIRECTORY_SEPARATOR.'downloads';
        $pythonRoot = dirname($this->pythonBinary());

        File::ensureDirectoryExists($downloadsRoot);
        File::ensureDirectoryExists($pythonRoot);

        $archivePath = $downloadsRoot.DIRECTORY_SEPARATOR.'python-embed.zip';
        $this->download(
            (string) config('gepa.python_embed_url'),
            $archivePath,
            (string) config('gepa.python_embed_sha256'),
        );

        if (File::isDirectory($pythonRoot)) {
            File::cleanDirectory($pythonRoot);
        }

        $zip = new ZipArchive();
        $status = $zip->open($archivePath);

        if ($status !== true) {
            throw new RuntimeException('Unable to open downloaded embedded Python archive.');
        }

        $zip->extractTo($pythonRoot);
        $zip->close();

        $this->enableSitePackages($pythonRoot);

        $getPipPath = $downloadsRoot.DIRECTORY_SEPARATOR.'get-pip.py';
        $this->download(
            (string) config('gepa.get_pip_url'),
            $getPipPath,
            (string) config('gepa.get_pip_sha256'),
        );

        $python = $this->pythonBinary();
        $requirementsPath = $this->requirementsPath();

        $this->runProcess(
            [$python, $getPipPath],
            $pythonRoot,
            (int) config('gepa.installer_process_timeout_seconds', 900),
            'GEPA runtime bootstrap timed out.'
        );
        $this->runProcess(
            [
                $python,
                '-m',
                'pip',
                'install',
                '--disable-pip-version-check',
                '--require-hashes',
                '-r',
                $requirementsPath,
            ],
            $pythonRoot,
            (int) config('gepa.installer_process_timeout_seconds', 900),
            'GEPA runtime dependency installation timed out.'
        );
    }

    protected function download(string $url, string $destination, string $expectedSha256): void
    {
        $response = Http::timeout((int) config('gepa.download_timeout_seconds', 240))
            ->sink($destination)
            ->get($url);

        if (! $response->successful()) {
            throw new RuntimeException("Unable to download GEPA runtime dependency from {$url}.");
        }

        $this->assertChecksum($destination, $expectedSha256);
    }

    protected function enableSitePackages(string $pythonRoot): void
    {
        $pthFiles = File::glob($pythonRoot.DIRECTORY_SEPARATOR.'python*._pth') ?: [];

        if ($pthFiles === []) {
            throw new RuntimeException('Unable to locate the embedded Python ._pth file.');
        }

        $pthFile = $pthFiles[0];
        $contents = File::get($pthFile);

        if (! str_contains($contents, '#import site')) {
            return;
        }

        File::put($pthFile, str_replace('#import site', 'import site', $contents));
    }

    protected function runProcess(
        array $command,
        string $workingDirectory,
        int $timeoutSeconds,
        string $timeoutMessage,
    ): void {
        $process = new Process($command, $workingDirectory);
        $process->setTimeout($timeoutSeconds);

        try {
            $process->run();
        } catch (ProcessTimedOutException $exception) {
            $process->stop(3);

            throw new TerminalOperationException($timeoutMessage, previous: $exception);
        }

        if (! $process->isSuccessful()) {
            throw new RuntimeException(trim($process->getErrorOutput() ?: $process->getOutput()));
        }
    }

    protected function assertChecksum(string $path, string $expectedSha256): void
    {
        $expected = strtolower(trim($expectedSha256));
        $actual = strtolower((string) hash_file('sha256', $path));

        if ($expected === '' || $actual === '' || ! hash_equals($expected, $actual)) {
            File::delete($path);

            throw new TerminalOperationException("Checksum verification failed for {$path}.");
        }
    }
}
