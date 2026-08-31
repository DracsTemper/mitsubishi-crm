@extends('layouts.app') @section('title','Vehicle Calendar') @section('content')
<x-page-heading title="Vehicle Calendar" subtitle="Schedule test drives by individual vehicle and chassis.">
    <div class="btn-group"><button class="btn btn-outline-dark">Day</button><button class="btn btn-dark">Week</button><button class="btn btn-outline-dark">Month</button></div>
</x-page-heading>
<div class="panel mb-3">
    <div class="panel-body d-flex flex-wrap gap-3 align-items-center">
        <div><small class="text-secondary">VEHICLE</small>
            <h5 class="mb-0">Outlander 2.4 AWD</h5>
        </div><span class="chassis">OUT-26-000184</span>
        <div class="ms-md-auto"><x-status status="Reserved" /> <strong class="ms-2">Mitsubishi Uttara</strong></div>
    </div>
</div>
<div class="panel">
    <div class="panel-header"><button class="btn btn-sm btn-outline-dark">← Previous</button>
        <h3 class="panel-title">24–28 August 2026</h3><button class="btn btn-sm btn-outline-dark">Next →</button>
    </div>
    <div class="table-responsive">
        <div class="calendar-grid">
            <div class="cal-cell cal-head">TIME</div>
            @foreach(['MON 24','TUE 25','WED 26','THU 27','FRI 28'] as $day)
            <div class="cal-cell cal-head">{{ $day }}</div>
            @endforeach
            @foreach(['09:00','10:30','12:00','14:00','16:00'] as $ri => $time)
            <div class="cal-cell cal-time">{{ $time }}</div>
            @foreach(range(0,4) as $dayIndex)
            @php
            $busy = ($ri === 1 && $dayIndex === 1) || ($ri === 3 && $dayIndex === 1) || ($ri === 2 && $dayIndex === 3);
            @endphp
            <div class="cal-cell">
                <div class="slot {{ $busy ? 'busy' : '' }}" data-time="{{ $time }}">
                    @if($busy)
                    <strong>Test Drive</strong><br>{{ $ri === 1 ? 'Rahim Ahmed' : 'Nusrat Jahan' }}
                    @else
                    <strong>Available</strong><br>Book this slot
                    @endif
                </div>
            </div>
            @endforeach
            @endforeach
        </div>
    </div>
</div>
<div class="modal fade" id="bookingModal">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5>Book Test Drive</h5><button class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="owner-strip mb-3"><strong>Outlander 2.4 AWD</strong>
                    <div class="chassis d-inline-block ms-2">OUT-26-000184</div>
                </div><label class="form-label">Customer</label><select class="form-select mb-3">
                    <option>Rahim Ahmed</option>
                    <option>Nusrat Jahan</option>
                    <option>Tanvir Rahman</option>
                </select><label class="form-label">Available time</label><input id="bookingTime" class="form-control" readonly><small class="text-success d-block mt-2">✓ No overlapping booking detected for this prototype slot.</small>
            </div>
            <div class="modal-footer"><button class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary" data-bs-dismiss="modal" data-demo-action data-message="Test drive booking confirmed">Confirm Booking</button></div>
        </div>
    </div>
</div>
@endsection