@php
$dealer = request()->routeIs('dealer.*');
$salesman = request()->routeIs('salesman.*');
$authenticatedUser = auth()->user();
$outletName = $authenticatedUser?->dealer?->name;
$dashboard = request()->routeIs('dashboard','dealer.dashboard','salesman.dashboard');
$topTitle = $dashboard ? ($salesman ? 'My Customer Desk' : ($dealer ? (($outletName ?? 'Dealer').' Operations') : 'Dealer Network')) : trim($__env->yieldContent('title','Workspace'));
@endphp
<header class="topbar">
    <button class="icon-btn mobile-toggle me-3" data-sidebar-toggle aria-label="Open navigation">@include('components.icon',['name'=>'menu'])</button>
    <div><div class="eyebrow d-none d-sm-block">{{ ($dealer||$salesman) ? ($outletName ?? 'UNASSIGNED OUTLET') : 'MITSUBISHI MOTORS BANGLADESH' }}</div><h1>{{ $topTitle }}</h1></div>
    <div class="top-actions">
        <div class="search-box" data-search-box>
            @include('components.icon',['name'=>'search'])<input id="globalSearch" autocomplete="off" placeholder="Search customers, vehicles...">
            <div class="search-suggestions"><small>QUICK RESULTS</small><a href="{{ $salesman?route('salesman.customers.show',1):($dealer?route('dealer.customers.show',1):route('customers.show',1)) }}"><span class="avatar">RA</span><span><strong>Rahim Ahmed</strong><em>Customer · Reserved</em></span></a><a href="{{ $salesman?route('salesman.test-drives'):($dealer?route('dealer.inventory'):route('inventory')) }}">@include('components.icon',['name'=>'car'])<span><strong>Outlander 2.4 AWD</strong><em>OUT-26-000184</em></span></a></div>
        </div>
        <button class="theme-toggle" id="themeToggle" type="button" aria-label="Switch to light theme" aria-pressed="true" title="Switch to light theme"><span class="theme-toggle-icon"><i class="bi bi-sun-fill" aria-hidden="true"></i></span><span class="theme-toggle-icon"><i class="bi bi-moon-stars-fill" aria-hidden="true"></i></span></button>
        <div class="dropdown">
            <button class="icon-btn" data-bs-toggle="dropdown" aria-label="Notifications">@include('components.icon',['name'=>'bell'])<span class="notification-dot"></span></button>
            <div class="dropdown-menu dropdown-menu-end notification-panel p-0"><div class="p-3 border-bottom d-flex justify-content-between"><strong>Notifications</strong><small class="text-danger">3 unread</small></div>@foreach([['NEW ENQUIRY','Rahim Ahmed is interested in Outlander','2 min ago'],['TEST DRIVE','Nusrat Jahan · Today, 1:00 PM','28 min ago'],['PAYMENT RECEIVED','৳50,000 from Sabbir Hasan','1 hr ago']] as $n)<div class="notification-item"><i></i><div><small>{{ $n[0] }}</small><strong>{{ $n[1] }}</strong><em>{{ $n[2] }}</em></div></div>@endforeach</div>
        </div>
        <div class="dropdown">
            <button class="avatar border-0" data-bs-toggle="dropdown" aria-label="Open profile menu">{{ $authenticatedUser ? str($authenticatedUser->name)->explode(' ')->filter()->take(2)->map(fn($part)=>strtoupper(substr($part,0,1)))->join('') : 'AU' }}</button>
            <div class="dropdown-menu dropdown-menu-end profile-menu"><strong>{{ $authenticatedUser?->name ?? 'Admin User' }}</strong><small>{{ ($dealer||$salesman) ? ($outletName ?? 'No Dealer assigned') : 'Network Administrator' }}</small><hr><a href="{{ $dealer?route('dealer.profile'):($salesman?route('salesman.dashboard'):route('settings')) }}">Profile & settings</a><a href="{{ route('login') }}">Switch role</a></div>
        </div>
    </div>
</header>
