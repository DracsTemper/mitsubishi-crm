@extends('layouts.app')

@section('title', 'Sales Team')

@section('content')
<x-page-heading title="{{ $dealer->name }} Sales Team" subtitle="Salesmen registered under your Dealer organization." eyebrow="DEALER · OUTLET SCOPE">
    <a class="btn btn-primary" href="{{ route('dealer.salesmen.create') }}">+ Create Salesman</a>
</x-page-heading>

@if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif

@if($salesmen->isEmpty())
    <div class="panel"><div class="panel-body text-center py-5"><h3 class="h6">No Salesmen registered</h3><p class="text-secondary mb-3">Create the first Salesman account for {{ $dealer->name }}.</p><a class="btn btn-primary" href="{{ route('dealer.salesmen.create') }}">Create Salesman</a></div></div>
@else
    <div class="row g-3">
        @foreach($salesmen as $salesman)
            <div class="col-md-6">
                <div class="panel h-100">
                    <div class="panel-header"><div><div class="eyebrow">SALES EXECUTIVE</div><h3 class="panel-title mt-1">{{ $salesman->name }}</h3></div><x-status status="Active" /></div>
                    <div class="panel-body info-list">
                        <div class="info-item"><label>Email</label><strong>{{ $salesman->email }}</strong></div>
                        <div class="info-item"><label>Customers</label><strong>{{ $salesman->customers_count }}</strong></div>
                        <div class="info-item"><label>Dealer</label><strong>{{ $dealer->name }}</strong></div>
                        <div class="info-item"><label>Role</label><strong>Salesman</strong></div>
                    </div>
                    <div class="panel-body pt-0"><a class="btn btn-outline-dark w-100" href="{{ route('dealer.salesmen.show', $salesman) }}">View Salesman</a></div>
                </div>
            </div>
        @endforeach
    </div>
@endif
@endsection
