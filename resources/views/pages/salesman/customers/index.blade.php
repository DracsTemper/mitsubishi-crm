@extends('layouts.app')
@section('title', 'My Customers')
@section('content')
<x-page-heading title="My Customers" subtitle="Customers assigned directly to you." eyebrow="SALESMAN · OWNERSHIP SCOPE"><a class="btn btn-primary" href="{{ route('salesman.customers.create') }}">Create Customer</a></x-page-heading>

@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif

<form method="GET" action="{{ route('salesman.customers.index') }}" class="panel panel-body mb-3">
    <div class="row g-2 align-items-end">
        <div class="col-lg-7"><label class="form-label" for="q">Search Customers</label><input id="q" name="q" class="form-control @error('q') is-invalid @enderror" value="{{ $filters['q'] }}" placeholder="Name, phone, or email">@error('q')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
        <div class="col-lg-3"><label class="form-label" for="status">Status</label><select id="status" name="status" class="form-select @error('status') is-invalid @enderror"><option value="">All statuses</option>@foreach($statuses as $status)<option value="{{ $status }}" @selected($filters['status'] === $status)>{{ ucfirst($status) }}</option>@endforeach</select>@error('status')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
        <div class="col-lg-2 d-flex gap-2"><button class="btn btn-primary flex-grow-1" type="submit">Filter</button><a class="btn btn-outline-dark" href="{{ route('salesman.customers.index') }}">Clear</a></div>
    </div>
</form>

<div class="owner-strip mb-3">
    <strong>Salesman ownership</strong>
    <div class="text-secondary">Only Customers whose owner is your authenticated Salesman account are shown.</div>
</div>

@if($customers->isEmpty())
    <div class="panel"><div class="panel-body text-center py-5"><div class="eyebrow">MY CUSTOMERS</div><h3 class="mt-2">No Customers registered.</h3><p class="text-secondary mb-3">Create your first Customer to begin managing the relationship.</p><a class="btn btn-primary" href="{{ route('salesman.customers.create') }}">Create Customer</a></div></div>
@else
    <div class="panel"><div class="table-responsive"><table class="table crm-table align-middle mb-0">
        <thead><tr><th>Customer</th><th>Phone</th><th>Email</th><th>Status</th><th>Dealer</th><th class="text-end">Action</th></tr></thead>
        <tbody>@foreach($customers as $customer)<tr>
            <td><a href="{{ route('salesman.customers.show', $customer) }}" class="text-decoration-none"><strong>{{ $customer->name }}</strong></a></td>
            <td>{{ $customer->phone }}</td><td>{{ $customer->email ?: '—' }}</td><td><x-status :status="ucfirst($customer->status)" /></td>
            <td>{{ $customer->salesman->dealer?->name ?? 'No Dealer assigned' }}</td>
            <td class="text-end"><a class="btn btn-sm btn-outline-dark" href="{{ route('salesman.customers.show', $customer) }}">View</a></td>
        </tr>@endforeach</tbody>
    </table></div></div>
    @if($customers->hasPages())<div class="mt-4">{{ $customers->onEachSide(1)->links('pagination::bootstrap-5') }}</div>@endif
@endif
@endsection
