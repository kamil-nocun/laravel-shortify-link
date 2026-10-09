<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureAccountIsNotBlocked
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()?->isBlocked()) {
            return $next($request);
        }

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        if ($request->expectsJson()) {
            abort(401);
        }

        return redirect()->route('login', status: 303)
            ->withErrors(['email' => __('Your account has been blocked.')]);
    }
}
