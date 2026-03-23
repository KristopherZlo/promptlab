<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCurrentTeam;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PromptOptimizationRun extends Model
{
    use BelongsToCurrentTeam;
    use HasFactory;

    protected $fillable = [
        'team_id',
        'prompt_template_id',
        'use_case_id',
        'source_prompt_version_id',
        'derived_prompt_version_id',
        'created_by',
        'requested_model_name',
        'status',
        'budget_metric_calls',
        'best_score',
        'total_metric_calls',
        'candidate_count',
        'train_case_ids_json',
        'validation_case_ids_json',
        'config_json',
        'seed_candidate_json',
        'best_candidate_json',
        'result_json',
        'error_message',
        'started_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'best_score' => 'float',
            'train_case_ids_json' => 'array',
            'validation_case_ids_json' => 'array',
            'config_json' => 'array',
            'seed_candidate_json' => 'array',
            'best_candidate_json' => 'array',
            'result_json' => 'array',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function promptTemplate(): BelongsTo
    {
        return $this->belongsTo(PromptTemplate::class);
    }

    public function useCase(): BelongsTo
    {
        return $this->belongsTo(UseCase::class);
    }

    public function sourceVersion(): BelongsTo
    {
        return $this->belongsTo(PromptVersion::class, 'source_prompt_version_id');
    }

    public function derivedVersion(): BelongsTo
    {
        return $this->belongsTo(PromptVersion::class, 'derived_prompt_version_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
