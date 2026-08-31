@extends('layouts.app')
@section('title', 'Create Customer')
@section('content')
<x-page-heading title="Create Customer" subtitle="Add a CRM customer and select the responsible Salesman." eyebrow="ADMIN · CUSTOMER MANAGEMENT" />
<div class="panel"><div class="panel-header"><h3 class="panel-title">Customer details</h3></div><div class="panel-body"><form method="POST" action="{{ route('customers.store') }}">@include('pages.customers._form')</form></div></div>
@endsection
