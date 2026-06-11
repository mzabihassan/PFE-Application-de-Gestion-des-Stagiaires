@extends('layouts.app')

@section('title', 'Résumé IA — ' . $intern->user?->full_name)

@section('content')
@php
    $internName = $intern->user?->full_name ?? 'Stagiaire';
@endphp

{{-- ── Page header ───────────────────────────────────────────── --}}
<div class="d-flex align-items-center gap-3 mb-4">
    <a href="{{ route('supervisor.interns.show', $intern) }}" class="btn btn-outline-secondary btn-sm">
        ← Retour
    </a>
    <div>
        <h1 class="h4 mb-0 d-flex align-items-center gap-2">
            <span class="ai-badge-icon">🤖</span> Copilote IA — Résumé Hebdomadaire
        </h1>
        <p class="text-muted mb-0 small">Rapport de suivi pour <strong>{{ $internName }}</strong></p>
    </div>
</div>

<div class="row g-4">

    {{-- ── LEFT: controls ────────────────────────────────────── --}}
    <div class="col-12 col-lg-4">
        <div class="card card-soft">
            <div class="card-body">
                <h5 class="fw-semibold mb-3">⚙️ Paramètres</h5>

                <div class="mb-3">
                    <label class="form-label fw-medium">Stagiaire</label>
                    <div class="d-flex align-items-center gap-2">
                        <div class="avatar-circle-sm bg-primary text-white">
                            {{ strtoupper(substr($internName, 0, 1)) }}
                        </div>
                        <div>
                            <div class="fw-semibold">{{ $internName }}</div>
                            <div class="text-muted small">{{ $intern->school ?? '' }} — {{ $intern->specialty ?? '' }}</div>
                        </div>
                    </div>
                </div>

                <hr>

                <label class="form-label fw-medium">Semaine analysée</label>
                <div class="mb-2">
                    <label class="form-label small text-muted mb-1">Du (lundi)</label>
                    <input type="date" id="week-start" class="form-control" value="{{ now()->startOfWeek()->toDateString() }}">
                </div>
                <div class="mb-3">
                    <label class="form-label small text-muted mb-1">Au (dimanche)</label>
                    <input type="date" id="week-end" class="form-control" value="{{ now()->endOfWeek()->toDateString() }}">
                </div>

                <button id="btn-generate" class="btn btn-primary w-100">
                    <span id="btn-icon">✨</span>
                    <span id="btn-text">Générer le résumé</span>
                    <span id="btn-spinner" class="spinner-border spinner-border-sm ms-2 d-none" role="status"></span>
                </button>

                <div id="agent-error" class="alert alert-danger mt-3 d-none small"></div>
            </div>
        </div>

        {{-- Info card --}}
        <div class="card card-soft mt-3">
            <div class="card-body">
                <h6 class="fw-semibold mb-2">ℹ️ Comment ça marche ?</h6>
                <p class="small text-muted mb-2">
                    L'agent IA analyse automatiquement :
                </p>
                <ul class="small text-muted mb-0 ps-3">
                    <li>Les tâches assignées au stagiaire et leurs <strong>commentaires hebdomadaires</strong></li>
                    <li>Les messages échangés durant la semaine</li>
                    <li>Le sentiment global et les signaux d'alarme</li>
                </ul>
            </div>
        </div>

        {{-- Previous reports --}}
        @if(isset($previousReports) && $previousReports->count() > 0)
            <div class="card card-soft mt-3">
                <div class="card-body">
                    <h6 class="fw-semibold mb-2">📂 Rapports précédents</h6>
                    <div class="list-group list-group-flush" style="max-height: 300px; overflow-y: auto;">
                        @foreach($previousReports as $prevReport)
                            @php
                                $pScoreBadge = $prevReport->week_score >= 80 ? 'success' : ($prevReport->week_score >= 50 ? 'warning' : 'danger');
                            @endphp
                            <a href="#" class="list-group-item list-group-item-action py-2 load-saved-report"
                               data-report-url="{{ route('ai.weekly-summary.saved', $prevReport) }}"
                               data-week-start="{{ $prevReport->week_start->format('Y-m-d') }}"
                               data-week-end="{{ $prevReport->week_end->format('Y-m-d') }}">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <div class="small fw-medium">{{ $prevReport->week_start->format('d/m') }} → {{ $prevReport->week_end->format('d/m/Y') }}</div>
                                        <div class="text-muted" style="font-size: 0.75rem;">{{ ucfirst($prevReport->overall_sentiment) }}</div>
                                    </div>
                                    <span class="badge text-bg-{{ $pScoreBadge }}">{{ $prevReport->week_score }}/100</span>
                                </div>
                            </a>
                        @endforeach
                    </div>
                </div>
            </div>
        @endif
    </div>

    {{-- ── RIGHT: report output ──────────────────────────────── --}}
    <div class="col-12 col-lg-8">

        {{-- Placeholder state --}}
        <div id="report-placeholder" class="card card-soft h-100 d-flex align-items-center justify-content-center text-center py-5">
            <div class="text-muted">
                <div style="font-size:3.5rem; margin-bottom:1rem;">🤖</div>
                <h5 class="fw-semibold">Prêt à analyser</h5>
                <p class="small">Sélectionnez la semaine et cliquez sur <strong>Générer le résumé</strong>.</p>
            </div>
        </div>

        {{-- Loading state --}}
        <div id="report-loading" class="card card-soft h-100 align-items-center justify-content-center text-center py-5 d-none">
            <div class="spinner-border text-primary mb-3" style="width:3rem;height:3rem;" role="status"></div>
            <h5 class="fw-semibold">Analyse en cours…</h5>
            <p class="text-muted small mb-0">L'agent récupère les données et interroge le modèle IA.<br>Cela peut prendre 15–30 secondes.</p>
        </div>

        {{-- Report output (hidden until generated) --}}
        <div id="report-output" class="d-none">

            {{-- Header card --}}
            <div class="card card-soft mb-3">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">
                        <div>
                            <h5 class="fw-bold mb-1" id="r-intern-name"></h5>
                            <div class="text-muted small" id="r-intern-meta"></div>
                            <div class="text-muted small mt-1" id="r-week-range"></div>
                        </div>
                        <div class="text-end">
                            <span id="r-rating-badge" class="badge fs-6 px-3 py-2"></span>
                            <div class="mt-2">
                                <span class="small text-muted">Score semaine :</span>
                                <strong id="r-week-score" class="ms-1"></strong><span class="text-muted">/100</span>
                            </div>
                        </div>
                    </div>
                    <hr class="my-3">
                    <p id="r-summary" class="mb-0 text-secondary"></p>
                </div>
            </div>

            <div class="row g-3 mb-3">
                {{-- Metrics row --}}
                <div class="col-sm-4">
                    <div class="card card-soft text-center">
                        <div class="card-body py-3">
                            <div class="h2 mb-0 fw-bold text-success" id="r-completion"></div>
                            <div class="small text-muted">Tâches complétées</div>
                        </div>
                    </div>
                </div>
                <div class="col-sm-4">
                    <div class="card card-soft text-center">
                        <div class="card-body py-3">
                            <div class="h2 mb-0 fw-bold" id="r-engagement-score" style="color:var(--bs-primary)"></div>
                            <div class="small text-muted">Score d'engagement /10</div>
                        </div>
                    </div>
                </div>
                <div class="col-sm-4">
                    <div class="card card-soft text-center">
                        <div class="card-body py-3">
                            <div class="h3 mb-1" id="r-sentiment-icon"></div>
                            <div class="small fw-semibold" id="r-sentiment-label"></div>
                            <div class="small text-muted">Sentiment</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row g-3 mb-3">
                {{-- Achievements --}}
                <div class="col-md-6">
                    <div class="card card-soft h-100">
                        <div class="card-body">
                            <h6 class="fw-semibold mb-3">🏆 Réalisations</h6>
                            <ul id="r-achievements" class="list-unstyled mb-0 small"></ul>
                        </div>
                    </div>
                </div>
                {{-- Blockers --}}
                <div class="col-md-6">
                    <div class="card card-soft h-100">
                        <div class="card-body">
                            <h6 class="fw-semibold mb-3">🚧 Blocages & Difficultés</h6>
                            <ul id="r-blockers" class="list-unstyled mb-0 small"></ul>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Wellbeing --}}
            <div class="card card-soft mb-3">
                <div class="card-body">
                    <h6 class="fw-semibold mb-2">💭 État du stagiaire</h6>
                    <p id="r-wellbeing" class="small text-muted mb-0"></p>
                </div>
            </div>

            {{-- Red flags --}}
            <div id="r-flags-section" class="alert alert-danger d-none mb-3">
                <h6 class="fw-semibold mb-2">⚠️ Signaux d'alarme</h6>
                <ul id="r-red-flags" class="mb-0 small"></ul>
            </div>

            {{-- Overdue tasks --}}
            <div id="r-overdue-section" class="alert alert-warning d-none mb-3">
                <h6 class="fw-semibold mb-2">⏰ Tâches en retard</h6>
                <ul id="r-overdue-tasks" class="mb-0 small"></ul>
            </div>

            {{-- Recommended actions --}}
            <div class="card card-soft mb-3">
                <div class="card-body">
                    <h6 class="fw-semibold mb-3">📋 Actions recommandées pour l'encadrant</h6>
                    <ul id="r-actions" class="list-unstyled mb-0 small"></ul>
                </div>
            </div>

        </div>{{-- /#report-output --}}
    </div>
</div>
@endsection

@push('scripts')
<script>
$(function () {
    const internId    = {{ $intern->id }};
    const generateUrl = '{{ route('ai.weekly-summary.generate', $intern) }}';
    const csrfToken   = $('meta[name="csrf-token"]').attr('content');

    // ── Sentiment helpers ──────────────────────────────────────
    const sentimentIcon = {
        positive:    '😊',
        neutral:     '😐',
        negative:    '😟',
        concerning:  '😰',
    };
    const sentimentColor = {
        positive:    'text-success',
        neutral:     'text-secondary',
        negative:    'text-warning',
        concerning:  'text-danger',
    };
    const ratingColor = {
        success: 'text-bg-success',
        primary: 'text-bg-primary',
        warning: 'text-bg-warning',
        danger:  'text-bg-danger',
    };

    function buildList(selector, items, emptyMsg = 'Aucun.') {
        const $ul = $(selector).empty();
        if (!items || items.length === 0) {
            $ul.append(`<li class="text-muted">${emptyMsg}</li>`);
            return;
        }
        items.forEach(item => {
            $ul.append(`<li class="mb-1">• ${$('<div>').text(item).html()}</li>`);
        });
    }

    // ── Generate click ─────────────────────────────────────────
    $('#btn-generate').on('click', function () {
        const weekStart = $('#week-start').val();
        const weekEnd   = $('#week-end').val();

        if (!weekStart || !weekEnd) {
            toast('Veuillez sélectionner une semaine.', 'warning');
            return;
        }

        // UI: loading state
        $('#report-placeholder').addClass('d-none');
        $('#report-output').addClass('d-none');
        $('#report-loading').removeClass('d-none');
        $('#agent-error').addClass('d-none');
        $('#btn-generate').prop('disabled', true);
        $('#btn-spinner').removeClass('d-none');
        $('#btn-text').text('Génération…');

        $.ajax({
            url: generateUrl,
            method: 'POST',
            data: {
                _token:     csrfToken,
                week_start: weekStart,
                week_end:   weekEnd,
            },
        })
        .done(function (data) {
            if (!data.success) {
                showError(data.error || 'Erreur inconnue.');
                return;
            }
            renderReport(data);
        })
        .fail(function (xhr) {
            const msg = xhr.responseJSON?.error
                || xhr.responseJSON?.message
                || `Erreur HTTP ${xhr.status}.`;
            showError(msg);
        })
        .always(function () {
            $('#report-loading').addClass('d-none');
            $('#btn-generate').prop('disabled', false);
            $('#btn-spinner').addClass('d-none');
            $('#btn-text').text('Générer le résumé');
        });
    });

    function showError(msg) {
        $('#report-loading').addClass('d-none');
        $('#report-placeholder').removeClass('d-none');
        $('#agent-error').removeClass('d-none').text('❌ ' + msg);
    }

    function renderReport(data) {
        const r = data.report || {};

        // Header
        $('#r-intern-name').text(r.intern_name || '—');
        $('#r-intern-meta').text(`${r.intern_school || ''} — ${r.intern_specialty || ''}`);
        $('#r-week-range').text(`Semaine du ${data.week_start} au ${data.week_end}`);
        $('#r-summary').text(r.executive_summary || '');

        // Rating badge
        const color = ratingColor[r.rating_color] || 'text-bg-secondary';
        $('#r-rating-badge').attr('class', `badge fs-6 px-3 py-2 ${color}`).text(r.overall_rating || '—');
        $('#r-week-score').text(r.week_score ?? '—');

        // Metrics
        $('#r-completion').text(`${r.task_completion_rate ?? '—'}%`);
        $('#r-engagement-score').text(r.engagement_score ?? '—');

        const sentiment = r.overall_sentiment || 'neutral';
        $('#r-sentiment-icon').text(sentimentIcon[sentiment] || '😐');
        $('#r-sentiment-label')
            .attr('class', `small fw-semibold ${sentimentColor[sentiment] || ''}`)
            .text(r.overall_sentiment || '—');

        // Lists
        buildList('#r-achievements', r.achievements, 'Aucune réalisation enregistrée.');
        buildList('#r-blockers',     r.blockers,     'Aucun blocage détecté.');
        buildList('#r-actions',      r.recommended_actions, 'Aucune action recommandée.');

        // Wellbeing
        $('#r-wellbeing').text(r.sentiment_summary || '—');

        // Red flags
        if (r.red_flags && r.red_flags.length > 0) {
            $('#r-flags-section').removeClass('d-none');
            buildList('#r-red-flags', r.red_flags);
        } else {
            $('#r-flags-section').addClass('d-none');
        }

        // Overdue tasks
        if (r.overdue_tasks && r.overdue_tasks.length > 0) {
            $('#r-overdue-section').removeClass('d-none');
            buildList('#r-overdue-tasks', r.overdue_tasks);
        } else {
            $('#r-overdue-section').addClass('d-none');
        }

        // Show
        $('#report-output').removeClass('d-none');
    }

    // ── Load saved report from sidebar ───────────────────────────
    $(document).on('click', '.load-saved-report', function (e) {
        e.preventDefault();
        const $link = $(this);
        const url = $link.data('report-url');

        // Update date fields
        $('#week-start').val($link.data('week-start'));
        $('#week-end').val($link.data('week-end'));

        // Highlight active
        $('.load-saved-report').removeClass('active');
        $link.addClass('active');

        // Show loading
        $('#report-placeholder').addClass('d-none');
        $('#report-output').addClass('d-none');
        $('#report-loading').removeClass('d-none');
        $('#agent-error').addClass('d-none');

        $.ajax({ url: url, method: 'GET' })
        .done(function (data) {
            if (!data.success) {
                showError(data.error || 'Erreur inconnue.');
                return;
            }
            renderReport(data);
        })
        .fail(function (xhr) {
            showError('Impossible de charger le rapport.');
        })
        .always(function () {
            $('#report-loading').addClass('d-none');
        });
    });
});
</script>

<style>
    .ai-badge-icon { font-size: 1.5rem; }
    .avatar-circle-sm {
        width: 36px; height: 36px;
        border-radius: 50%;
        display: flex; align-items: center; justify-content: center;
        font-weight: 700; font-size: 1rem;
        flex-shrink: 0;
    }
</style>
@endpush
