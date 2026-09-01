@extends('layouts.app')
@section('title', 'Customers')
@section('content')
<x-page-heading title="Customers" subtitle="Manage customer ownership across the DHS Motors dealer network." eyebrow="ADMIN · CUSTOMER MANAGEMENT">
    <a class="btn btn-primary" href="{{ route('customers.create') }}">+ Add Customer</a>
</x-page-heading>
@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
<form method="GET" action="{{ route('customers.index') }}" class="panel panel-body mb-3">
    <div class="row g-2 align-items-end">
        <div class="col-xl-4 col-md-6"><label class="form-label" for="q">Search Customers</label><input id="q" name="q" class="form-control @error('q') is-invalid @enderror" value="{{ $filters['q'] }}" placeholder="Name, phone, or email">@error('q')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
        <div class="col-xl-2 col-md-6"><label class="form-label" for="status">Status</label><select id="status" name="status" class="form-select @error('status') is-invalid @enderror"><option value="">All statuses</option>@foreach($statuses as $status)<option value="{{ $status }}" @selected($filters['status'] === $status)>{{ ucfirst($status) }}</option>@endforeach</select>@error('status')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
        <div class="col-xl-2 col-md-6"><label class="form-label" for="dealer_id">Dealer</label><select id="dealer_id" name="dealer_id" class="form-select @error('dealer_id') is-invalid @enderror"><option value="">All Dealers</option>@foreach($dealers as $dealer)<option value="{{ $dealer->id }}" @selected($filters['dealer_id'] === $dealer->id)>{{ $dealer->name }}</option>@endforeach</select>@error('dealer_id')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
        <div class="col-xl-2 col-md-6"><label class="form-label" for="salesman_id">Salesman</label><select id="salesman_id" name="salesman_id" class="form-select @error('salesman_id') is-invalid @enderror"><option value="">All Salesmen</option>@foreach($salesmen as $salesman)<option value="{{ $salesman->id }}" @selected($filters['salesman_id'] === $salesman->id)>{{ $salesman->name }} — {{ $salesman->dealer?->name ?? 'Unassigned' }}</option>@endforeach</select>@error('salesman_id')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
        <div class="col-xl-2 d-flex gap-2"><button class="btn btn-primary flex-grow-1" type="submit">Apply Filters</button><a class="btn btn-outline-dark" href="{{ route('customers.index') }}">Clear</a></div>
    </div>
</form>
@if($customers->isEmpty())
    <div class="panel"><div class="panel-body text-center py-5"><div class="eyebrow">CUSTOMER NETWORK</div><h3 class="mt-2">No customers yet.</h3><p class="text-secondary">Create the first CRM customer and assign a Salesman owner.</p><a class="btn btn-primary" href="{{ route('customers.create') }}">Create Customer</a></div></div>
@else
    <div class="panel"><div class="table-responsive"><table class="table crm-table align-middle mb-0">
        <thead><tr><th>Customer</th><th>Phone</th><th>Email</th><th>Salesman</th><th>Dealer</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
        <tbody>@foreach($customers as $customer)<tr>
            <td><a href="{{ route('customers.show', $customer) }}" class="text-decoration-none text-dark"><strong>{{ $customer->name }}</strong></a></td>
            <td>{{ $customer->phone }}</td><td>{{ $customer->email ?: '—' }}</td><td>{{ $customer->salesman->name }}</td>
            <td>{{ $customer->salesman->dealer?->name ?? 'No Dealer assigned' }}</td><td><x-status :status="ucfirst($customer->status)" /></td>
            <td class="text-end"><a class="btn btn-sm btn-outline-dark" href="{{ route('customers.show', $customer) }}">View</a> <a class="btn btn-sm btn-outline-dark" href="{{ route('customers.edit', $customer) }}">Edit</a></td>
        </tr>@endforeach</tbody>
    </table></div></div>
    @if($customers->hasPages())<div class="mt-4">{{ $customers->onEachSide(1)->links('pagination::bootstrap-5') }}</div>@endif
@endif
@endsection
