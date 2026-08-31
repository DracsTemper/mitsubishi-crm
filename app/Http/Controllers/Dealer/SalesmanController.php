<?php

namespace App\Http\Controllers\Dealer;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Dealer\StoreSalesmanRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class SalesmanController extends Controller
{
    public function index(Request $request): View
    {
        return view('pages.dealer.team', [
            'dealer' => $request->attributes->get('managerDealer'),
            'salesmen' => $this->scopedSalesmen($request)
                ->withCount('customers')
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function show(Request $request, string $user): View
    {
        $salesman = $this->scopedSalesmen($request)
            ->with('dealer')
            ->withCount('customers')
            ->whereKey($user)
            ->firstOrFail();

        return view('pages.dealer.salesmen.show', compact('salesman'));
    }

    public function create(Request $request): View
    {
        return view('pages.dealer.salesmen.create', [
            'dealer' => $request->attributes->get('managerDealer'),
        ]);
    }

    public function store(StoreSalesmanRequest $request): RedirectResponse
    {
        $attributes = $request->validated();

        User::query()->create([
            'name' => $attributes['name'],
            'email' => $attributes['email'],
            'password' => Hash::make($attributes['password']),
            'dealer_id' => $request->user()->dealer_id,
            'role' => UserRole::Salesman,
        ]);

        return redirect()->route('dealer.team')
            ->with('success', 'Salesman account created successfully.');
    }

    /** @return Builder<User> */
    private function scopedSalesmen(Request $request): Builder
    {
        return User::query()
            ->where('dealer_id', $request->user()->dealer_id)
            ->where('role', UserRole::Salesman->value);
    }
}
