<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCurrentTeam;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Experiment extends Model
{
    use BelongsToCurrentTeam;
    use HasFactory;

    protected $fillable = [
        'team_id',
        'use_case_id',
        'created_by',
        'mode',
        'provider',
        'model_name',
        'temperature',
        'max_tokens',
        'prompt_version_ids_json',
        'input_text',
        'variables_json',
        'summary_json',
        'status',
        'total_runs',
        'completed_runs',
        'failed_runs',
        'started_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'prompt_version_ids_json' => 'array',
            'variables_json' => 'array',
            'summary_json' => 'array',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'temperature' => 'float',
        ];
    }

    public function useCase(): BelongsTo
    {
        return $this->belongsTo(UseCase::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function runs(): HasMany
    {
        return $this->hasMany(ExperimentRun::class);
    }
}
