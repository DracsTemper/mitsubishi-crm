@extends('layouts.app')
@section('title','Dashboard')
@section('content')
<x-page-heading :title="'Good morning, '.auth()->user()->name.'.'" subtitle="Here's what needs attention across your customers today." :eyebrow="'SALES EXECUTIVE · '.strtoupper(auth()->user()->dealer->name)">
    <a class="btn btn-primary" href="{{ route('salesman.customers.create') }}">+ Add Customer</a>
</x-page-heading>
<div class="owner-strip mb-3"><strong>Your ownership scope</strong><div>{{ auth()->user()->name }} → {{ auth()->user()->dealer->name }} · Customers you create are automatically assigned to you.</div></div>
<section class="command-metrics">
    @foreach([['My Customers', auth()->user()->customers()->count()], ['Test Drives', auth()->user()->testDrives()->count()], ['Active Bookings', auth()->user()->bookings()->count()]] as $metric)
        <div class="command-metric"><small>{{ $metric[0] }}</small><strong>{{ $metric[1] }}</strong><span>My records only</span></div>
    @endforeach
</section>
<div class="panel mt-3"><div class="panel-header"><div><div class="eyebrow">QUICK ACTIONS</div><h3 class="panel-title mt-1">Move Customers forward</h3></div></div><div class="panel-body d-flex flex-wrap gap-2"><a class="btn btn-primary" href="{{ route('salesman.customers.create') }}">+ Add Customer</a><a class="btn btn-outline-dark" href="{{ route('salesman.customers.index') }}">My Customers</a><a class="btn btn-outline-dark" href="{{ route('salesman.test-drives.index') }}">Test Drives</a><a class="btn btn-outline-dark" href="{{ route('salesman.bookings') }}">Bookings</a></div></div>
@endsection
