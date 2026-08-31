@extends('layouts.app')
@section('title', $customer->name)
@section('content')
<a href="{{ route('dealer.customers.index') }}" class="text-secondary text-decoration-none">← Dealer Customers</a>
<div class="profile-hero my-3"><span class="avatar red">{{ strtoupper(substr($customer->name, 0, 2)) }}</span><div style="z-index:1"><div class="eyebrow">{{ strtoupper($customer->status) }} CUSTOMER</div><h2>{{ $customer->name }}</h2><div class="text-white-50">{{ $customer->phone }} · {{ $customer->email ?: 'No email' }}</div></div></div>
<div class="row g-3">
    <div class="col-lg-8"><div class="panel h-100"><div class="panel-header"><div><div class="eyebrow">CUSTOMER PROFILE</div><h3 class="panel-title mt-1">Contact information</h3></div><x-status :status="ucfirst($customer->status)" /></div><div class="panel-body"><div class="info-list">
        <div class="info-item"><label>Name</label><strong>{{ $customer->name }}</strong></div><div class="info-item"><label>Phone</label><strong>{{ $customer->phone }}</strong></div><div class="info-item"><label>Email</label><strong>{{ $customer->email ?: '—' }}</strong></div><div class="info-item"><label>City</label><strong>{{ $customer->city ?: '—' }}</strong></div><div class="info-item"><label>Address</label><strong>{{ $customer->address ?: '—' }}</strong></div><div class="info-item"><label>Status</label><strong>{{ ucfirst($customer->status) }}</strong></div>
    </div></div></div></div>
    <div class="col-lg-4"><div class="panel"><div class="panel-header"><h3 class="panel-title">Ownership context</h3></div><div class="panel-body info-list"><div class="info-item"><label>Salesman</label><strong>{{ $customer->salesman->name }}</strong></div><div class="info-item"><label>Dealer</label><strong>{{ $customer->salesman->dealer->name }}</strong></div><div class="info-item"><label>Manager access</label><strong>Read only</strong></div></div></div></div>
</div>
@endsection
