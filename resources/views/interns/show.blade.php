@extends('layouts.app')

@section('title', 'Detail stagiaire')

@section('content')
@php
    $canViewInternTasks = ! auth()->user()->hasRole('Responsable de competence', 'Responsable RH');
    $isSupervisor = auth()->user()->hasRole('Encadrant');
    $isRh = auth()->user()->hasRole('Responsable RH');
    $scoreColor = match ($score['badge']) {
        'success' => '#1B9E6A',
        'warning' => '#DE920B',
        'danger' => '#D2453B',
        default => '#9AA29B',
    };
    $hasScoreData = $score['has_data'] ?? false;
    $supervisors = $intern->internships
        ->pluck('supervisor')
        ->filter()
        ->unique('id')
        ->pluck('full_name')
        ->values();
    $latestInternship = $intern->internships->sortByDesc('end_date')->first();
    $attestationRequest = $intern->requests
        ->where('type', 'attestation')
        ->sortByDesc('created_at')
        ->first();
    $duration = $intern->start_date && $intern->end_date
        ? $intern->start_date->diffInDays($intern->end_date) + 1 . ' jours'
        : '-';
    $stageStatus = $intern->is_archived
        ? 'archive'
        : ($latestInternship?->status ?? ($latestInternship ? 'en_cours' : 'en_attente'));
    $historyItems = collect([
        ['label' => 'Rapport envoyé', 'date' => $attestationRequest?->created_at, 'show' => $attestationRequest?->report_path !== null],
        ['label' => 'Validé par encadrant', 'date' => $attestationRequest?->supervisor_validated_at, 'show' => $attestationRequest?->supervisor_validated_at !== null],
        ['label' => 'Validé par RC', 'date' => $attestationRequest?->rc_validated_at, 'show' => $attestationRequest?->rc_validated_at !== null],
        ['label' => 'Transmis au RH', 'date' => $attestationRequest?->sent_to_rh_at, 'show' => $attestationRequest?->sent_to_rh_at !== null],
        ['label' => 'Attestation générée', 'date' => $attestationRequest?->rh_processed_at, 'show' => $attestationRequest?->rh_processed_at !== null],
        ['label' => 'Attestation imprimée', 'date' => $attestationRequest?->attestation_printed_at, 'show' => $attestationRequest?->attestation_printed_at !== null],
        ['label' => 'Attestation récupérée', 'date' => $attestationRequest?->attestation_recovered_at, 'show' => $attestationRequest?->attestation_recovered_at !== null],
        ['label' => 'Dossier archivé', 'date' => $attestationRequest?->attestation_archived_at, 'show' => $attestationRequest?->attestation_archived_at !== null],
    ])->where('show', true);
@endphp

<x-ui.page-header :title="$intern->user?->full_name ?? 'Stagiaire non lié'" kicker="Fiche stagiaire" kicker-icon="bi-person-vcard"
                  :subtitle="$intern->school . ' · ' . $intern->specialty">
    <x-slot:actions>
        <a href="{{ $isSupervisor ? route('supervisor.interns') : route('interns.index') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> Retour</a>
        @if($isSupervisor || auth()->user()->hasRole('Administrateur'))
            <a href="{{ route('ai.weekly-summary', $intern) }}" class="btn btn-sm btn-primary">
                <i class="bi bi-stars"></i> Résumé IA
            </a>
        @endif
        @unless($isSupervisor || $isRh)
            <a href="{{ route('interns.edit', $intern) }}" class="btn btn-outline-primary btn-sm"><i class="bi bi-pencil"></i> Modifier</a>
        @endunless
    </x-slot:actions>
</x-ui.page-header>

