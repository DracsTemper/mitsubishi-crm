@extends('layouts.app')

@section('title', 'Dealers')

@section('content')
<x-page-heading title="Dealer Network" subtitle="Manage every DHS Motors dealership organization." eyebrow="ADMIN · ENTIRE NETWORK">
    <a class="btn btn-primary" href="{{ route('dealers.create') }}">+ Add Dealer</a>
</x-page-heading>

@if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif

@if($dealers->isEmpty())
    <div class="panel">
        <div class="panel-body text-center py-5">
            <div class="eyebrow">DEALER NETWORK</div>
            <h3 class="mt-2">No dealer organizations yet.</h3>
            <p class="text-secondary">Create the first DHS Motors dealership organization to begin.</p>
            <a class="btn btn-primary" href="{{ route('dealers.create') }}">Create Dealer</a>
        </div>
    </div>
@else
    <div class="row g-3">
        @foreach($dealers as $dealer)
            <div class="col-md-6">
                <div class="panel h-100">
                    <div class="panel-header">
                        <div>
                            <div class="eyebrow">{{ $dealer->code }}</div>
                            <h3 class="panel-title mt-1">{{ $dealer->name }}</h3>
                        </div>
                        <x-status :status="ucfirst($dealer->status->value)" />
                    </div>
                    <div class="panel-body">
                        <p class="text-secondary">{{ $dealer->address ?: 'Address not provided' }} · {{ $dealer->city }}</p>
                        <div class="info-list">
                            <div class="info-item"><label>Code</label><strong>{{ $dealer->code }}</strong></div>
                            <div class="info-item"><label>Phone</label><strong>{{ $dealer->phone ?: '—' }}</strong></div>
                            <div class="info-item"><label>Email</label><strong>{{ $dealer->email ?: '—' }}</strong></div>
                            <div class="info-item"><label>City</label><strong>{{ $dealer->city }}</strong></div>
                            <div class="info-item"><label>Status</label><strong>{{ ucfirst($dealer->status->value) }}</strong></div>
                        </div>
                        <a class="btn btn-outline-dark w-100 mt-4" href="{{ route('dealers.show', $dealer) }}">View Dealer</a>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    @if($dealers->hasPages())
        <div class="mt-4">{{ $dealers->onEachSide(1)->links('pagination::bootstrap-5') }}</div>
    @endif
@endif
@endsection
