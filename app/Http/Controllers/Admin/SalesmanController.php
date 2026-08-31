<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreSalesmanRequest;
use App\Models\Dealer;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class SalesmanController extends Controller
{
    public function create(): View
    {
        return view('pages.salesmen.create', [
            'dealers' => Dealer::query()->orderBy('name')->get(),
        ]);
    }

    public function store(StoreSalesmanRequest $request): RedirectResponse
    {
        $attributes = $request->validated();
        $salesman = User::query()->create([
            'name' => $attributes['name'],
            'email' => $attributes['email'],
            'password' => Hash::make($attributes['password']),
            'dealer_id' => $attributes['dealer_id'],
            'role' => UserRole::Salesman,
        ]);

        return redirect()->route('dealers.show', $salesman->dealer_id)
            ->with('success', 'Salesman account created successfully.');
    }
}
