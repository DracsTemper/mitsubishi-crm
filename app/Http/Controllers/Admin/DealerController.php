<?php

namespace App\Http\Controllers\Admin;

use App\Enums\DealerStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AssignDealerSalesmanRequest;
use App\Http\Requests\Admin\AssignDealerUserRequest;
use App\Http\Requests\Admin\StoreDealerRequest;
use App\Http\Requests\Admin\UpdateDealerRequest;
use App\Http\Requests\Admin\UpdateDealerStatusRequest;
use App\Models\Dealer;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

class DealerController extends Controller
{
    public function index(): View
    {
        return view('pages.dealers.index', [
            'dealers' => Dealer::query()->orderBy('name')->paginate(8),
        ]);
    }

    public function create(): View
    {
        return view('pages.dealers.create', ['statuses' => DealerStatus::cases()]);
    }

    public function store(StoreDealerRequest $request): RedirectResponse
    {
        $dealer = Dealer::query()->create($request->validated());

        return redirect()->route('dealers.show', $dealer)
            ->with('success', 'Dealer organization created successfully.');
    }

    public function show(Dealer $dealer): View
    {
        return view('pages.dealers.show', [
            'dealer' => $dealer,
            'assignedDealerUsers' => $dealer->users()
                ->where('role', UserRole::Dealer->value)
                ->orderBy('name')
                ->get(),
            'assignableUsers' => User::query()
                ->where('role', UserRole::Dealer->value)
                ->where(function ($query) use ($dealer): void {
                    $query->whereNull('dealer_id')
                        ->orWhere('dealer_id', '!=', $dealer->id);
                })
                ->with('dealer:id,name')
                ->orderBy('name')
                ->get(),
            'assignedSalesmen' => $dealer->users()
                ->where('role', UserRole::Salesman->value)
                ->orderBy('name')
                ->get(),
            'assignableSalesmen' => User::query()
                ->where('role', UserRole::Salesman->value)
                ->where(function ($query) use ($dealer): void {
                    $query->whereNull('dealer_id')
                        ->orWhere('dealer_id', '!=', $dealer->id);
                })
                ->with('dealer:id,name')
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function assignUser(AssignDealerUserRequest $request, Dealer $dealer): RedirectResponse|JsonResponse
    {
        $user = User::query()
            ->whereKey($request->validated('user_id'))
            ->where('role', UserRole::Dealer->value)
            ->firstOrFail();

        $user->update(['dealer_id' => $dealer->id]);

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => "{$user->name} was assigned to {$dealer->name}.",
                'data' => ['refresh_url' => route('dealers.show', $dealer)],
            ]);
        }

        return redirect()->route('dealers.show', $dealer)
            ->with('success', "{$user->name} was assigned to {$dealer->name}.");
    }

    public function unassignUser(Dealer $dealer, User $user): RedirectResponse|JsonResponse
    {
        abort_unless(
            $user->role === UserRole::Dealer && (int) $user->dealer_id === $dealer->id,
            404,
        );

        $user->update(['dealer_id' => null]);

        if (request()->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => "{$user->name} was unassigned from {$dealer->name}.",
                'data' => ['refresh_url' => route('dealers.show', $dealer)],
            ]);
        }

        return redirect()->route('dealers.show', $dealer)
            ->with('success', "{$user->name} was unassigned from {$dealer->name}.");
    }

    public function assignSalesman(
        AssignDealerSalesmanRequest $request,
        Dealer $dealer,
    ): RedirectResponse|JsonResponse {
        $salesman = User::query()
            ->whereKey($request->validated('user_id'))
            ->where('role', UserRole::Salesman->value)
            ->firstOrFail();

        $salesman->update(['dealer_id' => $dealer->id]);

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => "{$salesman->name} was assigned to {$dealer->name}.",
                'data' => ['refresh_url' => route('dealers.show', $dealer)],
            ]);
        }

        return redirect()->route('dealers.show', $dealer)
            ->with('success', "{$salesman->name} was assigned to {$dealer->name}.");
    }

    public function unassignSalesman(Dealer $dealer, User $user): RedirectResponse|JsonResponse
    {
        abort_unless(
            $user->role === UserRole::Salesman && (int) $user->dealer_id === $dealer->id,
            404,
        );

        $user->update(['dealer_id' => null]);

        if (request()->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => "{$user->name} was unassigned from {$dealer->name}.",
                'data' => ['refresh_url' => route('dealers.show', $dealer)],
            ]);
        }

        return redirect()->route('dealers.show', $dealer)
            ->with('success', "{$user->name} was unassigned from {$dealer->name}.");
    }

    public function edit(Dealer $dealer): View
    {
        return view('pages.dealers.edit', [
            'dealer' => $dealer,
            'statuses' => DealerStatus::cases(),
        ]);
    }

    public function update(UpdateDealerRequest $request, Dealer $dealer): RedirectResponse
    {
        $dealer->update($request->validated());

        return redirect()->route('dealers.show', $dealer)
            ->with('success', 'Dealer organization updated successfully.');
    }

    public function updateStatus(UpdateDealerStatusRequest $request, Dealer $dealer): RedirectResponse
    {
        $dealer->update($request->validated());

        return redirect()->route('dealers.show', $dealer)
            ->with('success', 'Dealer status updated successfully.');
    }
}
