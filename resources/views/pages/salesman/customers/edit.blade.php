@extends('layouts.app')

@section('title', 'Edit '.$customer->name)

@section('content')
<x-page-heading title="Edit Customer" subtitle="Update {{ $customer->name }} while preserving your ownership assignment." eyebrow="SALESMAN · CUSTOMER EDITING" />

<div class="owner-strip mb-3"><strong>Ownership preserved</strong><div class="text-secondary">This Customer remains assigned to {{ $customer->salesman->name }} at {{ $customer->salesman->dealer->name }}.</div></div>
<div class="panel">
    <div class="panel-header"><div><h3 class="panel-title">Customer information</h3><p class="text-secondary mb-0 mt-1">Only contact and journey fields can be changed.</p></div></div>
    <div class="panel-body">
        <form method="POST" action="{{ route('salesman.customers.update', $customer) }}">
            @csrf
            @method('PUT')
            <div class="row g-3">
                <div class="col-md-6"><label class="form-label" for="name">Name</label><input id="name" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name', $customer->name) }}" required maxlength="255">@error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                <div class="col-md-6"><label class="form-label" for="phone">Phone</label><input id="phone" name="phone" class="form-control @error('phone') is-invalid @enderror" value="{{ old('phone', $customer->phone) }}" required maxlength="30">@error('phone')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                <div class="col-md-6"><label class="form-label" for="email">Email</label><input id="email" name="email" type="email" class="form-control @error('email') is-invalid @enderror" value="{{ old('email', $customer->email) }}" maxlength="255">@error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                <div class="col-md-6"><label class="form-label" for="city">City</label><input id="city" name="city" class="form-control @error('city') is-invalid @enderror" value="{{ old('city', $customer->city) }}" maxlength="255">@error('city')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                <div class="col-12"><label class="form-label" for="address">Address</label><textarea id="address" name="address" class="form-control @error('address') is-invalid @enderror" rows="3">{{ old('address', $customer->address) }}</textarea>@error('address')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                <div class="col-md-6"><label class="form-label" for="status">Status</label><select id="status" name="status" class="form-select @error('status') is-invalid @enderror" required>@foreach($statuses as $status)<option value="{{ $status }}" @selected(old('status', $customer->status) === $status)>{{ ucfirst($status) }}</option>@endforeach</select>@error('status')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
            </div>
            <div class="d-flex gap-2 mt-4"><button class="btn btn-primary" type="submit">Update Customer</button><a class="btn btn-outline-dark" href="{{ route('salesman.customers.show', $customer) }}">Cancel</a></div>
        </form>
    </div>
</div>
@endsection
