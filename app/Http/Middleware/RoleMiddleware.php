<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    public function handle(Request $request, Closure $next, string $role): Response
    {
        $requiredRole = UserRole::tryFrom($role);

        abort_unless(
            $requiredRole !== null && $request->user()?->role === $requiredRole,
            Response::HTTP_FORBIDDEN,
        );

        return $next($request);
    }
}