<div class="row g-4 mb-4 align-items-start">
    <div class="col-xl-7 col-lg-6 fade-in">
        <div class="card card-soft">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start gap-3 mb-3">
                    <div>
                        <h2 class="h5 mb-1 section-title"><span class="module-icon"><i class="bi bi-speedometer2"></i></span> Score automatique</h2>
                        <p class="text-muted mb-0 mt-2">
                            @if($hasScoreData)
                                Moyenne calculée à partir de {{ $score['report_count'] }} rapport(s) IA.
                            @else
                                Aucun rapport IA généré. Le score sera disponible après le premier résumé hebdomadaire.
                            @endif
                        </p>
                    </div>
                    <span class="badge text-bg-{{ $score['badge'] }}">{{ $score['label'] }}</span>
                </div>

                <div class="row g-3 align-items-center mb-4">
                    <div class="col-sm-4">
                        <div class="score-donut" style="--score: {{ $score['score'] ?? 0 }}; --score-color: {{ $scoreColor }};">
                            <div class="text-center">
                                <div class="score-donut-value">{{ $score['score'] !== null ? $score['score'] . '%' : 'N/A' }}</div>
                                <div class="score-donut-caption">Score</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-sm-8">
                        @if($hasScoreData)
                        <div class="row g-2">
                            <div class="col-4">
                                <div class="score-metric text-center">
                                    <div class="fw-semibold">{{ $score['avg_engagement'] }}/10</div>
                                    <small class="text-muted">Engagement</small>
                                </div>
                            </div>
                            <div class="col-4">
                                <div class="score-metric text-center">
                                    <div class="fw-semibold">{{ $score['avg_completion'] }}%</div>
                                    <small class="text-muted">Tâches</small>
                                </div>
                            </div>
                            <div class="col-4">
                                <div class="score-metric text-center">
                                    <div class="fw-semibold">{{ $score['report_count'] }}</div>
                                    <small class="text-muted">Rapports</small>
                                </div>
                            </div>
                        </div>
                        @else
                        <div class="empty-state">
                            <span class="empty-state-icon"><i class="bi bi-robot"></i></span>
                            <div class="small">Demandez à l'encadrant de générer un résumé IA pour calculer le score.</div>
                        </div>
                        @endif
                    </div>
                </div>

                <div>
                    <div class="d-flex justify-content-between align-items-center gap-3 mb-2">
                        <h3 class="h6 mb-0 section-title"><span class="module-icon"><i class="bi bi-bar-chart-line"></i></span> Tâches terminées / semaine</h3>
                        <span class="text-muted small">6 dernières semaines</span>
                    </div>
                    <div class="weekly-chart">
                        @foreach($taskCompletionChart['labels'] as $index => $label)
                            @php
                                $value = $taskCompletionChart['values'][$index] ?? 0;
                                $height = $taskCompletionChart['max'] > 0 ? max(8, round(($value / $taskCompletionChart['max']) * 100)) : 4;
                            @endphp
                            <div class="weekly-chart-bar">
                                <div class="weekly-chart-value">{{ $value }}</div>
                                <div class="weekly-chart-fill" style="height: {{ $height }}%;"></div>
                                <div class="weekly-chart-label">{{ $label }}</div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-5 col-lg-6 fade-in">
        <div class="card card-soft">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start gap-3 mb-3">
                    <div>
                        <h2 class="h5 mb-1 section-title"><span class="module-icon"><i class="bi bi-exclamation-triangle"></i></span> Alertes intelligentes</h2>
                        <p class="text-muted mb-0 mt-2">Risques détectés sur les tâches et absences.</p>
                    </div>
                    <span class="badge text-bg-secondary">{{ count($alerts) }}</span>
                </div>
                @forelse($alerts as $alert)
                    <div class="alert alert-warning alert-dismissible" role="alert">
                        <div>{{ $alert['message'] }}</div>
                        @isset($alert['task'])
                            <small class="text-muted">Tâche : {{ $alert['task']->title }}</small>
                        @endisset
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fermer"></button>
                    </div>
                @empty
                    <div class="empty-state">
                        <span class="empty-state-icon"><i class="bi bi-check2-circle"></i></span>
                        <div>
                            <div class="fw-semibold text-body">Aucune alerte</div>
                            <div class="small">Aucun risque détecté pour ce stagiaire.</div>
                        </div>
                    </div>
                @endforelse
            </div>
        </div>
    </div>
</div>

