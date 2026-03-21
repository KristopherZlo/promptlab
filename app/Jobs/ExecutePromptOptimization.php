<?php

namespace App\Jobs;

use App\Services\PromptOptimizationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class ExecutePromptOptimization implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout;

    public bool $failOnTimeout = true;

    public function __construct(public int $runId)
    {
        $this->timeout = (int) config('gepa.job_timeout_seconds', 3600);
    }

    public function handle(PromptOptimizationService $optimizations): void
    {
        $optimizations->executeRun($this->runId);
    }

    public function failed(Throwable $exception): void
    {
        app(PromptOptimizationService::class)->markRunFailed($this->runId, $exception);
    }
}
