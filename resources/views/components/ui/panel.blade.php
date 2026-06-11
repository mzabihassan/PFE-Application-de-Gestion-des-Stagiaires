@props([
    'title',
    'icon' => 'bi-inbox',
    'tone' => 'default',
    'count' => null,
    'viewUrl' => null,
    'viewLabel' => 'Tout voir',
])

<div {{ $attributes->merge(['class' => 'panel panel-' . $tone . ' h-100']) }}>
    <div class="panel-head">
        <div class="section-title" style="margin:0">
            <span class="module-icon"><i class="bi {{ $icon }}"></i></span>
            <span class="panel-title">{{ $title }}</span>
        </div>
        @if($count !== null)
            <span class="panel-count">{{ $count }}</span>
        @endif
    </div>

    <div class="panel-body">{{ $slot }}</div>

    @if($viewUrl)
        <a class="panel-foot" href="{{ $viewUrl }}">{{ $viewLabel }} <i class="bi bi-arrow-right"></i></a>
    @endif
</div>
