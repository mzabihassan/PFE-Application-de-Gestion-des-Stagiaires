@props([
    'title',
    'kicker' => null,
    'kickerIcon' => null,
    'subtitle' => null,
])

<div {{ $attributes->merge(['class' => 'page-head d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4 fade-in']) }}>
    <div>
        @if($kicker)
            <div class="page-kicker">
                @if($kickerIcon)<i class="bi {{ $kickerIcon }}"></i>@endif {{ $kicker }}
            </div>
        @endif
        <h1 class="h3 mb-0">{{ $title }}</h1>
        @if($subtitle)
            <p class="text-muted mb-0 mt-1">{{ $subtitle }}</p>
        @endif
    </div>

    @isset($actions)
        <div class="d-flex gap-2 flex-wrap align-items-center">{{ $actions }}</div>
    @endisset
</div>
