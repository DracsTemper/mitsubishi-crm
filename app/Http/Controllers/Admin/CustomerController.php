<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\FilterCustomersRequest;
use App\Http\Requests\Admin\StoreCustomerRequest;
use App\Http\Requests\Admin\UpdateCustomerRequest;
use App\Models\Customer;
use App\Models\Dealer;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CustomerController extends Controller
{
    public function index(FilterCustomersRequest $request): View
    {
        $filters = $request->validated();
        $search = trim((string) ($filters['q'] ?? ''));
        $status = $filters['status'] ?? null;
        $dealerId = isset($filters['dealer_id']) ? (int) $filters['dealer_id'] : null;
        $salesmanId = isset($filters['salesman_id']) ? (int) $filters['salesman_id'] : null;
        $queryParameters = array_filter(
            ['q' => $search, 'status' => $status, 'dealer_id' => $dealerId, 'salesman_id' => $salesmanId],
            fn (string|int|null $value): bool => $value !== null && $value !== '',
        );

        return view('pages.customers.index', [
            'customers' => Customer::query()
                ->with('salesman.dealer')
                ->when($search !== '', fn (Builder $query) => $query->where(
                    fn (Builder $searchQuery) => $searchQuery
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%"),
                ))
                ->when($status !== null, fn (Builder $query) => $query->where('status', $status))
                ->when($dealerId !== null, fn (Builder $query) => $query->whereHas(
                    'salesman',
                    fn (Builder $salesmanQuery) => $salesmanQuery->where('dealer_id', $dealerId),
                ))
                ->when($salesmanId !== null, fn (Builder $query) => $query->where('salesman_id', $salesmanId))
                ->orderBy('name')
                ->paginate(10)
                ->appends($queryParameters),
            'filters' => [
                'q' => $search,
                'status' => $status,
                'dealer_id' => $dealerId,
                'salesman_id' => $salesmanId,
            ],
            'statuses' => Customer::STATUSES,
            'dealers' => Dealer::query()->orderBy('name')->get(['id', 'name']),
            'salesmen' => $this->salesmen(),
        ]);
    }

    public function create(): View
    {
        return view('pages.customers.create', [
            'salesmen' => $this->salesmen(),
            'statuses' => Customer::STATUSES,
        ]);
    }

    public function store(StoreCustomerRequest $request): RedirectResponse
    {
        $customer = Customer::query()->create($request->validated());

        return redirect()->route('customers.show', $customer)
            ->with('success', 'Customer created successfully.');
    }

    public function show(Customer $customer): View
    {
        $customer->load('salesman.dealer');

        return view('pages.customers.show', compact('customer'));
    }

    public function edit(Customer $customer): View
    {
        return view('pages.customers.edit', [
            'customer' => $customer,
            'salesmen' => $this->salesmen(),
            'statuses' => Customer::STATUSES,
        ]);
    }

    public function update(UpdateCustomerRequest $request, Customer $customer): RedirectResponse
    {
        $customer->update($request->validated());

        return redirect()->route('customers.show', $customer)
            ->with('success', 'Customer updated successfully.');
    }

    public function destroy(Request $request, Customer $customer): RedirectResponse|JsonResponse
    {
        $customer->delete();

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Customer deleted successfully.',
                'data' => ['id' => $customer->id],
            ]);
        }

        return redirect()->route('customers.index')
            ->with('success', 'Customer deleted successfully.');
    }

    /** @return Collection<int, User> */
    private function salesmen(): Collection
    {
        return User::query()
            ->where('role', UserRole::Salesman->value)
            ->with('dealer:id,name')
            ->orderBy('name')
            ->get();
    }
}
