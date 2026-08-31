<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use App\Models\Dealer;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSalesmanDealerScope
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        abort_unless(
            $user?->role === UserRole::Salesman && $user->dealer_id !== null,
            Response::HTTP_FORBIDDEN,
        );

        $dealerId = (int) $user->dealer_id;
        $routeDealer = $request->route('dealer');

        if ($routeDealer !== null) {
            $routeDealerId = $routeDealer instanceof Dealer
                ? (int) $routeDealer->getKey()
                : filter_var($routeDealer, FILTER_VALIDATE_INT);

            abort_unless($routeDealerId === $dealerId, Response::HTTP_FORBIDDEN);
        }

        if ($request->has('dealer_id') && ! $request->routeIs(
            'salesman.customers.store',
            'salesman.customers.update',
            'salesman.customers.destroy',
            'salesman.customers.test-drives.store',
            'salesman.test-drives.update',
            'salesman.customers.test-drives.book.store',
        )) {
            abort_unless(
                filter_var($request->input('dealer_id'), FILTER_VALIDATE_INT) === $dealerId,
                Response::HTTP_FORBIDDEN,
            );
        }

        $request->attributes->set('salesmanDealer', $user->dealer);

        return $next($request);
    }
}
