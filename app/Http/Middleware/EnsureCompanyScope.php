<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureCompanyScope
{
    /**
     * Ensure the authenticated user belongs to a company.
     * Platform-level users (company_id = null) are redirected to the platform admin.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user) {
            return redirect()->route('platform.dashboard');
        }

        // Allow Platform Admin to access company-scoped routes (they can see all)
        if (!$user->company_id) {
            if ($user->hasRole('Platform Admin')) {
                view()->share('currentCompanyId', null);
                view()->share('currentCompany', null);
                return $next($request);
            }
            return redirect()->route('platform.dashboard');
        }

        // Share company_id globally for convenience
        view()->share('currentCompanyId', $user->company_id);
        view()->share('currentCompany', $user->company);

        return $next($request);
    }
}
