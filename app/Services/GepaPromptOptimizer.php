<?php

namespace App\Services;

use App\Exceptions\TerminalOperationException;
use App\Models\PromptOptimizationRun;
use Illuminate\Support\Facades\File;
use RuntimeException;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;

class GepaPromptOptimizer
{
    public function __construct(
        private readonly GepaRuntimeManager $runtime,
    ) {
    }

    public function optimize(PromptOptimizationRun $run, array $payload): array
    {
        $this->runtime->ensureReady();

        $workingDirectory = $this->runDirectory($run);
        File::ensureDirectoryExists($workingDirectory);

        $payloadPath = $workingDirectory.DIRECTORY_SEPARATOR.'payload.json';
        File::put(
            $payloadPath,
            json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)
        );

        $process = new Process([
            $this->runtime->pythonBinary(),
            $this->runtime->scriptPath(),
            '--payload',
            $payloadPath,
        ], base_path());

        $process->setTimeout($this->runtime->optimizationProcessTimeoutSeconds());

        try {
            $process->run();
        } catch (ProcessTimedOutException $exception) {
            $process->stop(3);

            throw new TerminalOperationException(
                'GEPA optimization timed out after '.$this->runtime->optimizationProcessTimeoutSeconds().' seconds.',
                previous: $exception,
            );
        }

        if (! $process->isSuccessful()) {
            throw new RuntimeException(trim($process->getErrorOutput() ?: $process->getOutput() ?: 'GEPA optimization failed.'));
        }

        $decoded = json_decode($process->getOutput(), true);

        if (! is_array($decoded)) {
            throw new RuntimeException('GEPA optimizer returned an invalid response payload.');
        }

        if (($decoded['ok'] ?? true) !== true) {
            throw new RuntimeException((string) ($decoded['error'] ?? 'GEPA optimizer failed.'));
        }

        return $decoded;
    }

    private function runDirectory(PromptOptimizationRun $run): string
    {
        return rtrim($this->runtime->runRoot(), DIRECTORY_SEPARATOR)
            .DIRECTORY_SEPARATOR
            .'run-'.str_pad((string) $run->id, 6, '0', STR_PAD_LEFT);
    }
}
