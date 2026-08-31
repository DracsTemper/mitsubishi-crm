<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureManagerDealerScope
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        abort_unless(
            $user?->role === UserRole::Dealer && $user->dealer_id !== null,
            Response::HTTP_FORBIDDEN,
        );

        if ($request->has('dealer_id') && ! $request->routeIs('dealer.salesmen.store')) {
            abort_unless(
                filter_var($request->input('dealer_id'), FILTER_VALIDATE_INT) === (int) $user->dealer_id,
                Response::HTTP_FORBIDDEN,
            );
        }

        $request->attributes->set('managerDealer', $user->dealer);

        return $next($request);
    }
}
