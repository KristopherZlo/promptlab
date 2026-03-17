<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCurrentTeam;
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
}
