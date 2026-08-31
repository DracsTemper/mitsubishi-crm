<?php

namespace App\Http\Controllers\Dealer;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Dealer\FilterCustomersRequest;
use App\Models\Customer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CustomerController extends Controller
{
    public function index(FilterCustomersRequest $request): View
    {
        $filters = $request->validated();
        $search = trim((string) ($filters['q'] ?? ''));
        $status = $filters['status'] ?? null;
        $queryParameters = array_filter(
            ['q' => $search, 'status' => $status],
            fn (?string $value): bool => $value !== null && $value !== '',
        );

        return view('pages.dealer.customers.index', [
            'customers' => $this->dealerCustomers((int) $request->user()->dealer_id)
                ->when($search !== '', fn (Builder $query) => $query->where(
                    fn (Builder $searchQuery) => $searchQuery
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%"),
                ))
                ->when($status !== null, fn (Builder $query) => $query->where('status', $status))
                ->latest()
                ->paginate(10)
                ->appends($queryParameters),
            'filters' => ['q' => $search, 'status' => $status],
            'statuses' => Customer::STATUSES,
        ]);
    }

    public function show(Request $request, string $customer): View
    {
        $customer = $this->dealerCustomers((int) $request->user()->dealer_id)
            ->findOrFail($customer);

        return view('pages.dealer.customers.show', compact('customer'));
    }

    /** @return Builder<Customer> */
    private function dealerCustomers(int $dealerId): Builder
    {
        return Customer::query()
            ->whereHas('salesman', fn (Builder $query) => $query
                ->where('dealer_id', $dealerId)
                ->where('role', UserRole::Salesman->value))
            ->with('salesman.dealer');
    }
}
