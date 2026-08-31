@csrf
@if(isset($dealer)) @method('PUT') @endif

<div class="row g-3">
    <div class="col-md-8">
        <label class="form-label" for="name">Dealer name</label>
        <input id="name" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name', $dealer->name ?? '') }}" required maxlength="255">
        @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-4">
        <label class="form-label" for="code">Dealer code</label>
        <input id="code" name="code" class="form-control @error('code') is-invalid @enderror" value="{{ old('code', $dealer->code ?? '') }}" required maxlength="50">
        @error('code')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-6">
        <label class="form-label" for="phone">Phone</label>
        <input id="phone" name="phone" class="form-control @error('phone') is-invalid @enderror" value="{{ old('phone', $dealer->phone ?? '') }}" maxlength="30">
        @error('phone')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-6">
        <label class="form-label" for="email">Email</label>
        <input id="email" name="email" type="email" class="form-control @error('email') is-invalid @enderror" value="{{ old('email', $dealer->email ?? '') }}" maxlength="255">
        @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-6">
        <label class="form-label" for="city">City</label>
        <input id="city" name="city" class="form-control @error('city') is-invalid @enderror" value="{{ old('city', $dealer->city ?? '') }}" required maxlength="255">
        @error('city')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-6">
        <label class="form-label" for="status">Status</label>
        <select id="status" name="status" class="form-select @error('status') is-invalid @enderror" required>
            @foreach($statuses as $status)
                <option value="{{ $status->value }}" @selected(old('status', isset($dealer) ? $dealer->status->value : 'active') === $status->value)>{{ ucfirst($status->value) }}</option>
            @endforeach
        </select>
        @error('status')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-12">
        <label class="form-label" for="address">Address</label>
        <textarea id="address" name="address" class="form-control @error('address') is-invalid @enderror" rows="4">{{ old('address', $dealer->address ?? '') }}</textarea>
        @error('address')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
</div>

<div class="d-flex gap-2 mt-4">
    <button class="btn btn-primary" type="submit">{{ isset($dealer) ? 'Save Changes' : 'Create Dealer' }}</button>
    <a class="btn btn-outline-dark" href="{{ isset($dealer) ? route('dealers.show', $dealer) : route('dealers.index') }}">Cancel</a>
</div>
