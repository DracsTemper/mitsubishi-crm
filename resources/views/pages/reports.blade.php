@extends('layouts.app') @section('title','Reports') @section('content')
<x-page-heading title="Executive Reports" subtitle="Sales, revenue and conversion performance across the dealer network.">
    <div><select class="form-select d-inline-block" style="width:150px">
            <option>August 2026</option>
        </select> <button class="btn btn-primary" data-demo-action data-message="Executive report exported">Export PDF</button></div>
</x-page-heading>
<div class="row g-3 mb-4">@foreach([['Total Sales','48'],['Total Revenue','৳8.4M'],['Avg. Booking Value','৳56,250'],['Conversion Rate','18.6%'],['Test Drive Conversion','42.4%']] as $s)<div class="col-sm-6 col-xl"><x-stat :label="$s[0]" :value="$s[1]" change="August performance" /></div>@endforeach</div>
<div class="row g-3 mb-4">
    <div class="col-xl-8">
        <div class="panel">
            <div class="panel-header">
                <h3 class="panel-title">Monthly Sales Overview</h3>
            </div>
            <div class="panel-body">
                <div class="chart">@foreach([48,55,70,64,83,91,78,100] as $i=>$h)<div class="bar-wrap">
                        <div class="bar" style="height:{{ $h*1.7 }}px"></div>{{ ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug'][$i] }}
                    </div>@endforeach</div>
            </div>
        </div>
    </div>
    <div class="col-xl-4">
        <div class="panel h-100">
            <div class="panel-header">
                <h3 class="panel-title">Dealer Performance</h3>
            </div>
            <div class="panel-body">@foreach([['Uttara',18,100],['Gulshan',14,78],['Dhanmondi',10,56],['Chittagong',6,33]] as $d)<div class="mb-4">
                    <div class="d-flex justify-content-between"><span>{{ $d[0] }}</span><strong>{{ $d[1] }} sold</strong></div>
                    <div class="progress mt-2" style="height:6px">
                        <div class="progress-bar bg-danger" style="width:{{ $d[2] }}%"></div>
                    </div>
                </div>@endforeach</div>
        </div>
    </div>
</div>
<div class="row g-3">
    <div class="col-xl-7">
        <div class="panel h-100">
            <div class="panel-header">
                <h3 class="panel-title">Conversion Funnel</h3>
            </div>
            <div class="panel-body">
                <div class="funnel">@foreach([['Enquiries','326'],['Test Drives','174'],['Bookings','81'],['Purchases','48']] as $f)<div class="funnel-step"><strong class="fs-4 d-block">{{ $f[1] }}</strong><small>{{ $f[0] }}</small></div>@endforeach</div>
                <div class="text-secondary mt-4">14.7% of total enquiries converted to purchases this period.</div>
            </div>
        </div>
    </div>
    <div class="col-xl-5">
        <div class="panel">
            <div class="panel-header">
                <h3 class="panel-title">Vehicle Performance</h3>
            </div>
            <div class="table-responsive">
                <table class="table crm-table">
                    <thead>
                        <tr>
                            <th>Model</th>
                            <th>Leads</th>
                            <th>Sold</th>
                            <th>Revenue</th>
                        </tr>
                    </thead>
                    <tbody>@foreach([['Outlander',112,19,'৳95.0M'],['Xforce',96,14,'৳53.9M'],['Triton',67,9,'৳41.4M'],['Pajero Sport',51,6,'৳37.5M']] as $v)<tr>
                            <td><strong>{{ $v[0] }}</strong></td>
                            <td>{{ $v[1] }}</td>
                            <td>{{ $v[2] }}</td>
                            <td>{{ $v[3] }}</td>
                        </tr>@endforeach</tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection