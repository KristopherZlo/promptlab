<?php

namespace App\Http\Requests;

use App\Services\TeamPermissionService;
use Illuminate\Validation\Rule;

class TeamInvitationStoreRequest extends TeamAwareRequest
{
    public function authorize(): bool
    {
        return $this->authorizeAbility('manage_members');
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email:rfc', 'max:255'],
            'role' => ['required', Rule::in(app(TeamPermissionService::class)->validRoles())],
        ];
    }
}
