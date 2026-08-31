@extends('layouts.app')
@section('title', $customer->name)
@section('content')
<a href="{{ route('customers.index') }}" class="text-secondary text-decoration-none">← Customers</a>
@if(session('success'))<div class="alert alert-success mt-3">{{ session('success') }}</div>@endif
<div data-crm-record data-record-id="{{ $customer->id }}">
<div class="profile-hero my-3"><span class="avatar red">{{ strtoupper(substr($customer->name, 0, 2)) }}</span><div style="z-index:1"><div class="eyebrow">{{ strtoupper($customer->status) }} CUSTOMER</div><h2>{{ $customer->name }}</h2><div class="text-white-50">{{ $customer->phone }} · {{ $customer->email ?: 'No email' }}</div></div></div>
<div class="row g-3">
    <div class="col-lg-8"><div class="panel h-100"><div class="panel-header"><div><div class="eyebrow">CUSTOMER PROFILE</div><h3 class="panel-title mt-1">Contact information</h3></div><x-status :status="ucfirst($customer->status)" /></div><div class="panel-body"><div class="info-list">
        <div class="info-item"><label>Name</label><strong>{{ $customer->name }}</strong></div><div class="info-item"><label>Phone</label><strong>{{ $customer->phone }}</strong></div><div class="info-item"><label>Email</label><strong>{{ $customer->email ?: '—' }}</strong></div><div class="info-item"><label>City</label><strong>{{ $customer->city ?: '—' }}</strong></div><div class="info-item"><label>Address</label><strong>{{ $customer->address ?: '—' }}</strong></div><div class="info-item"><label>Status</label><strong>{{ ucfirst($customer->status) }}</strong></div>
    </div></div></div></div>
    <div class="col-lg-4"><div class="panel mb-3"><div class="panel-header"><h3 class="panel-title">Ownership</h3></div><div class="panel-body info-list"><div class="info-item"><label>Salesman</label><strong>{{ $customer->salesman->name }}</strong></div><div class="info-item"><label>Salesman email</label><strong>{{ $customer->salesman->email }}</strong></div><div class="info-item"><label>Dealer</label><strong>{{ $customer->salesman->dealer?->name ?? 'No Dealer assigned' }}</strong></div></div></div>
        <div class="panel"><div class="panel-header"><h3 class="panel-title">Admin actions</h3></div><div class="panel-body"><a class="btn btn-primary w-100" href="{{ route('customers.edit', $customer) }}">Edit Customer</a><form class="mt-2" method="POST" action="{{ route('customers.destroy', $customer) }}" data-crm-ajax data-remove-record="true" data-success-url="{{ route('customers.index') }}" data-success-label="Back to Customers" data-confirm-title="Delete Customer?" data-confirm-message="This Customer record will be permanently deleted. This action cannot be undone." data-confirm-button="Delete Customer" data-confirm-style="danger">@csrf @method('DELETE')<button class="btn btn-outline-danger w-100" type="submit">Delete Customer</button></form></div></div>
    </div>
</div>
</div>
@endsection
