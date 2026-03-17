<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Services\TeamPermissionService;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    public const ROLE_ADMIN = 'admin';

    public const ROLE_TEAM_MEMBER = 'team_member';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'first_name',
        'last_name',
        'email',
        'password',
        'role',
        'current_team_id',
    ];

    protected $appends = [
        'display_name',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $user): void {
            $fullName = trim(implode(' ', array_filter([
                trim((string) $user->first_name),
                trim((string) $user->last_name),
            ])));

            if ($fullName !== '') {
                $user->name = $fullName;
                return;
            }

            if (filled($user->name)) {
                [$firstName, $lastName] = self::splitName((string) $user->name);
                $user->first_name ??= $firstName ?: null;
                $user->last_name ??= $lastName ?: null;
            }
        });
    }

    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    public function promptTemplates(): HasMany
    {
        return $this->hasMany(PromptTemplate::class, 'created_by');
    }

    public function promptVersions(): HasMany
    {
        return $this->hasMany(PromptVersion::class, 'created_by');
    }

    public function experiments(): HasMany
    {
        return $this->hasMany(Experiment::class, 'created_by');
    }

    public function evaluations(): HasMany
    {
        return $this->hasMany(Evaluation::class, 'evaluator_id');
    }

    public function libraryEntries(): HasMany
    {
        return $this->hasMany(LibraryEntry::class, 'approved_by');
    }

    public function currentTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'current_team_id');
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(TeamMembership::class);
    }

    public function teams(): BelongsToMany
    {
        return $this->belongsToMany(Team::class, 'team_memberships')
            ->withPivot('role')
            ->withTimestamps();
    }

    public function teamRole(?int $teamId = null): ?string
    {
        return app(TeamPermissionService::class)->roleFor($this, $teamId);
    }

    public function canInTeam(string $ability, ?int $teamId = null): bool
    {
        return app(TeamPermissionService::class)->can($this, $ability, $teamId);
    }

    public function getDisplayNameAttribute(): string
    {
        $displayName = trim(implode(' ', array_filter([
            trim((string) $this->first_name),
            trim((string) $this->last_name),
        ])));

        return $displayName !== '' ? $displayName : (string) $this->name;
    }

    private static function splitName(string $name): array
    {
        $parts = preg_split('/\s+/', trim($name)) ?: [];
        $parts = array_values(array_filter($parts));

        if ($parts === []) {
            return ['', ''];
        }

        $firstName = array_shift($parts) ?: '';
        $lastName = implode(' ', $parts);

        return [$firstName, $lastName];
    }
}
