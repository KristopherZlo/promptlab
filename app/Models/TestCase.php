<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCurrentTeam;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TestCase extends Model
{
    use BelongsToCurrentTeam;
    use HasFactory;

    protected $fillable = [
        'team_id',
        'use_case_id',
        'title',
        'input_text',
        'expected_output',
        'expected_json',
        'variables_json',
        'metadata_json',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'expected_json' => 'array',
            'variables_json' => 'array',
            'metadata_json' => 'array',
        ];
    }

    public function useCase(): BelongsTo
    {
        return $this->belongsTo(UseCase::class);
    }

    public function experimentRuns(): HasMany
    {
        return $this->hasMany(ExperimentRun::class);
    }
}
