<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Services\TeamInvitationService;
use App\Services\WorkspaceJourneyService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(Request $request, TeamInvitationService $invitations): Response
    {
        $invitation = $invitations->findByToken($request->string('invitation')->toString());

        return Inertia::render('Auth/Login', [
            'canResetPassword' => Route::has('password.request'),
            'status' => session('status'),
            'invitation' => $invitation ? (new \App\Http\Resources\TeamInvitationResource($invitation))->resolve() : null,
        ]);
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request, WorkspaceJourneyService $journey): RedirectResponse
    {
        $request->authenticate();

        $request->session()->regenerate();

        if ($request->filled('invitation_token')) {
            return redirect()->route('team-invitations.show', $request->string('invitation_token')->toString());
        }

        return redirect()->intended(route($journey->landingRouteName(), absolute: false));
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }
}
