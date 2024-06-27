<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class RoleMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @param  string|array  $role
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next, $role)
    {
        $user = Auth::user();

        if (!$user) {
            return response()->json([
                "success" => false,
                "message" => "Unauthenticated.",
            ], 401);
        }

        $roles = is_array($role) ? $role : explode('|', $role);

        if (!$user->hasAnyRole($roles)) {  // Updated to use $roles
            return response()->json([
                "success" => false,
                "message" => "User does not have the right roles.",
            ], 403);
        }

        return $next($request);
    }
}

