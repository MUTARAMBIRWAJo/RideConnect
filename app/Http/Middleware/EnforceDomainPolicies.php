<?php

namespace App\Http\Middleware;

use App\Domain\Core\DomainGuard;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnforceDomainPolicies
{
    public function handle(Request $request, Closure $next): Response
    {
        $route = $request->route();
        $action = $route?->getActionName();

        if (is_string($action) && str_contains($action, '@')) {
            [$controllerClass] = explode('@', $action, 2);
            DomainGuard::assertControllerUsesPolicies($controllerClass);
        }

        return $next($request);
    }
}
