@props(['value'])

@php $badge = \App\Support\StatusDesign::badge($value); @endphp
<span {{ $attributes->merge(['class' => 'badge ' . $badge['class']]) }}>{{ $badge['label'] }}</span>
