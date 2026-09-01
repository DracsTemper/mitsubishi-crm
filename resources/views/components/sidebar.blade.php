@php
$dealer = request()->routeIs('dealer.*');
$salesman = request()->routeIs('salesman.*');
$authenticatedUser = auth()->user();
$authenticatedDealer = $authenticatedUser?->dealer?->name;
$authenticatedInitials = $authenticatedUser ? str($authenticatedUser->name)->explode(' ')->filter()->take(2)->map(fn($part)=>strtoupper(substr($part,0,1)))->join('') : 'AU';
if ($salesman) {
    $nav = [['salesman.dashboard','Dashboard','grid'],['salesman.customers.*','My Customers','users'],['salesman.conversations','Conversations','chat'],['salesman.test-drives.*','Test Drives','calendar'],['salesman.follow-ups.*','Follow-Ups','calendar'],['salesman.calendar','Calendar','calendar'],['salesman.bookings','Bookings','book'],['salesman.chassis','Chassis','car'],['salesman.deliveries','Deliveries','car']];
    $portal='SALESMAN WORKSPACE'; $initials=$authenticatedInitials; $user=$authenticatedUser?->name ?? 'Salesman'; $role=$authenticatedDealer ?? 'No Dealer assigned';
} elseif ($dealer) {
    $nav = [['dealer.dashboard','Dashboard','grid'],['dealer.team','Sales Team','users'],['dealer.customers.*','Customers','users'],['dealer.test-drives','Test Drives','calendar'],['dealer.bookings','Bookings','book'],['dealer.inventory','Inventory','car'],['dealer.chassis','Chassis','car'],['dealer.deliveries','Deliveries','car'],['dealer.reports','Reports','chart']];
    $portal='DEALER / OUTLET'; $initials=$authenticatedInitials; $user=$authenticatedUser?->name ?? 'Manager'; $role=$authenticatedDealer ?? 'No Dealer assigned';
} else {
    $nav = [['admin.dashboard','Dashboard','grid'],['dealers.*','Dealers','users'],['salesmen.*','Salesmen','users'],['customers.*','Customers','users'],['vehicles.*','Vehicles','car'],['inventory','Inventory','car'],['test-drives.*','Test Drives','calendar'],['bookings.*','Bookings','book'],['deliveries.*','Deliveries','car'],['reports','Reports','chart'],['integrations','API Integrations','link'],['settings','Settings','settings']];
    $portal='NETWORK ADMINISTRATION'; $initials='AU'; $user='Admin User'; $role='Network Administrator';
}
@endphp
<aside class="sidebar"><div class="brand"><div class="brand-mark"><img class="brand-logo" src="/assets/images/brand/honda-emblem-transparent.png" alt="Honda emblem"><span class="brand-lockup"><span><b>DHS</b> MOTORS</span><em>Drive Your Dreams</em></span></div><small>{{ $portal }}</small></div><nav class="side-nav">@foreach($nav as [$route,$label,$icon])@if(in_array($label,['Dealers','Customers','Vehicles','Reports','Sales Team','Inventory','My Customers','Test Drives','Chassis']))<div class="side-label">{{ strtoupper($label) }}</div>@endif<a class="side-link {{ request()->routeIs($route)?'active':'' }}" href="{{ route(str_replace('.*','.index',$route)) }}">@include('components.icon',['name'=>$icon])<span>{{ $label }}</span></a>@endforeach</nav><div class="side-user"><span class="avatar red">{{ $initials }}</span><div><strong>{{ $user }}</strong><small>{{ $role }}</small></div>@auth<form method="POST" action="{{ route('logout') }}">@csrf<button type="submit" class="logout" title="Sign out" style="border:0;background:transparent;padding:0">@include('components.icon',['name'=>'logout'])</button></form>@else<a href="{{ route('login') }}" class="logout" title="Sign in">@include('components.icon',['name'=>'logout'])</a>@endauth</div></aside>
