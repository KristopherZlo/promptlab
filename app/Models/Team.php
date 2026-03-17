<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Team extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'created_by',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(TeamMembership::class)->orderBy('role')->orderBy('id');
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'team_memberships')
            ->withPivot('role')
            ->withTimestamps();
    }

    public function llmConnections(): HasMany
    {
        return $this->hasMany(LlmConnection::class)->orderByDesc('is_default')->orderBy('name');
    }

    public function activityLogs(): HasMany
    {
        return $this->hasMany(ActivityLog::class)->latest();
    }
}
