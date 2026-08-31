<div class="d-flex flex-wrap align-items-end justify-content-between gap-3 mb-4">
    <div>
        <div class="eyebrow">{{ $eyebrow??'DEALER MANAGEMENT' }}</div>
        <h2 class="page-heading">{{ $title }}</h2>
        <p class="page-subtitle">{{ $subtitle }}</p>
    </div>{{ $slot }}
</div>