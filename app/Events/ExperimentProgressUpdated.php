<?php

namespace App\Events;

use App\Models\Experiment;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ExperimentProgressUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Experiment $experiment)
    {
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('experiments.'.$this->experiment->id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'experiment.progress';
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->experiment->id,
            'status' => $this->experiment->status,
            'completed_runs' => $this->experiment->completed_runs,
            'failed_runs' => $this->experiment->failed_runs,
            'total_runs' => $this->experiment->total_runs,
            'summary' => $this->experiment->summary_json,
        ];
    }
}
