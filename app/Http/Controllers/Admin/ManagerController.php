<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreManagerRequest;
use App\Models\Dealer;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class ManagerController extends Controller
{
    public function create(): View
    {
        return view('pages.managers.create', [
            'dealers' => Dealer::query()->orderBy('name')->get(),
        ]);
    }

    public function store(StoreManagerRequest $request): RedirectResponse
    {
        $attributes = $request->validated();
        $manager = User::query()->create([
            'name' => $attributes['name'],
            'email' => $attributes['email'],
            'password' => Hash::make($attributes['password']),
            'dealer_id' => $attributes['dealer_id'],
            'role' => UserRole::Dealer,
        ]);

        return redirect()->route('dealers.show', $manager->dealer_id)
            ->with('success', 'Manager account created successfully.');
    }
}
