<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCurrentTeam;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class UseCase extends Model
{
    use BelongsToCurrentTeam;
    use HasFactory;

    protected $fillable = [
        'team_id',
        'name',
        'slug',
        'description',
        'business_goal',
        'primary_input_label',
        'status',
        'created_by',
        'updated_by',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function promptTemplates(): HasMany
    {
        return $this->hasMany(PromptTemplate::class);
    }

    public function testCases(): HasMany
    {
        return $this->hasMany(TestCase::class);
    }

    public function experiments(): HasMany
    {
        return $this->hasMany(Experiment::class);
    }
}
