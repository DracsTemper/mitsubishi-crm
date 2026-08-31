@extends('layouts.app')
@section('title', 'Edit '.$customer->name)
@section('content')
<x-page-heading title="Edit Customer" subtitle="Update {{ $customer->name }} or reassign Salesman ownership." eyebrow="ADMIN · CUSTOMER MANAGEMENT" />
<div class="panel"><div class="panel-header"><h3 class="panel-title">Customer details</h3></div><div class="panel-body"><form method="POST" action="{{ route('customers.update', $customer) }}">@include('pages.customers._form')</form></div></div>
@endsection
