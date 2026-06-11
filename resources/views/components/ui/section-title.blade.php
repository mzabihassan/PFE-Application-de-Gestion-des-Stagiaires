@props([
    'icon',
    'tag' => 'h2',
    'level' => 'h5',
])

<{{ $tag }} {{ $attributes->merge(['class' => $level . ' mb-3 section-title']) }}>
    <span class="module-icon"><i class="bi {{ $icon }}"></i></span> {{ $slot }}
</{{ $tag }}>
