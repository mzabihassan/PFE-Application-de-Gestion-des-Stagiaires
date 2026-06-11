@props([
    'label',
    'value',
    'icon' => 'bi-bar-chart-line',
])

<div {{ $attributes->merge(['class' => 'stat-card h-100']) }}>
    <div class="card-body">
        <div class="d-flex align-items-start gap-3">
            <span class="module-icon"><i class="bi {{ $icon }}"></i></span>
            <div>
                <div class="text-muted small">{{ $label }}</div>
                <div class="display-6 fw-semibold">{{ $value }}</div>
            </div>
        </div>
    </div>
</div>
