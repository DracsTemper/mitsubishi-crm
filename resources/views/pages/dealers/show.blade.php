@extends('layouts.app')

@section('title', $dealer->name)

@section('content')
<a href="{{ route('dealers.index') }}" class="text-secondary text-decoration-none">← Dealer Network</a>

@if(session('success'))
    <div class="alert alert-success mt-3">{{ session('success') }}</div>
@endif

<div class="profile-hero my-3">
    <span class="avatar red">{{ strtoupper(substr($dealer->code, 0, 2)) }}</span>
    <div style="z-index:1">
        <div class="eyebrow">{{ strtoupper($dealer->status->value) }} ORGANIZATION · {{ strtoupper($dealer->city) }}</div>
        <h2>{{ $dealer->name }}</h2>
        <div class="text-white-50">{{ $dealer->code }} · {{ $dealer->phone ?: 'No phone' }} · {{ $dealer->email ?: 'No email' }}</div>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-8">
        <div class="panel h-100">
            <div class="panel-header">
                <div><div class="eyebrow">ORGANIZATION PROFILE</div><h3 class="panel-title mt-1">Dealer details</h3></div>
                <x-status :status="ucfirst($dealer->status->value)" />
            </div>
            <div class="panel-body">
                <div class="info-list">
                    <div class="info-item"><label>Name</label><strong>{{ $dealer->name }}</strong></div>
                    <div class="info-item"><label>Dealer code</label><strong>{{ $dealer->code }}</strong></div>
                    <div class="info-item"><label>Phone</label><strong>{{ $dealer->phone ?: '—' }}</strong></div>
                    <div class="info-item"><label>Email</label><strong>{{ $dealer->email ?: '—' }}</strong></div>
                    <div class="info-item"><label>City</label><strong>{{ $dealer->city }}</strong></div>
                    <div class="info-item"><label>Address</label><strong>{{ $dealer->address ?: '—' }}</strong></div>
                    <div class="info-item"><label>Status</label><strong>{{ ucfirst($dealer->status->value) }}</strong></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="panel">
            <div class="panel-header"><h3 class="panel-title">Admin actions</h3></div>
            <div class="panel-body">
                <a class="btn btn-primary w-100" href="{{ route('dealers.edit', $dealer) }}">Edit Dealer</a>
                <form class="mt-2" method="POST" action="{{ route('dealers.status', $dealer) }}">
                    @csrf
                    @method('PATCH')
                    <input type="hidden" name="status" value="{{ $dealer->status === \App\Enums\DealerStatus::Active ? 'inactive' : 'active' }}">
                    <button class="btn btn-outline-dark w-100" type="submit">
                        {{ $dealer->status === \App\Enums\DealerStatus::Active ? 'Deactivate Dealer' : 'Activate Dealer' }}
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="panel mt-3" data-assignment-panel="managers">
    <div class="panel-header">
        <div>
            <div class="eyebrow">MANAGEMENT TEAM</div>
            <h3 class="panel-title mt-1">Managers</h3>
        </div>
        <div class="d-flex align-items-center gap-2"><span class="text-secondary">{{ $assignedDealerUsers->count() }} assigned</span><a class="btn btn-sm btn-primary" href="{{ route('managers.create', ['dealer_id' => $dealer->id]) }}">Create Manager</a></div>
    </div>
    <div class="panel-body">
        <form method="POST" action="{{ route('dealers.users.assign', $dealer) }}" class="row g-2 align-items-start mb-4" data-crm-ajax data-refresh-selector="[data-assignment-panel='managers']">
            @csrf
            <div class="col-md">
                <label class="form-label" for="user_id">Assign an existing Manager</label>
                <select id="user_id" name="user_id" class="form-select @error('user_id') is-invalid @enderror" required>
                    <option value="">Select a Manager account</option>
                    @foreach($assignableUsers as $user)
                        <option value="{{ $user->id }}" @selected(old('user_id') == $user->id)>
                            {{ $user->name }} · {{ $user->email }}{{ $user->dealer ? ' · currently '.$user->dealer->name : ' · unassigned' }}
                        </option>
                    @endforeach
                </select>
                @error('user_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                @if($assignableUsers->isEmpty())
                    <div class="form-text">Every existing Manager account is already assigned to this organization.</div>
                @else
                    <div class="form-text">Selecting an account assigned elsewhere will move it to this Dealer.</div>
                @endif
            </div>
            <div class="col-md-auto" style="padding-top: 2rem">
                <button class="btn btn-primary" type="submit" @disabled($assignableUsers->isEmpty())>Assign User</button>
            </div>
        </form>

        @if($assignedDealerUsers->isEmpty())
            <div class="text-center py-4 border rounded">
                <h4 class="h6 mb-1">No Managers assigned</h4>
                <p class="text-secondary mb-0">Create a Manager or assign an existing Manager account to this organization.</p>
            </div>
        @else
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead>
                        <tr><th>Name</th><th>Email</th><th>Role</th><th class="text-end">Action</th></tr>
                    </thead>
                    <tbody>
                        @foreach($assignedDealerUsers as $user)
                            <tr>
                                <td><strong>{{ $user->name }}</strong></td>
                                <td>{{ $user->email }}</td>
                                <td><x-status status="Manager" /></td>
                                <td class="text-end">
                                    <form method="POST" action="{{ route('dealers.users.unassign', [$dealer, $user]) }}" data-crm-ajax data-refresh-selector="[data-assignment-panel='managers']" data-confirm-title="Unassign Manager?" data-confirm-message="This Manager will lose access to this Dealer workspace." data-confirm-button="Unassign" data-confirm-style="danger">
                                        @csrf
                                        @method('DELETE')
                                        <button class="btn btn-sm btn-outline-dark" type="submit">Unassign</button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>

<div class="panel mt-3" data-assignment-panel="salesmen">
    <div class="panel-header">
        <div>
            <div class="eyebrow">SALES TEAM</div>
            <h3 class="panel-title mt-1">Dealer Salesmen</h3>
        </div>
        <div class="d-flex align-items-center gap-2"><span class="text-secondary">{{ $assignedSalesmen->count() }} assigned</span><a class="btn btn-sm btn-primary" href="{{ route('salesmen.create', ['dealer_id' => $dealer->id]) }}">Create Salesman</a></div>
    </div>
    <div class="panel-body">
        <form method="POST" action="{{ route('dealers.salesmen.assign', $dealer) }}" class="row g-2 align-items-start mb-4" data-crm-ajax data-refresh-selector="[data-assignment-panel='salesmen']">
            @csrf
            <div class="col-md">
                <label class="form-label" for="salesman_user_id">Assign an existing Salesman</label>
                <select id="salesman_user_id" name="user_id" class="form-select @error('user_id', 'assignSalesman') is-invalid @enderror" required>
                    <option value="">Select a Salesman account</option>
                    @foreach($assignableSalesmen as $salesman)
                        <option value="{{ $salesman->id }}" @selected(old('user_id') == $salesman->id)>
                            {{ $salesman->name }} · {{ $salesman->email }}{{ $salesman->dealer ? ' · currently '.$salesman->dealer->name : ' · unassigned' }}
                        </option>
                    @endforeach
                </select>
                @error('user_id', 'assignSalesman')<div class="invalid-feedback">{{ $message }}</div>@enderror
                @if($assignableSalesmen->isEmpty())
                    <div class="form-text">Every existing Salesman account is already assigned to this organization.</div>
                @else
                    <div class="form-text">Selecting a Salesman assigned elsewhere will move them to this Dealer.</div>
                @endif
            </div>
            <div class="col-md-auto" style="padding-top: 2rem">
                <button class="btn btn-primary" type="submit" @disabled($assignableSalesmen->isEmpty())>Assign Salesman</button>
            </div>
        </form>

        @if($assignedSalesmen->isEmpty())
            <div class="text-center py-4 border rounded">
                <h4 class="h6 mb-1">No Salesmen assigned</h4>
                <p class="text-secondary mb-0">Choose an existing Salesman account above to add it to this Dealer team.</p>
            </div>
        @else
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead>
                        <tr><th>Name</th><th>Email</th><th>Role</th><th class="text-end">Action</th></tr>
                    </thead>
                    <tbody>
                        @foreach($assignedSalesmen as $salesman)
                            <tr>
                                <td><strong>{{ $salesman->name }}</strong></td>
                                <td>{{ $salesman->email }}</td>
                                <td><x-status status="Salesman" /></td>
                                <td class="text-end">
                                    <form method="POST" action="{{ route('dealers.salesmen.unassign', [$dealer, $salesman]) }}" data-crm-ajax data-refresh-selector="[data-assignment-panel='salesmen']" data-confirm-title="Unassign Salesman?" data-confirm-message="This Salesman will no longer belong to this Dealer organization." data-confirm-button="Unassign" data-confirm-style="danger">
                                        @csrf
                                        @method('DELETE')
                                        <button class="btn btn-sm btn-outline-dark" type="submit">Unassign</button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>
@endsection
