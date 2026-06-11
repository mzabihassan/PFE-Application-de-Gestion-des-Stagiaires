@props([
    'icon' => 'bi-inbox',
    'title' => null,
])

<div {{ $attributes->merge(['class' => 'empty-state']) }}>
    <span class="empty-state-icon"><i class="bi {{ $icon }}"></i></span>
    <div>
        @if($title)<div class="fw-semibold text-body">{{ $title }}</div>@endif
        <div class="small">{{ $slot }}</div>
    </div>
</div>
