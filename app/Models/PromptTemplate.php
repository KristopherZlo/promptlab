<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCurrentTeam;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PromptTemplate extends Model
{
    use BelongsToCurrentTeam;
    use HasFactory;

    protected $fillable = [
        'team_id',
        'use_case_id',
        'name',
        'description',
        'task_type',
        'status',
        'preferred_model',
        'tags_json',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'tags_json' => 'array',
            'average_score' => 'float',
            'last_reviewed_at' => 'datetime',
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

    public function versions(): HasMany
    {
        return $this->hasMany(PromptVersion::class)->orderBy('id');
    }

    public function optimizationRuns(): HasMany
    {
        return $this->hasMany(PromptOptimizationRun::class)->latest();
    }

    public function scopeWithSummaryMetrics(Builder $query): Builder
    {
        $averageScoreSql = PromptVersion::evaluationAverageScoreSql('evaluations');

        return $query
            ->withCount('versions')
            ->selectSub(
                PromptVersion::query()
                    ->select('version_label')
                    ->whereColumn('prompt_versions.prompt_template_id', 'prompt_templates.id')
                    ->latest('id')
                    ->limit(1),
                'latest_version_label'
            )
            ->selectSub(
                Evaluation::query()
                    ->selectRaw("round(avg({$averageScoreSql}), 2)")
                    ->join('experiment_runs', 'experiment_runs.id', '=', 'evaluations.experiment_run_id')
                    ->join('prompt_versions', 'prompt_versions.id', '=', 'experiment_runs.prompt_version_id')
                    ->whereColumn('prompt_versions.prompt_template_id', 'prompt_templates.id'),
                'average_score'
            )
            ->selectSub(
                ExperimentRun::query()
                    ->selectRaw('count(*)')
                    ->join('prompt_versions', 'prompt_versions.id', '=', 'experiment_runs.prompt_version_id')
                    ->whereColumn('prompt_versions.prompt_template_id', 'prompt_templates.id')
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
                    ->join('prompt_versions', 'prompt_versions.id', '=', 'experiment_runs.prompt_version_id')
                    ->whereColumn('prompt_versions.prompt_template_id', 'prompt_templates.id')
                    ->whereNotNull('evaluations.evaluator_id'),
                'reviewer_count'
            )
            ->selectSub(
                Evaluation::query()
                    ->selectRaw('max(coalesce(evaluations.updated_at, evaluations.created_at))')
                    ->join('experiment_runs', 'experiment_runs.id', '=', 'evaluations.experiment_run_id')
                    ->join('prompt_versions', 'prompt_versions.id', '=', 'experiment_runs.prompt_version_id')
                    ->whereColumn('prompt_versions.prompt_template_id', 'prompt_templates.id'),
                'last_reviewed_at'
            );
    }
}
