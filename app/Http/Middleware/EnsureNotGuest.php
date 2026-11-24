<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Blocks guest accounts (users.role = guest) from owner-side API routes.
 */
class EnsureNotGuest
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->role === 'guest') {
            return response()->json(['message' => 'Guest accounts cannot access this area.'], 403);
        }

        return $next($request);
    }
}
