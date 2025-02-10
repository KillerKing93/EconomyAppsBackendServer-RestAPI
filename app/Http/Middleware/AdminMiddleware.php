<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Auth;

class AdminMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        // Ensure the request is authenticated via Sanctum
        if (!Auth::guard('sanctum')->check()) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $user = Auth::guard('sanctum')->user(); // Get the authenticated user

        // Check if the authenticated user is an admin
        if (!$user || !$user->isAdmin()) {
            Log::info('Unauthorized Admin Access Attempt', ['user' => $user]);
            return response()->json(['message' => 'Forbidden'], 403);
        }

        return $next($request);
    }
}
