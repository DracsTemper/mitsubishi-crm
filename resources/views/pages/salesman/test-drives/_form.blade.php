@csrf
@isset($testDrive) @method('PUT') @endisset
<div class="row g-3">
    @if(!isset($testDrive))
        <div class="col-12"><div class="owner-strip"><span class="eyebrow">CUSTOMER · AUTOMATICALLY ASSIGNED</span><strong class="d-block mt-1">{{ $customer->name }}</strong><small class="text-secondary">Owned by {{ auth()->user()->name }} · {{ auth()->user()->dealer->name }}</small></div></div>
        <div class="col-md-5"><label class="form-label" for="slot_date_filter">1. Select Date</label><input id="slot_date_filter" type="date" min="{{ today()->format('Y-m-d') }}" value="{{ $selectedDate->toDateString() }}" class="form-control" data-slot-date><div class="form-text">Changing the date loads its live working-day availability.</div></div>
        <div class="col-md-7"><label class="form-label" for="slot_vehicle_filter">2. Demo / Test Drive Vehicle</label><select id="slot_vehicle_filter" class="form-select" data-slot-vehicle><option value="">Select a Vehicle</option>@foreach($vehicles as $vehicle)<option value="{{ $vehicle->id }}">{{ $vehicle->model_year }} Mitsubishi {{ $vehicle->name }} · {{ $vehicle->variant }} · {{ $vehicle->color }}</option>@endforeach</select><div class="form-text">Only active Demo Vehicles allocated to {{ auth()->user()->dealer->name }} are shown.</div></div>
    @else
        <div class="col-md-6"><div class="owner-strip h-100"><span class="eyebrow">CUSTOMER · LOCKED</span><strong class="d-block mt-1">{{ $testDrive->customer->name }}</strong><small class="text-secondary">Ownership cannot be changed here.</small></div></div>
        <div class="col-md-6"><div class="owner-strip h-100"><span class="eyebrow">VEHICLE · LOCKED</span><strong class="d-block mt-1">{{ $testDrive->vehicle->model_year }} {{ $testDrive->vehicle->name }}</strong><small class="text-secondary">{{ $testDrive->vehicle->variant }} · {{ $testDrive->vehicle->color }}</small></div></div>
    @endif
    <div class="col-12"><label class="form-label">3. Available Test Drive Slot</label>
    @if(!isset($testDrive) && $closureReason)<div class="calendar-closed panel mb-3"><i class="bi bi-door-closed"></i><strong>OUTLET CLOSED</strong><span>{{ $closureReason }}</span><small>No Test Drives Available</small></div>@endif
    <div class="slot-grid" data-slot-grid>
        @foreach($slots as $slot)
            @php($belongsToVehicle = !isset($testDrive) || $slot->vehicle_id === $testDrive->vehicle_id)
            @php($bookedByOther = $slot->is_booked && (!isset($testDrive) || $slot->testDrive?->id !== $testDrive->id))
            @if($belongsToVehicle)
                <label class="slot-option {{ $bookedByOther ? 'is-booked' : '' }}" data-slot-option data-vehicle="{{ $slot->vehicle_id }}" data-date="{{ $slot->slot_date->format('Y-m-d') }}">
                    @if(isset($testDrive))<input type="radio" name="slot_id" value="{{ $slot->id }}" {{ (string) old('slot_id', $testDrive->slot_id ?? '') === (string) $slot->id ? 'checked' : '' }} {{ $bookedByOther ? 'disabled' : '' }}>@else<input type="radio" name="slot_choice" value="{{ $slot->dealer_vehicle_allocation_id }}|{{ $slot->slot_date->format('Y-m-d') }}|{{ substr($slot->start_time, 0, 5) }}" {{ old('slot_choice') === $slot->dealer_vehicle_allocation_id.'|'.$slot->slot_date->format('Y-m-d').'|'.substr($slot->start_time, 0, 5) ? 'checked' : '' }} {{ $bookedByOther ? 'disabled' : '' }}>@endif
                    <span><strong>{{ \Illuminate\Support\Carbon::parse($slot->start_time)->format('g:i A') }} – {{ \Illuminate\Support\Carbon::parse($slot->end_time)->format('g:i A') }}</strong><small>{{ $slot->slot_date->format('d M Y') }} · {{ $slot->vehicle->name }}</small></span><em>{{ $bookedByOther ? 'BOOKED' : 'AVAILABLE' }}</em>
                </label>
            @endif
        @endforeach
    </div>@error('slot_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror @error('slot_choice')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror<div class="form-text" data-slot-empty>Select a date and Demo Vehicle to reveal its configured Slots.</div></div>
    @isset($testDrive)<div class="col-12"><label class="form-label" for="status">Appointment status</label><select id="status" name="status" class="form-select @error('status') is-invalid @enderror" required>@foreach($statuses as $status)<option value="{{ $status->value }}" @selected(old('status', $testDrive->status->value) === $status->value)>{{ $status->label() }}</option>@endforeach</select>@error('status')<div class="invalid-feedback">{{ $message }}</div>@enderror<div class="form-text">Choose Rescheduled after selecting a new available Slot.</div></div>@endisset
    <div class="col-12"><label class="form-label" for="notes">Appointment notes <span class="text-secondary">(optional)</span></label><textarea id="notes" name="notes" class="form-control @error('notes') is-invalid @enderror" maxlength="2000" rows="4">{{ old('notes', $testDrive->notes ?? '') }}</textarea>@error('notes')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
</div>
<div class="d-flex gap-2 mt-4"><button class="btn btn-primary" type="submit">{{ isset($testDrive) ? 'Update Test Drive' : 'Book Selected Slot' }}</button><a class="btn btn-outline-dark" href="{{ isset($testDrive) ? route('salesman.test-drives.show', $testDrive) : route('salesman.customers.show', $customer) }}">Cancel</a></div>
@if(!isset($testDrive))
<script>
document.addEventListener('DOMContentLoaded',()=>{
    const vehicle=document.querySelector('[data-slot-vehicle]'),date=document.querySelector('[data-slot-date]'),options=[...document.querySelectorAll('[data-slot-option]')],empty=document.querySelector('[data-slot-empty]');
    const filter=()=>{let visible=0;options.forEach(option=>{const show=Boolean(vehicle.value&&option.dataset.vehicle===vehicle.value);option.hidden=!show;if(show)visible++;});empty.textContent=!vehicle.value?'Select a Demo / Test Drive Vehicle.':visible?'Choose one available Test Drive Slot.':'No slots available for this date.';};
    vehicle.addEventListener('change',filter);date.addEventListener('change',()=>{const url=new URL(location.href);url.searchParams.set('date',date.value);location.assign(url)});filter();
});
</script>
@endif
