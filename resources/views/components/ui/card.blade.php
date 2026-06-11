@props([
    'soft' => true,
    'padded' => true,
])

<div {{ $attributes->merge(['class' => $soft ? 'card card-soft' : 'card']) }}>
    @if($padded)
        <div class="card-body">{{ $slot }}</div>
    @else
        {{ $slot }}
    @endif
</div>
