<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRole
{
    /**
     * Handle an incoming request.
     *
     * Usage: ->middleware('role:owner') or ->middleware('role:owner,admin')
     *
     * @param  \Closure(Request): (Response)  $next
     * @param  string  ...$roles  Allowed role names
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'message' => 'Unauthenticated.',
            ], 401);
        }

        // Ensure role relationship is loaded
        if (!$user->relationLoaded('role')) {
            $user->load('role');
        }

        if (!$user->hasRole(...$roles)) {
            return response()->json([
                'message' => 'Forbidden. You do not have the required role.',
            ], 403);
        }

        return $next($request);
    }
}
