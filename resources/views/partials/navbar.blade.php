@php
    $authUser = auth()->user();

    // ── Flat, role-aware navigation: daily-use flows live at the top level ──
    $primary = [];
    $add = function (string $label, string $route, array $active) use (&$primary, $authUser) {
        $primary[] = [
            'label'  => $label,
            'url'    => route($route),
            'active' => request()->routeIs(...$active),
        ];
    };

    $add('Dashboard', 'dashboard', ['dashboard']);

    if ($authUser->hasRole('Administrateur', 'Responsable RH', 'Responsable de competence')) {
        $add('Stagiaires', 'interns.index', ['interns.*']);
    }
    if ($authUser->hasRole('Encadrant')) {
        $add('Mes stagiaires', 'supervisor.interns', ['supervisor.interns', 'supervisor.interns.show']);
    }
    if ($authUser->hasRole('Administrateur', 'Responsable de competence')) {
        $add('Stages', 'internships.index', ['internships.*']);
    }
    if ($authUser->hasRole('Encadrant')) {
        $add('Mes stages', 'supervisor.internships', ['supervisor.internships', 'supervisor.internships.show']);
    }
    if ($authUser->hasRole('Administrateur', 'Encadrant', 'Stagiaire')) {
        $add('Tâches', 'tasks.index', ['tasks.*']);
    }
    if ($authUser->hasRole('Stagiaire')) {
        $add('Journal', 'daily-log.index', ['daily-log.*']);
    }
    if ($authUser->hasRole('Administrateur', 'Responsable de competence', 'Encadrant', 'Stagiaire')) {
        $add('Demandes', 'requests.index', ['requests.*']);
    }
    if ($authUser->hasRole('Administrateur', 'Responsable RH')) {
        $add('Attestations', 'rh.attestations.index', ['rh.attestations.*']);
    }
    $add('Messages', 'messages.index', ['messages.*']);

    // ── Secondary items grouped under "Plus" ──
    $more = [];
    if ($authUser->hasRole('Administrateur', 'Responsable RH', 'Responsable de competence')) {
        $more[] = ['label' => 'Absences', 'url' => route('absences.index'), 'active' => request()->routeIs('absences.*')];
    }
    if ($authUser->hasRole('Administrateur', 'Responsable RH')) {
        $more[] = ['label' => 'Archives', 'url' => route('rh.archives.index'), 'active' => request()->routeIs('rh.archives.*')];
    }
    if ($authUser->hasRole('Responsable RH')) {
        $more[] = ['label' => 'Profil RH', 'url' => route('rh.profile'), 'active' => request()->routeIs('rh.profile')];
    }
    if ($authUser->hasRole('Administrateur')) {
        $more[] = ['label' => 'Utilisateurs', 'url' => route('users.index'), 'active' => request()->routeIs('users.*')];
    }
    $moreActive = collect($more)->contains('active', true);

    $roleName = $authUser->role?->name ?? '-';
    $profileLabel = $authUser->full_name . ' (' . $roleName . ')';
    if ($authUser->hasRole('Stagiaire') && $authUser->intern !== null) {
        $isActiveIntern = $authUser->intern->internships()->where('status', 'en_cours')->exists();
        $profileLabel = $authUser->full_name . ' (' . $roleName . '/' . ($isActiveIntern ? 'actif' : 'inactif') . ')';
    }

    $nameParts = preg_split('/\s+/', trim((string) $authUser->full_name));
    $initials = strtoupper(
        mb_substr($nameParts[0] ?? 'U', 0, 1)
        . (count($nameParts) > 1 ? mb_substr(end($nameParts), 0, 1) : '')
    );
@endphp

<nav class="app-nav">
    <div class="app-nav-inner">
        <a class="app-brand" href="{{ route('dashboard') }}">
            <img src="{{ asset('images/ALTEN-Logo.wine.png') }}" alt="ALTEN">
            <span class="brand-text">
                <span class="brand-name">Stagiaires</span>
                <span class="brand-sub">ALTEN · Pilotage</span>
            </span>
        </a>

        <button class="nav-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav" aria-label="Menu" aria-expanded="false">
            <i class="bi bi-list"></i>
        </button>

        <div class="app-nav-collapse" id="mainNav">
            <ul class="app-nav-links">
                @foreach($primary as $link)
                    <li>
                        <a class="nav-link {{ $link['active'] ? 'active' : '' }}" href="{{ $link['url'] }}">{{ $link['label'] }}</a>
                    </li>
                @endforeach

                @if(! empty($more))
                    <li class="dropdown">
                        <a class="nav-link dropdown-toggle {{ $moreActive ? 'active' : '' }}" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">Plus</a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            @foreach($more as $link)
                                <li><a class="dropdown-item {{ $link['active'] ? 'active' : '' }}" href="{{ $link['url'] }}">{{ $link['label'] }}</a></li>
                            @endforeach
                        </ul>
                    </li>
                @endif
            </ul>

            <span class="nav-spacer"></span>

            <div class="app-nav-user">
                <a class="nav-profile" href="{{ route('profile.edit') }}" title="{{ $profileLabel }}">
                    <span class="avatar">{{ $initials }}</span>
                    <span class="nav-profile-label">{{ $profileLabel }}</span>
                </a>
                <form action="{{ route('logout') }}" method="POST">
                    @csrf
                    <button class="btn btn-primary btn-sm" type="submit">Déconnexion</button>
                </form>
            </div>
        </div>
    </div>
</nav>
