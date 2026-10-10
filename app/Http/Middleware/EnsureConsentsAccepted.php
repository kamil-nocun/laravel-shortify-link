<?php

namespace App\Http\Middleware;

use App\Support\ConsentDocuments;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureConsentsAccepted
{
    /**
     * Routes that must work without consent, so that the user's rights never depend on accepting anything.
     */
    private const EXEMPT = ['consent.*', 'content.show', 'front_app', 'logout', 'privacy.export', 'profile.destroy', 'verification.*'];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null || $user->isSuperAdmin() || $request->routeIs(self::EXEMPT) || ConsentDocuments::pendingFor($user)->isEmpty()) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            return response()->json(['message' => __('You have to accept the current documents first.'), 'redirect' => route('consent.show')], 409);
        }

        return redirect()->guest(route('consent.show'), 303);
    }
}
