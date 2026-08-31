@php($dealerMode = request()->routeIs('dealer.*'))
@php($inventoryRoute = $dealerMode ? route('dealer.inventory') : route('inventory'))
<section class="inventory-showcase reveal">
    <div class="showcase-head">
        <div><div class="eyebrow">{{ $dealerMode?'MY SHOWROOM':'NETWORK INVENTORY' }}</div><h3>Inventory Highlights</h3><p>Explore this month’s featured Mitsubishi lineup.</p></div>
        <a class="showcase-all" href="{{ $inventoryRoute }}">Explore all inventory <i class="bi bi-arrow-up-right"></i></a>
    </div>
    <div class="showcase-grid">
        @foreach([
            ['outlander-2026.png','OUTLANDER','2.4 AWD','Reserved','OUT-26-000184','18 units'],
            ['xforce-2026.png','XFORCE','1.5 Premium','Available','XFC-26-000291','12 units'],
            ['triton-2026.png','TRITON','2.4 Double Cab','Sold','TRT-26-000112','6 units'],
            ['pajero-sport-2026.png','PAJERO SPORT','2.4 Elite','Test Drive','PJS-26-000081','9 units']
        ] as $v)
            <a class="showcase-car" href="{{ $inventoryRoute }}">
                <div class="showcase-image"><img src="/assets/images/vehicles/{{ $v[0] }}" alt="Mitsubishi {{ $v[1] }}"><span class="showcase-status status-{{ strtolower(str_replace(' ','-',$v[3])) }}"><i></i>{{ strtoupper($v[3]) }}</span></div>
                <div class="showcase-content">
                    <div class="showcase-model"><span>2026 MODEL</span><small>{{ $v[1] }}</small></div>
                    <h4>{{ $v[1] }}</h4><p>{{ $v[2] }} · 2026</p>
                    <div class="showcase-footer"><span>{{ $v[4] }}</span><strong>{{ $v[5] }} <i class="bi bi-arrow-right"></i></strong></div>
                </div>
            </a>
        @endforeach
    </div>
</section>
