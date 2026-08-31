@extends('layouts.app')

@section('title', $salesman->name)

@section('content')
<a href="{{ route('dealer.salesmen.index') }}" class="text-secondary text-decoration-none">← Sales Team</a>

<div class="profile-hero my-3">
    <span class="avatar red">{{ strtoupper(substr($salesman->name, 0, 2)) }}</span>
    <div style="z-index:1">
        <div class="eyebrow">SALESMAN · READ ONLY</div>
        <h2>{{ $salesman->name }}</h2>
        <div class="text-white-50">{{ $salesman->email }} · {{ $salesman->dealer->name }}</div>
    </div>
</div>

<div class="panel">
    <div class="panel-header"><div><div class="eyebrow">ACCOUNT INFORMATION</div><h3 class="panel-title mt-1">Salesman details</h3></div><x-status status="Active" /></div>
    <div class="panel-body">
        <div class="info-list">
            <div class="info-item"><label>Name</label><strong>{{ $salesman->name }}</strong></div>
            <div class="info-item"><label>Email</label><strong>{{ $salesman->email }}</strong></div>
            <div class="info-item"><label>Dealer</label><strong>{{ $salesman->dealer->name }}</strong></div>
            <div class="info-item"><label>Role</label><strong>Salesman</strong></div>
            <div class="info-item"><label>Customers</label><strong>{{ $salesman->customers_count }}</strong></div>
            <div class="info-item"><label>Access</label><strong>Read only</strong></div>
        </div>
    </div>
</div>
@endsection
