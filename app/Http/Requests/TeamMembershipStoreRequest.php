<?php

namespace App\Http\Requests;

use App\Services\TeamPermissionService;
use Illuminate\Validation\Rule;

class TeamMembershipStoreRequest extends TeamAwareRequest
{
    public function authorize(): bool
    {
        return $this->authorizeAbility('manage_members');
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'email:rfc'],
            'role' => ['required', Rule::in(app(TeamPermissionService::class)->validRoles())],
        ];
    }
}
