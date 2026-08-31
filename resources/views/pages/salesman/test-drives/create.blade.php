@extends('layouts.app')
@section('title', 'Schedule Test Drive')
@section('content')
<a href="{{ route('salesman.customers.show', $customer) }}" class="text-secondary text-decoration-none">← {{ $customer->name }}</a>
<x-page-heading title="Schedule Test Drive" subtitle="Choose a Dealer Vehicle and reserve a clear appointment time for this Customer." eyebrow="CUSTOMER WORKSPACE · TEST DRIVE" />
<form class="panel panel-body mt-3" method="POST" action="{{ route('salesman.customers.test-drives.store', $customer) }}">@include('pages.salesman.test-drives._form')</form>
@endsection
