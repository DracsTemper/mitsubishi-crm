@extends('layouts.app')

@section('title', 'Salesmen')

@section('content')
<x-page-heading title="Network Salesmen" subtitle="Every Salesman belongs to exactly one DHS Motors Dealer." eyebrow="ADMIN · TEAM DIRECTORY">
    <a class="btn btn-primary" href="{{ route('salesmen.create') }}">+ Register Salesman</a>
</x-page-heading>

<div class="owner-strip mb-3"><strong>Hierarchy</strong><div>Admin → Dealer → Manager → Salesman → Customer</div></div>
<div class="panel">
    <div class="table-responsive">
        <table class="table crm-table">
            <thead><tr><th>Salesman</th><th>Dealer</th><th>Active Customers</th><th>Test Drives</th><th>Bookings</th><th>Cars Sold</th><th>Status</th></tr></thead>
            <tbody>
                @foreach([['Arif Hasan','Mitsubishi Uttara',38,7,4,3],['Sabbir Rahman','Mitsubishi Uttara',29,6,3,2],['Fahim Ahmed','Mitsubishi Uttara',21,4,3,2],['Tanvir Hasan','Mitsubishi Uttara',18,3,5,3],['Nabil Islam','Mitsubishi Gulshan',31,5,4,2]] as $salesman)
                    <tr><td><a href="{{ route('salesmen.show', strtolower(str_replace(' ', '-', $salesman[0]))) }}"><strong>{{ $salesman[0] }}</strong></a><small class="d-block text-secondary">Sales Executive</small></td><td>{{ $salesman[1] }}</td><td>{{ $salesman[2] }}</td><td>{{ $salesman[3] }}</td><td>{{ $salesman[4] }}</td><td>{{ $salesman[5] }}</td><td><x-status status="Active" /></td></tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endsection
