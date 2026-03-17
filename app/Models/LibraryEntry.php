<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCurrentTeam;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LibraryEntry extends Model
{
    use BelongsToCurrentTeam;
    use HasFactory;

    protected $fillable = [
        'team_id',
        'prompt_version_id',
        'approved_by',
        'approved_at',
        'recommended_model',
        'best_for',
        'usage_notes',
    ];

    protected function casts(): array
    {
        return [
            'approved_at' => 'datetime',
        ];
    }

    public function promptVersion(): BelongsTo
    {
        return $this->belongsTo(PromptVersion::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
