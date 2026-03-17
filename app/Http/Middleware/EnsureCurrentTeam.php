<?php

namespace App\Http\Middleware;

use App\Services\CurrentTeamResolver;
use App\Services\TeamProvisioningService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureCurrentTeam
{
    public function __construct(
        private readonly CurrentTeamResolver $resolver,
        private readonly TeamProvisioningService $provisioning,
    ) {
    }

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()) {
            $team = $this->resolver->ensureFor($request->user());

            if (! $team) {
                $this->provisioning->ensurePersonalWorkspace($request->user());
            }
        }

        return $next($request);
    }
}
