{{--
    Flash + validation messages are surfaced as toast popups (see public/js/ui.js).
    Messages are queued on window.__toasts and flushed once the UI layer loads.
--}}
@php
    $flashes = [];
    if (session('success')) { $flashes[] = ['type' => 'success', 'message' => session('success')]; }
    if (session('error'))   { $flashes[] = ['type' => 'error',   'message' => session('error')]; }
    if (session('warning')) { $flashes[] = ['type' => 'warning', 'message' => session('warning')]; }
    if (session('status'))  { $flashes[] = ['type' => 'info',    'message' => session('status')]; }
    foreach ($errors->all() as $validationError) {
        $flashes[] = ['type' => 'error', 'message' => $validationError];
    }
@endphp

@if(! empty($flashes))
    <script>
        window.__toasts = window.__toasts || [];
        @foreach($flashes as $flash)
            window.__toasts.push({ message: @json($flash['message']), type: @json($flash['type']) });
        @endforeach
    </script>
@endif
