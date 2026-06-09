<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckPermission
{
    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        $user = $request->user();
        
        if (!$user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        if (!$user->role) {
            return response()->json(['error' => 'No role assigned'], 403);
        }

        foreach ($permissions as $permission) {
            if (!$user->hasPermission($permission)) {
                return response()->json([
                    'error' => 'Permission denied',
                    'required' => $permission,
                ], 403);
            }
        }

        return $next($request);
    }
}
