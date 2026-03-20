<?php

namespace App\Jobs;

use App\Exceptions\RetryableOperationException;
use App\Exceptions\TerminalOperationException;
use App\Services\ExperimentService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class ExecuteExperimentRun implements ShouldQueue
{
    use Queueable;

    public int $tries;

    public int $timeout;

    public bool $failOnTimeout = true;

    public function __construct(public int $runId)
    {
        $this->tries = (int) config('experiments.jobs.execute_run.tries', 3);
        $this->timeout = (int) config('experiments.jobs.execute_run.timeout', 120);
    }

    public function backoff(): array
    {
        return config('experiments.jobs.execute_run.backoff', [10, 30, 90]);
    }

    public function handle(ExperimentService $experiments): void
    {
        try {
            $experiments->executeRun($this->runId);
        } catch (TerminalOperationException $exception) {
            $experiments->markRunFailed($this->runId, $exception);
        } catch (RetryableOperationException $exception) {
            $experiments->markRunForRetry($this->runId, $exception);

            throw $exception;
        } catch (Throwable $exception) {
            $experiments->markRunFailed($this->runId, $exception);
        }
    }

    public function failed(Throwable $exception): void
    {
        app(ExperimentService::class)->markRunFailed($this->runId, $exception);
    }
}
