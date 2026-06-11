@props([
    'search' => null,          // valeur actuelle de la recherche ; null => pas de champ recherche
    'searchName' => 'search',
    'placeholder' => 'Rechercher…',
    'preserve' => [],          // paramètres à conserver (champs cachés), ex: ['view' => $view]
    'action' => null,
])

{{--
    Barre d'outils unifiée pour les tableaux : un champ de recherche (loupe + croix
    de réinitialisation) et des filtres (selects) passés via le slot. Tout est dans
    un seul formulaire GET qui se soumet automatiquement (voir public/js/ui.js) :
    recherche après 3 caractères avec un délai de 600 ms, ou dès qu'un filtre change.
--}}
<form method="GET" @if($action) action="{{ $action }}" @endif data-autosearch class="table-toolbar">
    @foreach($preserve as $key => $value)
        @if($value !== null && $value !== '')
            <input type="hidden" name="{{ $key }}" value="{{ $value }}">
        @endif
    @endforeach

    @if($search !== null)
        <label class="toolbar-search">
            <i class="bi bi-search toolbar-search-icon"></i>
            <input type="text" name="{{ $searchName }}" value="{{ $search }}"
                   class="toolbar-search-input" placeholder="{{ $placeholder }}"
                   autocomplete="off" data-autosearch-input>
            <button type="button" class="toolbar-search-clear {{ trim((string) $search) === '' ? 'is-hidden' : '' }}"
                    data-autosearch-clear aria-label="Effacer la recherche"><i class="bi bi-x-lg"></i></button>
        </label>
    @endif

    @if(trim($slot->toHtml()) !== '')
        <div class="toolbar-filters">{{ $slot }}</div>
    @endif

    <noscript><button type="submit" class="btn btn-sm btn-outline-secondary">Chercher</button></noscript>
</form>
