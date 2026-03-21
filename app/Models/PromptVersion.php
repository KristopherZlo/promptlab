<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCurrentTeam;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class PromptVersion extends Model
{
    use BelongsToCurrentTeam;
    use HasFactory;

    protected $fillable = [
        'team_id',
        'prompt_template_id',
        'version_label',
        'change_summary',
        'system_prompt',
        'user_prompt_template',
        'variables_schema',
        'output_type',
        'output_schema_json',
        'notes',
        'preferred_model',
        'is_library_approved',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'variables_schema' => 'array',
            'output_schema_json' => 'array',
            'is_library_approved' => 'boolean',
            'average_score' => 'float',
            'format_pass_rate' => 'float',
            'last_reviewed_at' => 'datetime',
        ];
    }

    public function promptTemplate(): BelongsTo
    {
        return $this->belongsTo(PromptTemplate::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function experimentRuns(): HasMany
    {
        return $this->hasMany(ExperimentRun::class);
    }

    public function libraryEntry(): HasOne
    {
        return $this->hasOne(LibraryEntry::class);
    }

    public function optimizationSourceRuns(): HasMany
    {
        return $this->hasMany(PromptOptimizationRun::class, 'source_prompt_version_id');
    }

    public function scopeWithSummaryMetrics(Builder $query): Builder
    {
        $averageScoreSql = self::evaluationAverageScoreSql('evaluations');

        return $query
            ->withCount(['experimentRuns as run_count'])
            ->selectSub(
                Evaluation::query()
                    ->selectRaw('count(*)')
                    ->join('experiment_runs', 'experiment_runs.id', '=', 'evaluations.experiment_run_id')
                    ->whereColumn('experiment_runs.prompt_version_id', 'prompt_versions.id'),
                'evaluation_count'
            )
            ->selectSub(
                ExperimentRun::query()
                    ->selectRaw('count(*)')
                    ->whereColumn('experiment_runs.prompt_version_id', 'prompt_versions.id')
                    ->whereExists(function ($subquery): void {
                        $subquery
                            ->selectRaw('1')
                            ->from('evaluations')
                            ->whereColumn('evaluations.experiment_run_id', 'experiment_runs.id');
                    }),
                'reviewed_runs'
            )
            ->selectSub(
                Evaluation::query()
                    ->selectRaw('count(distinct evaluations.evaluator_id)')
                    ->join('experiment_runs', 'experiment_runs.id', '=', 'evaluations.experiment_run_id')
                    ->whereColumn('experiment_runs.prompt_version_id', 'prompt_versions.id')
                    ->whereNotNull('evaluations.evaluator_id'),
                'reviewer_count'
            )
            ->selectSub(
                Evaluation::query()
                    ->selectRaw('max(coalesce(evaluations.updated_at, evaluations.created_at))')
                    ->join('experiment_runs', 'experiment_runs.id', '=', 'evaluations.experiment_run_id')
                    ->whereColumn('experiment_runs.prompt_version_id', 'prompt_versions.id'),
                'last_reviewed_at'
            )
            ->selectSub(
                Evaluation::query()
                    ->selectRaw("round(avg({$averageScoreSql}), 2)")
                    ->join('experiment_runs', 'experiment_runs.id', '=', 'evaluations.experiment_run_id')
                    ->whereColumn('experiment_runs.prompt_version_id', 'prompt_versions.id'),
                'average_score'
            )
            ->selectSub(
                ExperimentRun::query()
                    ->selectRaw('case when count(*) = 0 then null else round((sum(case when experiment_runs.format_valid = 1 then 1 else 0 end) * 100.0) / count(*), 1) end')
                    ->whereColumn('experiment_runs.prompt_version_id', 'prompt_versions.id'),
                'format_pass_rate'
            );
    }

    public static function evaluationAverageScoreSql(string $table = 'evaluations'): string
    {
        $scoreTotal = "coalesce({$table}.clarity_score, 0) + coalesce({$table}.correctness_score, 0) + coalesce({$table}.completeness_score, 0) + coalesce({$table}.tone_score, 0)";
        $scoreCount = "(case when {$table}.clarity_score is not null then 1 else 0 end + case when {$table}.correctness_score is not null then 1 else 0 end + case when {$table}.completeness_score is not null then 1 else 0 end + case when {$table}.tone_score is not null then 1 else 0 end)";

        return "case when {$scoreCount} = 0 then null else ({$scoreTotal} * 1.0) / {$scoreCount} end";
    }
}
