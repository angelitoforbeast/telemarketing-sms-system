<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePlatformAdmin
{
    /**
     * Ensure the authenticated user is a platform-level admin (no company_id).
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user || $user->company_id !== null) {
            return redirect()->route('dashboard');
        }

        return $next($request);
    }
}
