<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Resources\TeamInvitationResource;
use App\Models\User;
use App\Services\TeamInvitationService;
use App\Services\TeamProvisioningService;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class RegisteredUserController extends Controller
{
    /**
     * Display the registration view.
     */
    public function create(Request $request, TeamInvitationService $invitations): Response
    {
        $invitation = $invitations->findByToken($request->string('invitation')->toString());

        return Inertia::render('Auth/Register', [
            'invitation' => $invitation ? (new TeamInvitationResource($invitation))->resolve() : null,
            'invitationToken' => $request->string('invitation')->toString() ?: null,
        ]);
    }

    /**
     * Handle an incoming registration request.
     *
     * @throws ValidationException
     */
    public function store(Request $request, TeamProvisioningService $provisioning): RedirectResponse
    {
        $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|string|lowercase|email|max:255|unique:'.User::class,
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $user = User::create([
            'first_name' => $request->string('first_name')->toString(),
            'last_name' => $request->string('last_name')->toString(),
            'name' => trim($request->string('first_name')->toString().' '.$request->string('last_name')->toString()),
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);

        event(new Registered($user));

        $provisioning->ensurePersonalWorkspace($user);

        Auth::login($user);

        if ($request->filled('invitation_token')) {
            return redirect()->route('team-invitations.show', $request->string('invitation_token')->toString());
        }

        return redirect(route('getting-started', absolute: false));
    }
}
