<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCurrentTeam;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Evaluation extends Model
{
    use BelongsToCurrentTeam;
    use HasFactory;

    protected $fillable = [
        'team_id',
        'experiment_run_id',
        'evaluator_id',
        'clarity_score',
        'correctness_score',
        'completeness_score',
        'tone_score',
        'format_valid_manual',
        'hallucination_risk',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'format_valid_manual' => 'boolean',
        ];
    }

    public function experimentRun(): BelongsTo
    {
        return $this->belongsTo(ExperimentRun::class);
    }

    public function evaluator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'evaluator_id');
    }

    public function averageScore(): ?float
    {
        $scores = collect([
            $this->clarity_score,
            $this->correctness_score,
            $this->completeness_score,
            $this->tone_score,
        ])->filter(fn ($value) => $value !== null);

        if ($scores->isEmpty()) {
            return null;
        }

        return round($scores->avg(), 2);
    }
}
