<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\TeamInvitation */
class TeamInvitationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $status = $this->status === 'pending' && $this->expires_at?->isPast()
            ? 'expired'
            : $this->status;
        $publicToken = $this->public_token ?: $this->token_ciphertext;

        return [
            'id' => $this->id,
            'email' => $this->email,
            'role' => $this->role,
            'status' => $status,
            'invited_by' => $this->whenLoaded('inviter', fn () => $this->inviter?->display_name),
            'team' => $this->whenLoaded('team', fn () => [
                'id' => $this->team?->id,
                'name' => $this->team?->name,
                'slug' => $this->team?->slug,
                'description' => $this->team?->description,
            ]),
            'invite_url' => $publicToken ? route('team-invitations.show', $publicToken) : null,
            'created_at' => optional($this->created_at)->toIso8601String(),
            'accepted_at' => optional($this->accepted_at)->toIso8601String(),
            'revoked_at' => optional($this->revoked_at)->toIso8601String(),
            'expires_at' => optional($this->expires_at)->toIso8601String(),
        ];
    }
}
