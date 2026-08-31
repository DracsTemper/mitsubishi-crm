<?php

namespace App\Http\Controllers\Salesman;

use App\Http\Controllers\Controller;
use App\Http\Requests\Salesman\FilterCustomersRequest;
use App\Http\Requests\Salesman\StoreCustomerRequest;
use App\Http\Requests\Salesman\UpdateCustomerRequest;
use App\Models\Customer;
use Illuminate\Database\Eloquent\Builder;
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
        $queryParameters = array_filter(
            ['q' => $search, 'status' => $status],
            fn (?string $value): bool => $value !== null && $value !== '',
        );

        return view('pages.salesman.customers.index', [
            'customers' => $this->ownedCustomers($request)
                ->when($search !== '', fn (Builder $query) => $query->where(
                    fn (Builder $searchQuery) => $searchQuery
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%"),
                ))
                ->when($status !== null, fn (Builder $query) => $query->where('status', $status))
                ->with('salesman.dealer')
                ->latest()
                ->paginate(10)
                ->appends($queryParameters),
            'filters' => ['q' => $search, 'status' => $status],
            'statuses' => Customer::STATUSES,
        ]);
    }

    public function show(Request $request, string $customer): View
    {
        $customer = $this->ownedCustomers($request)
            ->with(['salesman.dealer', 'testDrives' => fn ($query) => $query->with(['vehicle', 'booking'])->latest('scheduled_date')])
            ->findOrFail($customer);

        return view('pages.salesman.customers.show', compact('customer'));
    }

    public function create(Request $request): View
    {
        return view('pages.salesman.customers.create', [
            'salesman' => $request->user(),
            'statuses' => Customer::STATUSES,
        ]);
    }

    public function store(StoreCustomerRequest $request): RedirectResponse
    {
        $customer = Customer::query()->create([
            ...$request->validated(),
            'salesman_id' => $request->user()->id,
        ]);

        return redirect()->route('salesman.customers.show', $customer)
            ->with('success', 'Customer created successfully.');
    }

    public function edit(Request $request, string $customer): View
    {
        $customer = $this->ownedCustomers($request)
            ->with('salesman.dealer')
            ->whereKey($customer)
            ->firstOrFail();

        return view('pages.salesman.customers.edit', [
            'customer' => $customer,
            'statuses' => Customer::STATUSES,
        ]);
    }

    public function update(UpdateCustomerRequest $request, string $customer): RedirectResponse
    {
        $customer = $this->ownedCustomers($request)
            ->whereKey($customer)
            ->firstOrFail();
        $customer->update($request->validated());

        return redirect()->route('salesman.customers.show', $customer)
            ->with('success', 'Customer updated successfully.');
    }

    public function destroy(Request $request, string $customer): RedirectResponse|JsonResponse
    {
        $customer = $this->ownedCustomers($request)
            ->whereKey($customer)
            ->firstOrFail();
        $customer->delete();

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Customer deleted successfully.',
                'data' => ['id' => $customer->id],
            ]);
        }

        return redirect()->route('salesman.customers.index')
            ->with('success', 'Customer deleted successfully.');
    }

    /** @return Builder<Customer> */
    private function ownedCustomers(Request $request): Builder
    {
        return Customer::query()->where('salesman_id', $request->user()->id);
    }
}
