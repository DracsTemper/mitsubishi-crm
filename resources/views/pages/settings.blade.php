@extends('layouts.app') @section('title','Settings') @section('content')
<x-page-heading title="System Settings" subtitle="Configure company, dealer and workspace preferences."><button class="btn btn-primary" data-demo-action data-message="Settings saved">Save Changes</button></x-page-heading>
<div class="row g-3">
    <div class="col-xl-8">
        <div class="panel mb-3">
            <div class="panel-header">
                <h3 class="panel-title">Company Information</h3>
            </div>
            <div class="panel-body">
                <div class="row g-3">
                    <div class="col-md-6"><label class="form-label">Company Name</label><input class="form-control" value="Mitsubishi Motors Bangladesh"></div>
                    <div class="col-md-6"><label class="form-label">Support Email</label><input class="form-control" value="crm@mitsubishi.com.bd"></div>
                    <div class="col-12"><label class="form-label">Head Office</label><input class="form-control" value="Tejgaon Industrial Area, Dhaka 1208"></div>
                </div>
            </div>
        </div>
        <div class="panel">
            <div class="panel-header">
                <h3 class="panel-title">Dealer Information</h3>
            </div>
            <div class="panel-body">
                <div class="row g-3">
                    <div class="col-md-6"><label class="form-label">Default Dealer</label><select class="form-select">
                            <option>Mitsubishi Uttara</option>
                            <option>Mitsubishi Gulshan</option>
                        </select></div>
                    <div class="col-md-6"><label class="form-label">Dealer Owner</label><input class="form-control" value="John Doe"></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-4">
        <div class="panel mb-3">
            <div class="panel-header">
                <h3 class="panel-title">Notification Preferences</h3>
            </div>
            <div class="panel-body">@foreach(['New customer enquiries','Test drive reminders','Booking payments','API sync errors'] as $i=>$n)<div class="form-check form-switch d-flex justify-content-between ps-0 mb-3"><label for="n{{ $i }}">{{ $n }}</label><input class="form-check-input" id="n{{ $i }}" type="checkbox" checked></div>@endforeach</div>
        </div>
        <div class="panel">
            <div class="panel-header">
                <h3 class="panel-title">System Preferences</h3>
            </div>
            <div class="panel-body"><label class="form-label">Timezone</label><select class="form-select mb-3">
                    <option>Asia/Dhaka (GMT+6)</option>
                </select><label class="form-label">Currency</label><select class="form-select">
                    <option>BDT — Bangladeshi Taka (৳)</option>
                </select></div>
        </div>
    </div>
</div>
@endsection