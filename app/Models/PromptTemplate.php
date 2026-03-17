<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCurrentTeam;
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
}
