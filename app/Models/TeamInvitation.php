<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TeamInvitation extends Model
{
    use HasFactory;

    public ?string $public_token = null;

    protected $fillable = [
        'team_id',
        'email',
        'role',
        'token',
        'token_ciphertext',
        'status',
        'invited_by',
        'accepted_at',
        'revoked_at',
        'expires_at',
    ];

    protected $hidden = [
        'token',
        'token_ciphertext',
    ];

    protected function casts(): array
    {
        return [
            'token_ciphertext' => 'encrypted',
            'accepted_at' => 'datetime',
            'revoked_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function inviter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }
}
