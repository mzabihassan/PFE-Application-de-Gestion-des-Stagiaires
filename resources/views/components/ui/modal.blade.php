@props([
    'id',
    'title' => null,
    'size' => null,
])

<div class="modal" id="{{ $id }}" tabindex="-1" aria-hidden="true" aria-labelledby="{{ $id }}-title">
    <div class="modal-dialog" @if($size === 'lg') style="max-width:720px" @elseif($size === 'sm') style="max-width:24rem" @endif>
        <div class="modal-content">
            @if($title || isset($header))
                <div class="modal-header">
                    <h5 class="modal-title" id="{{ $id }}-title">{{ $header ?? $title }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
                </div>
            @endif

            <div class="modal-body">{{ $slot }}</div>

            @isset($footer)
                <div class="modal-footer">{{ $footer }}</div>
            @endisset
        </div>
    </div>
</div>
