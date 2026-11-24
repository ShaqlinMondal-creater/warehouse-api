<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Guest-only routes (the guest panel API). */
class EnsureGuest
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->role !== 'guest') {
            return response()->json(['message' => 'This area is for guest accounts only.'], 403);
        }

        return $next($request);
    }
}
