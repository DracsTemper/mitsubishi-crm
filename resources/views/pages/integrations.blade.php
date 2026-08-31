@extends('layouts.app') @section('title','API Integrations') @section('content')
<x-page-heading title="API Integrations" subtitle="Connect external business systems and monitor data exchange."><button class="btn btn-outline-dark" data-demo-action data-message="Integration catalogue opened">+ Add Integration</button></x-page-heading>
<div class="row g-3 mb-4">
    <div class="col-lg-7">
        <div class="panel h-100">
            <div class="panel-header">
                <div>
                    <div class="eyebrow">CONNECTED APPLICATION</div>
                    <h3 class="panel-title mt-1">Accounting System</h3>
                </div><x-status status="Connected" />
            </div>
            <div class="panel-body">
                <div class="row g-3">
                    <div class="col-sm-4 info-item"><label>Connection</label><strong>REST API · v2</strong></div>
                    <div class="col-sm-4 info-item"><label>Last Sync</label><strong>25 Aug 2026, 10:42 AM</strong></div>
                    <div class="col-sm-4 info-item"><label>Records Synced</label><strong>1,284</strong></div>
                </div>
                <hr class="my-4">
                <p class="text-secondary">Bi-directional sync for customers, bookings, payments and vehicle transactions.</p><button class="btn btn-primary" data-demo-action data-message="Sync completed — 14 records updated">Sync Now</button> <button class="btn btn-outline-dark">View Logs</button>
            </div>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="panel h-100">
            <div class="panel-header">
                <h3 class="panel-title">API Health</h3><span class="text-success fw-bold">99.98%</span>
            </div>
            <div class="panel-body"><x-stat label="Requests Today" value="2,481" change="All systems operational" />
                <div class="d-flex justify-content-between mt-3"><span>Average response</span><strong>184 ms</strong></div>
                <div class="d-flex justify-content-between mt-2"><span>Failed requests</span><strong>2</strong></div>
            </div>
        </div>
    </div>
</div>
<div class="panel">
    <div class="panel-header">
        <h3 class="panel-title">API Activity / Sync Logs</h3><select class="form-select form-select-sm" style="width:140px">
            <option>All activity</option>
        </select>
    </div>
    <div class="table-responsive">
        <table class="table crm-table">
            <thead>
                <tr>
                    <th>Time</th>
                    <th>Direction</th>
                    <th>Event</th>
                    <th>Application</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>@foreach([['10:42:18 AM','PUSH','Customer #1024 pushed','Accounting System','Success'],['10:42:16 AM','PUSH','Booking #583 pushed','Accounting System','Success'],['10:41:52 AM','PULL','Payment #291 pulled','Accounting System','Success'],['10:40:31 AM','PULL','Vehicle #184 updated','Accounting System','Success'],['09:15:08 AM','PUSH','Booking #579 rejected','Accounting System','Error']] as $l)<tr>
                    <td>{{ $l[0] }}</td>
                    <td><span class="chassis">{{ $l[1] }} →</span></td>
                    <td><strong>{{ $l[2] }}</strong></td>
                    <td>{{ $l[3] }}</td>
                    <td><x-status :status="$l[4]" /></td>
                </tr>@endforeach</tbody>
        </table>
    </div>
</div>
@endsection