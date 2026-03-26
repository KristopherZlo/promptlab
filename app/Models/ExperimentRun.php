<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCurrentTeam;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ExperimentRun extends Model
{
    use BelongsToCurrentTeam;
    use HasFactory;

    public const REVIEWABLE_STATUSES = ['success', 'invalid_format'];

    protected $fillable = [
        'team_id',
        'experiment_id',
        'prompt_version_id',
        'test_case_id',
        'input_text',
        'variables_json',
        'compiled_prompt',
        'output_text',
        'output_json',
        'latency_ms',
        'token_input',
        'token_output',
        'format_valid',
        'status',
        'error_message',
        'provider_response_json',
    ];

    protected function casts(): array
    {
        return [
            'variables_json' => 'array',
            'output_json' => 'array',
            'provider_response_json' => 'array',
            'format_valid' => 'boolean',
        ];
    }

    public function experiment(): BelongsTo
    {
        return $this->belongsTo(Experiment::class);
    }

    public function promptVersion(): BelongsTo
    {
        return $this->belongsTo(PromptVersion::class);
    }

    public function testCase(): BelongsTo
    {
        return $this->belongsTo(TestCase::class);
    }

    public function evaluations(): HasMany
    {
        return $this->hasMany(Evaluation::class);
    }

    public function isReviewable(): bool
    {
        return in_array($this->status, self::REVIEWABLE_STATUSES, true);
    }
}
