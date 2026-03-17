<?php

namespace App\Jobs;

use App\Services\ExperimentService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ExecuteExperimentRun implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $runId)
    {
    }

    public function handle(ExperimentService $experiments): void
    {
        $experiments->executeRun($this->runId);
    }
}
