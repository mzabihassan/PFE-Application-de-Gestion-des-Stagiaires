<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Gestion des Stagiaires')</title>

    {{-- Icons + third-party widget styles (framework-agnostic) --}}
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet">

    {{-- Typeface system: Fraunces (display) · Hanken Grotesk (body) · JetBrains Mono (data) --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Syne:wght@600;700;800&family=Hanken+Grotesk:wght@400;500;600;700&family=JetBrains+Mono:wght@500;700&display=swap" rel="stylesheet">

    {{-- Design system --}}
    <link href="{{ asset('css/app.css') }}" rel="stylesheet">

    @stack('head')
</head>
<body class="@auth app-shell @endauth">
    @auth
        @include('partials.navbar')
    @endauth

    <main class="container py-4">
        @include('partials.alerts')
        @yield('content')
    </main>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/fr.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="{{ asset('js/ui.js') }}"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            if (window.flatpickr) {
                flatpickr('.js-date', { dateFormat: 'd/m/Y', allowInput: true, locale: 'fr' });
            }
        });
    </script>
    @stack('scripts')

    @auth
        @include('partials.ai-chat')
    @endauth
</body>
</html>
