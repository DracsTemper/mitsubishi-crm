@extends('layouts.app')
@section('title', 'Create Manager')
@section('content')
<x-page-heading title="Create Manager" subtitle="Create an authenticated Manager account for a Dealer organization." eyebrow="ADMIN · MANAGER REGISTRATION" />
<div class="panel">
    <div class="panel-header"><div><h3 class="panel-title">Manager account</h3><p class="text-secondary mb-0 mt-1">Manager accounts belong to exactly one Dealer organization.</p></div></div>
    <div class="panel-body">
        <form method="POST" action="{{ route('managers.store') }}">
            @csrf
            <div class="row g-3">
                <div class="col-md-6"><label class="form-label" for="name">Name</label><input id="name" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name') }}" required maxlength="255" autocomplete="name">@error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                <div class="col-md-6"><label class="form-label" for="email">Email</label><input id="email" name="email" type="email" class="form-control @error('email') is-invalid @enderror" value="{{ old('email') }}" required maxlength="255" autocomplete="email">@error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                <div class="col-12"><label class="form-label" for="dealer_id">Dealer organization</label><select id="dealer_id" name="dealer_id" class="form-select @error('dealer_id') is-invalid @enderror" required><option value="">Select a Dealer</option>@foreach($dealers as $dealer)<option value="{{ $dealer->id }}" @selected(old('dealer_id', request('dealer_id')) == $dealer->id)>{{ $dealer->name }} — {{ $dealer->code }}</option>@endforeach</select>@error('dealer_id')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                <div class="col-md-6"><label class="form-label" for="password">Password</label><input id="password" name="password" type="password" class="form-control @error('password') is-invalid @enderror" required minlength="8" autocomplete="new-password">@error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                <div class="col-md-6"><label class="form-label" for="password_confirmation">Confirm password</label><input id="password_confirmation" name="password_confirmation" type="password" class="form-control" required minlength="8" autocomplete="new-password"></div>
            </div>
            <div class="d-flex gap-2 mt-4"><button class="btn btn-primary" type="submit">Create Manager</button><a class="btn btn-outline-dark" href="{{ route('dealers.index') }}">Cancel</a></div>
        </form>
    </div>
</div>
@endsection