<div class="card card-soft fade-in">
    <div class="card-body">
        <ul class="nav nav-tabs flex-nowrap overflow-auto mb-4" id="internDetailsTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active text-nowrap" id="infos-tab" data-bs-toggle="tab" data-bs-target="#infos-pane" type="button" role="tab">Profil &amp; stage</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link text-nowrap" id="absences-tab" data-bs-toggle="tab" data-bs-target="#absences-pane" type="button" role="tab">Absences</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link text-nowrap" id="ai-reports-tab" data-bs-toggle="tab" data-bs-target="#ai-reports-pane" type="button" role="tab">Rapports IA</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link text-nowrap" id="attestation-tab" data-bs-toggle="tab" data-bs-target="#attestation-pane" type="button" role="tab">Attestation</button>
            </li>
        </ul>

        <div class="tab-content" id="internDetailsTabsContent">
            <div class="tab-pane active" id="infos-pane" role="tabpanel" aria-labelledby="infos-tab" tabindex="0">
                <div class="row g-4">
                    <div class="col-lg-6">
                        <h3 class="h6 text-muted text-uppercase mb-2" style="letter-spacing:.06em;font-size:.72rem">Identité</h3>
                        <dl class="row mb-0">
                            <dt class="col-sm-4">Nom</dt>
                            <dd class="col-sm-8">{{ $intern->user?->full_name ?? '-' }}</dd>
                            <dt class="col-sm-4">Email</dt>
                            <dd class="col-sm-8">{{ $intern->user?->email ?? '-' }}</dd>
                            <dt class="col-sm-4">Téléphone</dt>
                            <dd class="col-sm-8">{{ $intern->phone ?? '-' }}</dd>
                            <dt class="col-sm-4">CIN</dt>
                            <dd class="col-sm-8">{{ $intern->cin }}</dd>
                            <dt class="col-sm-4">École</dt>
                            <dd class="col-sm-8">{{ $intern->school }}</dd>
                            <dt class="col-sm-4">Spécialité</dt>
                            <dd class="col-sm-8">{{ $intern->specialty }}</dd>
                        </dl>
                    </div>
                    <div class="col-lg-6">
                        <h3 class="h6 text-muted text-uppercase mb-2" style="letter-spacing:.06em;font-size:.72rem">Stage &amp; encadrement</h3>
                        <dl class="row mb-0">
                            <dt class="col-sm-4">Sujet</dt>
                            <dd class="col-sm-8">{{ $latestInternship?->title ?? '-' }}</dd>
                            <dt class="col-sm-4">Département</dt>
                            <dd class="col-sm-8">{{ $latestInternship?->department ?? '-' }}</dd>
                            <dt class="col-sm-4">Période</dt>
                            <dd class="col-sm-8">{{ $intern->start_date?->format('d/m/Y') ?? '-' }} → {{ $intern->end_date?->format('d/m/Y') ?? '-' }} <span class="text-muted">({{ $duration }})</span></dd>
                            <dt class="col-sm-4">Statut</dt>
                            <dd class="col-sm-8">@statusBadge($stageStatus)</dd>
                            <dt class="col-sm-4">Encadrant</dt>
                            <dd class="col-sm-8">{{ $supervisors->isNotEmpty() ? $supervisors->join(', ') : '-' }}</dd>
                            <dt class="col-sm-4">Resp. compétence</dt>
                            <dd class="col-sm-8">{{ $latestInternship?->responsible?->full_name ?? '-' }}</dd>
                        </dl>
                    </div>
                </div>
            </div>

            <div class="tab-pane" id="absences-pane" role="tabpanel" aria-labelledby="absences-tab" tabindex="0">
                <div class="d-flex flex-wrap gap-2 mb-3">
                    <span class="badge text-bg-secondary">Total : {{ $intern->absences->count() }}</span>
                    <span class="badge text-bg-danger">Non justifiées : {{ $intern->absences->where('justified', false)->count() }}</span>
                </div>
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Motif</th>
                                <th>Statut</th>
                                <th>Ajouté par</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($intern->absences->sortByDesc('date_absence') as $absence)
                                <tr>
                                    <td>{{ $absence->date_absence?->format('d/m/Y') ?? '-' }}</td>
                                    <td>{{ $absence->reason ?? '-' }}</td>
                                    <td>@statusBadge($absence->justified ? 'valide' : 'en_attente')</td>
                                    <td>{{ $absence->recordedBy?->full_name ?? '-' }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="text-muted">Aucune absence enregistrée.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="tab-pane" id="ai-reports-pane" role="tabpanel" aria-labelledby="ai-reports-tab" tabindex="0">
                @if(isset($weeklyReports) && $weeklyReports->count() > 0)
                    <div class="d-flex flex-wrap gap-2 mb-3">
                        <span class="badge text-bg-primary">{{ $weeklyReports->count() }} rapport(s)</span>
                        <span class="badge text-bg-info">Score moyen : {{ round($weeklyReports->avg('week_score')) }}/100</span>
                    </div>
                    <div class="accordion" id="aiReportsAccordion">
                        @foreach($weeklyReports as $rIndex => $report)
                            @php
                                $sentimentIcon = match($report->overall_sentiment) {
                                    'positive' => '😊',
                                    'negative' => '😟',
                                    'concerning' => '😰',
                                    default => '😐',
                                };
                                $scoreBadge = $report->week_score >= 80 ? 'success' : ($report->week_score >= 50 ? 'warning' : 'danger');
                                $rData = $report->report_json ?? [];
                            @endphp
                            <div class="accordion-item">
                                <h2 class="accordion-header">
                                    <button class="accordion-button {{ $rIndex > 0 ? 'collapsed' : '' }}" type="button" data-bs-toggle="collapse" data-bs-target="#aiReport{{ $report->id }}">
                                        <div class="d-flex align-items-center gap-3 w-100">
                                            <span class="badge text-bg-{{ $scoreBadge }}">{{ $report->week_score }}/100</span>
                                            <span>Semaine du {{ $report->week_start->format('d/m/Y') }} au {{ $report->week_end->format('d/m/Y') }}</span>
                                            <span>{{ $sentimentIcon }}</span>
                                            <span class="text-muted small ms-auto me-3">par {{ $report->generatedBy?->full_name ?? '?' }}</span>
                                        </div>
                                    </button>
                                </h2>
                                <div id="aiReport{{ $report->id }}" class="accordion-collapse collapse {{ $rIndex === 0 ? 'show' : '' }}" data-bs-parent="#aiReportsAccordion">
                                    <div class="accordion-body">
                                        <div class="row g-3 mb-3">
                                            <div class="col-sm-4">
                                                <div class="score-metric text-center">
                                                    <div class="h4 mb-0 fw-bold text-success">{{ $rData['task_completion_rate'] ?? $report->task_completion_rate }}%</div>
                                                    <small class="text-muted">Tâches complétées</small>
                                                </div>
                                            </div>
                                            <div class="col-sm-4">
                                                <div class="score-metric text-center">
                                                    <div class="h4 mb-0 fw-bold text-primary">{{ $rData['engagement_score'] ?? $report->engagement_score }}/10</div>
                                                    <small class="text-muted">Engagement</small>
                                                </div>
                                            </div>
                                            <div class="col-sm-4">
                                                <div class="score-metric text-center">
                                                    <div class="h4 mb-0">{{ $sentimentIcon }}</div>
                                                    <small class="text-muted">{{ ucfirst($report->overall_sentiment) }}</small>
                                                </div>
                                            </div>
                                        </div>

                                        @if(!empty($rData['executive_summary']))
                                            <h6 class="fw-semibold">📝 Résumé</h6>
                                            <p class="small text-secondary mb-3">{{ $rData['executive_summary'] }}</p>
                                        @endif

                                        <div class="row g-3 mb-3">
                                            @if(!empty($rData['achievements']))
                                                <div class="col-md-6">
                                                    <h6 class="fw-semibold">🏆 Réalisations</h6>
                                                    <ul class="small mb-0">
                                                        @foreach($rData['achievements'] as $item)
                                                            <li>{{ $item }}</li>
                                                        @endforeach
                                                    </ul>
                                                </div>
                                            @endif
                                            @if(!empty($rData['blockers']))
                                                <div class="col-md-6">
                                                    <h6 class="fw-semibold">🚧 Blocages</h6>
                                                    <ul class="small mb-0">
                                                        @foreach($rData['blockers'] as $item)
                                                            <li>{{ $item }}</li>
                                                        @endforeach
                                                    </ul>
                                                </div>
                                            @endif
                                        </div>

                                        @if(!empty($rData['recommended_actions']))
                                            <h6 class="fw-semibold">📋 Actions recommandées</h6>
                                            <ul class="small mb-0">
                                                @foreach($rData['recommended_actions'] as $item)
                                                    <li>{{ $item }}</li>
                                                @endforeach
                                            </ul>
                                        @endif

                                        @if(!empty($rData['red_flags']))
                                            <div class="alert alert-danger mt-3 mb-0 small">
                                                <h6 class="fw-semibold">⚠️ Signaux d'alarme</h6>
                                                <ul class="mb-0">
                                                    @foreach($rData['red_flags'] as $item)
                                                        <li>{{ $item }}</li>
                                                    @endforeach
                                                </ul>
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="text-center text-muted py-4">
                        <i class="bi bi-robot fs-1 d-block mb-2"></i>
                        <p class="fw-semibold">Aucun rapport IA disponible</p>
                        <p class="small">L'encadrant doit générer un résumé hebdomadaire IA depuis la page « Résumé IA ».</p>
                    </div>
                @endif
            </div>

            <div class="tab-pane" id="attestation-pane" role="tabpanel" aria-labelledby="attestation-tab" tabindex="0">
                @if($attestationRequest)
                    <div class="mb-3">
                        <div class="d-flex align-items-center gap-2 mb-2">
                            <span class="text-muted small">Étape actuelle</span>
                            <span class="badge text-bg-primary">{{ \App\Support\AttestationWorkflow::label($attestationRequest->workflow_status) }}</span>
                        </div>
                        @include('partials.attestation-timeline', ['requestItem' => $attestationRequest])
                    </div>

                    <div class="row g-4">
                        <div class="col-lg-6">
                            <h3 class="h6 text-muted text-uppercase mb-2" style="letter-spacing:.06em;font-size:.72rem">Rapport &amp; validations</h3>
                            <dl class="row mb-0">
                                <dt class="col-sm-5">Rapport PDF</dt>
                                <dd class="col-sm-7">
                                    @if($attestationRequest->report_path)
                                        {{ $attestationRequest->report_original_name ?? 'rapport-stage.pdf' }}
                                        @unless($isRh)
                                            <a href="{{ route('requests.report', $attestationRequest) }}" class="btn btn-sm btn-outline-primary ms-1">Télécharger</a>
                                        @endunless
                                    @else
                                        -
                                    @endif
                                </dd>
                                <dt class="col-sm-5">Validation encadrant</dt>
                                <dd class="col-sm-7">{{ $attestationRequest->supervisor_validated_at ? 'Validé le ' . $attestationRequest->supervisor_validated_at->format('d/m/Y H:i') : 'En attente' }}</dd>
                                <dt class="col-sm-5">Validation RC</dt>
                                <dd class="col-sm-7">{{ $attestationRequest->rc_validated_at ? 'Validé le ' . $attestationRequest->rc_validated_at->format('d/m/Y H:i') : 'En attente' }}</dd>
                                <dt class="col-sm-5">Note encadrant</dt>
                                <dd class="col-sm-7">{{ $attestationRequest->supervisor_grade !== null ? $attestationRequest->supervisor_grade . '/20' : '-' }}</dd>
                            </dl>
                        </div>
                        <div class="col-lg-6">
                            <h3 class="h6 text-muted text-uppercase mb-2" style="letter-spacing:.06em;font-size:.72rem">Historique</h3>
                            <div class="list-group list-group-flush">
                                @forelse($historyItems as $item)
                                    <div class="list-group-item px-0 d-flex justify-content-between gap-3">
                                        <span>{{ $item['label'] }}</span>
                                        <span class="text-muted text-nowrap small">{{ $item['date']?->format('d/m/Y H:i') }}</span>
                                    </div>
                                @empty
                                    <div class="text-muted small">Aucun historique pour le moment.</div>
                                @endforelse
                            </div>
                        </div>
                    </div>
                @else
                    <x-ui.empty-state icon="bi-award" title="Aucune attestation">
                        Aucune demande d'attestation n'a encore été envoyée pour ce stagiaire.
                    </x-ui.empty-state>
                @endif
            </div>
        </div>
    </div>
</div>

@if($canViewInternTasks)
    <div class="card card-soft mt-4 fade-in">
        <div class="card-body">
            <h2 class="h5 mb-3 section-title"><span class="module-icon"><i class="bi bi-list-check"></i></span> Tâches du stagiaire</h2>
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Titre</th>
                            <th>Date limite</th>
                            <th>Statut</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($tasks as $task)
                            <tr>
                                <td class="fw-semibold">{{ $task->title }}</td>
                                <td>{{ $task->due_date?->format('d/m/Y') ?? '-' }}</td>
                                <td>@statusBadge($task->status)</td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="text-muted">Aucune tâche liée au stagiaire.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endif
@endsection
