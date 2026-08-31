@csrf
@if(isset($vehicle))
    @method('PUT')
@endif
<div class="row g-3">
    <div class="col-md-6"><label class="form-label" for="name">Vehicle name</label><input id="name" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name', $vehicle->name ?? '') }}" required>@error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
    <div class="col-md-6"><label class="form-label" for="variant">Variant</label><input id="variant" name="variant" class="form-control @error('variant') is-invalid @enderror" value="{{ old('variant', $vehicle->variant ?? '') }}" required>@error('variant')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
    <div class="col-md-4"><label class="form-label" for="model_year">Model year</label><input id="model_year" name="model_year" type="number" class="form-control @error('model_year') is-invalid @enderror" value="{{ old('model_year', $vehicle->model_year ?? now()->year) }}" required>@error('model_year')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
    <div class="col-md-4"><label class="form-label" for="color">Color</label><input id="color" name="color" class="form-control @error('color') is-invalid @enderror" value="{{ old('color', $vehicle->color ?? '') }}" required>@error('color')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
    <div class="col-md-4"><label class="form-label" for="price">Price (BDT)</label><input id="price" name="price" type="number" min="0" step="0.01" class="form-control @error('price') is-invalid @enderror" value="{{ old('price', $vehicle->price ?? '') }}" required>@error('price')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
    <div class="col-12"><label class="form-label" for="status">Catalog status</label><select id="status" name="status" class="form-select @error('status') is-invalid @enderror" required>@foreach($statuses as $status)<option value="{{ $status }}" @selected(old('status', $vehicle->status ?? 'available') === $status)>{{ ucwords(str_replace('_', ' ', $status)) }}</option>@endforeach</select>@error('status')<div class="invalid-feedback">{{ $message }}</div>@enderror<div class="form-text">Dealer demo availability is managed separately through Demo Allocation.</div></div>
    <div class="col-12"><label class="form-label" for="description">Description</label><textarea id="description" name="description" rows="4" class="form-control @error('description') is-invalid @enderror">{{ old('description', $vehicle->description ?? '') }}</textarea>@error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
    <div class="col-12">
        <label class="form-label" for="image">Primary image</label>
        <input id="image" name="image" type="file" accept="image/*" class="form-control @error('image') is-invalid @enderror">
        @error('image')<div class="invalid-feedback">{{ $message }}</div>@enderror
        @if(isset($vehicle) && $vehicle->image)
            <div class="mt-2"><img src="{{ asset($vehicle->image) }}" alt="Current {{ $vehicle->name }} image" style="width:180px;max-height:120px;object-fit:cover"><div class="small text-secondary mt-1">Current image remains unless replaced.</div></div>
        @endif
    </div>
</div>
<div class="d-flex gap-2 mt-4"><button class="btn btn-primary" type="submit">{{ isset($vehicle) ? 'Update Vehicle' : 'Create Vehicle' }}</button><a class="btn btn-outline-dark" href="{{ route('vehicles.index') }}">Cancel</a></div>
