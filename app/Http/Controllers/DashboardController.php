<?php

namespace App\Http\Controllers;

use App\Models\DailyLog;
use App\Models\Intern;
use App\Models\Internship;
use App\Models\InternshipRequest;
use App\Models\Message;
use App\Models\Task;
use App\Models\User;
use App\Support\AttestationWorkflow;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\View\View;

class DashboardController extends Controller
{
    private const RECENT_LIMIT = 3;

    public function index(): View
    {
        $user = auth()->user();
        $isAdmin = $user->hasRole('Administrateur');
        $isHr = $user->hasRole('Responsable RH');
        $isManager = $user->hasRole('Responsable de competence', 'Encadrant');
        $canViewTasks = ! $user->hasRole('Responsable de competence', 'Responsable RH');
        $isIntern = $user->hasRole('Stagiaire');

        $managedInternIds = collect();

        if ($isManager) {
            $managedInternIds = Intern::query()
                ->whereHas('internships', function ($query) use ($user) {
                    $this->scopeManagedInternships($query, $user);
                })
                ->pluck('id')
                ->unique()
                ->values();
        }

        $stats = [];

        if ($isAdmin || $isHr) {
            $stats = [
                'interns' => Intern::query()->count(),
                'active_internships' => Internship::query()->where('status', 'en_cours')->count(),
                'completed_internships' => Internship::query()->where('status', 'termine')->count(),
                'attestations_to_process' => InternshipRequest::query()
                    ->where('type', 'attestation')
                    ->where('workflow_status', 'transmise_rh')
                    ->count(),
                'generated_attestations' => InternshipRequest::query()
                    ->where('type', 'attestation')
                    ->whereIn('workflow_status', ['attestation_generee', 'attestation_prete', 'attestation_imprimee', 'attestation_recuperee', 'attestation_archivee'])
                    ->count(),
                'pending_requests' => InternshipRequest::query()
                    ->where('status', 'en_attente')
                    ->when($isHr, fn ($query) => $query->where('type', 'attestation')->whereNotNull('sent_to_rh_at'))
                    ->count(),
            ];

            if ($isAdmin) {
                $stats['users'] = User::query()->count();
            }
        } elseif ($isManager) {
            $stats = [
                'interns' => $managedInternIds->count(),
                'active_internships' => Internship::query()
                    ->where('status', 'en_cours')
                    ->whereHas('interns', fn ($query) => $query->whereIn('interns.id', $managedInternIds))
                    ->count(),
                'pending_requests' => InternshipRequest::query()
                    ->whereIn('intern_id', $managedInternIds)
                    ->where('status', 'en_attente')
                    ->count(),
            ];
        } elseif ($isIntern && $user->intern !== null) {
            $stats = [
                'pending_requests' => InternshipRequest::query()
                    ->where('intern_id', $user->intern->id)
                    ->where('status', 'en_attente')
                    ->count(),
                'my_open_tasks' => Task::query()
                    ->where('assigned_to', $user->id)
                    ->whereIn('status', ['a_faire', 'en_cours'])
                    ->count(),
                'unread_messages' => Message::query()
                    ->where('receiver_id', $user->id)
                    ->where('is_read', false)
                    ->count(),
            ];
        }

        $statCards = [];

        if ($isAdmin) {
            $statCards = [
                ['label' => 'Utilisateurs', 'value' => $stats['users'] ?? 0],
                ['label' => 'Stagiaires', 'value' => $stats['interns'] ?? 0],
                ['label' => 'Stages en cours', 'value' => $stats['active_internships'] ?? 0],
                ['label' => 'Demandes en attente', 'value' => $stats['pending_requests'] ?? 0],
            ];
        } elseif ($isHr) {
            $statCards = [
                ['label' => 'Total stagiaires', 'value' => $stats['interns'] ?? 0],
                ['label' => 'Stages terminés', 'value' => $stats['completed_internships'] ?? 0],
                ['label' => 'Attestations à traiter', 'value' => $stats['attestations_to_process'] ?? 0],
                ['label' => 'Attestations générées', 'value' => $stats['generated_attestations'] ?? 0],
                ['label' => 'Demandes en attente', 'value' => $stats['pending_requests'] ?? 0],
            ];
        } elseif ($isManager) {
            $statCards = [
                ['label' => 'Stagiaires', 'value' => $stats['interns'] ?? 0],
                ['label' => 'Stages en cours', 'value' => $stats['active_internships'] ?? 0],
                ['label' => 'Demandes en attente', 'value' => $stats['pending_requests'] ?? 0],
            ];
        } elseif ($isIntern) {
            $statCards = [
                ['label' => 'Mes demandes en attente', 'value' => $stats['pending_requests'] ?? 0],
                ['label' => 'Mes tâches ouvertes', 'value' => $stats['my_open_tasks'] ?? 0],
                ['label' => 'Messages non lus', 'value' => $stats['unread_messages'] ?? 0],
            ];
        }

        $latestTasks = collect();

        if ($canViewTasks) {
            $latestTasks = Task::query()
                ->with(['assignedBy', 'assignedTo'])
                ->when($isIntern, fn ($query) => $query->where('assigned_to', $user->id))
                ->when($isManager, function ($query) use ($managedInternIds, $user) {
                    if ($managedInternIds->isEmpty()) {
                        $query->whereRaw('1 = 0');
                    } else {
                        $query
                            ->whereHas('internship.interns', fn ($subQuery) => $subQuery->whereIn('interns.id', $managedInternIds))
                            ->whereHas('internship', function ($subQuery) use ($user) {
                                $this->scopeManagedInternships($subQuery, $user);
                            });
                    }
                })
                ->latest()
                ->take(self::RECENT_LIMIT)
                ->get();
        }

        $latestRequests = InternshipRequest::query()
            ->with(['intern.user', 'processedBy'])
            ->when($isIntern && $user->intern !== null, fn ($query) => $query->where('intern_id', $user->intern->id))
            ->when($isIntern && $user->intern === null, fn ($query) => $query->whereRaw('1 = 0'))
            ->when($isHr, fn ($query) => $query->where('type', 'attestation')->whereNotNull('sent_to_rh_at'))
            ->when($isManager, function ($query) use ($managedInternIds) {
                if ($managedInternIds->isEmpty()) {
                    $query->whereRaw('1 = 0');
                } else {
                    $query->whereIn('intern_id', $managedInternIds);
                }
            })
            ->latest()
            ->take(self::RECENT_LIMIT)
            ->get();

        $allEvaluatedInterns = collect();
        $alertEvaluatedInterns = collect();

        if ($isAdmin || $isHr) {
            $allEvaluatedInterns = Intern::query()
                ->with(['user', 'absences', 'internships.tasks', 'weeklyReports'])
                ->where('is_archived', false)
                ->latest()
                ->get();
            $alertEvaluatedInterns = $allEvaluatedInterns;
        } elseif ($isManager) {
            $allEvaluatedInterns = Intern::query()
                ->with(['user', 'absences', 'internships.tasks', 'weeklyReports'])
                ->where('is_archived', false)
                ->when($managedInternIds->isEmpty(), fn ($query) => $query->whereRaw('1 = 0'))
                ->when($managedInternIds->isNotEmpty(), fn ($query) => $query->whereIn('id', $managedInternIds))
                ->latest()
                ->get();

            $alertEvaluatedInterns = Intern::query()
                ->with([
                    'user',
                    'absences',
                    'internships' => function ($query) use ($user) {
                        $this->scopeManagedInternships($query, $user);
                    },
                    'internships.tasks',
                ])
                ->where('is_archived', false)
                ->when($managedInternIds->isEmpty(), fn ($query) => $query->whereRaw('1 = 0'))
                ->when($managedInternIds->isNotEmpty(), fn ($query) => $query->whereIn('id', $managedInternIds))
                ->latest()
                ->get();
        } elseif ($isIntern && $user->intern !== null) {
            $allEvaluatedInterns = collect([
                $user->intern->load(['user', 'absences', 'internships.tasks', 'weeklyReports']),
            ]);
            $alertEvaluatedInterns = $allEvaluatedInterns;
        }

        $sortedInternsByScore = $allEvaluatedInterns
            ->sortByDesc(fn (Intern $intern): int => $intern->performanceScore()['score'] ?? -1)
            ->values();

        // Dashboard blocks stay synthetic: show the top few, link out for the rest.
        $evaluatedInterns = $sortedInternsByScore->take(5)->values();
        $hasMoreInterns = $sortedInternsByScore->count() > $evaluatedInterns->count();
        $internsListUrl = $user->hasRole('Encadrant')
            ? route('supervisor.interns')
            : ($user->hasRole('Administrateur', 'Responsable RH', 'Responsable de competence') ? route('interns.index') : null);

        $smartAlerts = $alertEvaluatedInterns
            ->flatMap(fn (Intern $intern) => collect($intern->smartAlerts())
                ->when(! $canViewTasks, fn ($alerts) => $alerts->reject(fn (array $alert): bool => $alert['type'] === 'task'))
                ->map(fn (array $alert) => [
                    'intern' => $intern,
                    'alert' => $alert,
                ]))
            ->take(self::RECENT_LIMIT)
            ->values();

        // ── Action panels: "what needs attention now", per role ──────────
        $attention = $this->buildAttentionPanels($user, $managedInternIds, $isAdmin, $isHr, $isManager, $isIntern, $smartAlerts);
        $roleName = $user->role?->name ?? 'Utilisateur';

        return view('dashboard.index', compact('stats', 'statCards', 'latestTasks', 'latestRequests', 'evaluatedInterns', 'hasMoreInterns', 'internsListUrl', 'smartAlerts', 'canViewTasks', 'attention', 'roleName'));
    }

    /**
     * Build the role-specific "needs attention now" panels.
     *
     * Each panel: ['title','icon','tone','count','viewUrl','viewLabel','empty','items'].
     * Each item:  ['title','meta','badge'=>['label','class']|null,'url'].
     *
     * @return array<int, array<string, mixed>>
     */
    private function buildAttentionPanels(
        User $user,
        $managedInternIds,
        bool $isAdmin,
        bool $isHr,
        bool $isManager,
        bool $isIntern,
        $smartAlerts
    ): array {
        $today = today();
        $panels = [];

        // Helper: map attestation requests to panel items.
        $reqItems = fn (EloquentCollection $requests, string $url) => $requests
            ->map(fn (InternshipRequest $r) => [
                'title' => $r->intern->user?->full_name ?? $r->intern->cin ?? 'Stagiaire',
                'meta'  => AttestationWorkflow::label($r->workflow_status),
                'badge' => null,
                'url'   => $url,
            ])->all();

        // Helper: map tasks to panel items.
        $taskItems = fn (EloquentCollection $tasks) => $tasks
            ->map(fn (Task $t) => [
                'title' => $t->title,
                'meta'  => 'Échéance ' . ($t->due_date?->format('d/m/Y') ?? '—')
                    . ($t->assignedTo ? ' · ' . $t->assignedTo->full_name : ''),
                'badge' => ['label' => 'En retard', 'class' => 'text-bg-danger'],
                'url'   => route('tasks.index'),
            ])->all();

        $attestation = fn () => InternshipRequest::query()->with('intern.user')->where('type', 'attestation');

        if ($isIntern) {
            // A stagiaire only ever sees their own panels — never the team-wide queues.
            if ($user->intern === null) {
                return $panels;
            }

            $internId = $user->intern->id;

            $overdue = Task::query()->with('assignedTo')
                ->where('assigned_to', $user->id)
                ->where('status', '!=', 'termine')
                ->whereNotNull('due_date')->whereDate('due_date', '<', $today)
                ->orderBy('due_date')->limit(5)->get();

            $panels[] = [
                'title' => 'Tâches en retard',
                'icon' => 'bi-exclamation-circle',
                'tone' => 'danger',
                'count' => $overdue->count(),
                'viewUrl' => route('tasks.index'),
                'viewLabel' => 'Voir mes tâches',
                'empty' => 'Aucune tâche en retard. Continuez comme ça !',
                'items' => $taskItems($overdue),
            ];

            $myRequests = InternshipRequest::query()
                ->where('intern_id', $internId)
                ->where(function (Builder $q): void {
                    $q->where('status', 'en_attente')
                        ->orWhere(fn (Builder $s) => $s->where('type', 'attestation')
                            ->whereNotNull('workflow_status')
                            ->where('workflow_status', '!=', 'attestation_archivee'));
                })
                ->latest()->limit(5)->get();

            $panels[] = [
                'title' => 'Suivi de mes demandes',
                'icon' => 'bi-file-earmark-text',
                'tone' => 'default',
                'count' => $myRequests->count(),
                'viewUrl' => route('requests.index'),
                'viewLabel' => 'Voir mes demandes',
                'empty' => 'Aucune demande en cours.',
                'items' => $myRequests->map(fn (InternshipRequest $r) => [
                    'title' => $r->type === 'attestation' ? 'Attestation de stage'
                        : ($r->type === 'retard_attestation' ? 'Retard attestation' : ucfirst($r->type)),
                    'meta'  => $r->workflow_status
                        ? AttestationWorkflow::label($r->workflow_status)
                        : \App\Support\StatusDesign::badge($r->status)['label'],
                    'badge' => null,
                    'url'   => route('requests.index'),
                ])->all(),
            ];

            // Journal reminder (weekdays only).
            $loggedToday = DailyLog::query()->where('intern_id', $internId)->whereDate('log_date', $today)->exists();
            if (! $loggedToday && $today->isWeekday()) {
                $panels[] = [
                    'title' => 'Journal du jour',
                    'icon' => 'bi-journal-plus',
                    'tone' => 'accent',
                    'count' => null,
                    'viewUrl' => route('daily-log.index'),
                    'viewLabel' => "Remplir mon journal d'aujourd'hui",
                    'empty' => "Vous n'avez pas encore renseigné votre présence et vos activités du jour.",
                    'items' => [],
                ];
            }

            return $panels;
        }

        if ($isHr) {
            $toGenerate = $attestation()->where('workflow_status', 'transmise_rh')->latest()->limit(6)->get();
            $panels[] = [
                'title' => 'Attestations à générer',
                'icon' => 'bi-award',
                'tone' => 'danger',
                'count' => $toGenerate->count(),
                'viewUrl' => route('rh.attestations.index'),
                'viewLabel' => 'Ouvrir la file RH',
                'empty' => 'Aucune attestation en attente de génération.',
                'items' => $reqItems($toGenerate, route('rh.attestations.index')),
            ];

            $toHandOver = $attestation()
                ->whereIn('workflow_status', ['attestation_generee', 'attestation_prete', 'attestation_imprimee'])
                ->latest()->limit(6)->get();
            $panels[] = [
                'title' => 'À imprimer / remettre',
                'icon' => 'bi-printer',
                'tone' => 'default',
                'count' => $toHandOver->count(),
                'viewUrl' => route('rh.attestations.index'),
                'viewLabel' => 'Ouvrir la file RH',
                'empty' => 'Rien à imprimer ou remettre pour le moment.',
                'items' => $reqItems($toHandOver, route('rh.attestations.index')),
            ];

            return $panels;
        }

        // Encadrant / Responsable de compétence / Administrateur.
        $scopeIds = $isManager ? $managedInternIds : null;

        if ($user->hasRole('Encadrant')) {
            $toValidate = $attestation()->where('workflow_status', 'attente_validation_encadrant')
                ->whereHas('intern.internships', fn ($q) => $q->where('supervisor_id', $user->id))
                ->latest()->limit(6)->get();
            $panels[] = [
                'title' => 'Attestations à valider',
                'icon' => 'bi-patch-check',
                'tone' => 'danger',
                'count' => $toValidate->count(),
                'viewUrl' => route('requests.index'),
                'viewLabel' => 'Ouvrir les demandes',
                'empty' => 'Aucun rapport en attente de votre validation.',
                'items' => $reqItems($toValidate, route('requests.index')),
            ];
        }

        if ($user->hasRole('Responsable de competence')) {
            $toValidate = $attestation()->where('workflow_status', 'attente_validation_rc')
                ->whereHas('intern.internships', fn ($q) => $q->where('responsible_id', $user->id))
                ->latest()->limit(6)->get();
            $panels[] = [
                'title' => 'Rapports à valider (RC)',
                'icon' => 'bi-patch-check',
                'tone' => 'danger',
                'count' => $toValidate->count(),
                'viewUrl' => route('requests.index'),
                'viewLabel' => 'Ouvrir les demandes',
                'empty' => 'Aucun rapport en attente de votre validation.',
                'items' => $reqItems($toValidate, route('requests.index')),
            ];
        }

        if ($isAdmin) {
            $toProcess = $attestation()->where('workflow_status', 'transmise_rh')->latest()->limit(6)->get();
            $panels[] = [
                'title' => 'Attestations à traiter',
                'icon' => 'bi-award',
                'tone' => 'default',
                'count' => $toProcess->count(),
                'viewUrl' => route('rh.attestations.index'),
                'viewLabel' => 'Ouvrir la file RH',
                'empty' => 'Aucune attestation à traiter.',
                'items' => $reqItems($toProcess, route('rh.attestations.index')),
            ];
        }

        // Overdue tasks for managed/all interns.
        $overdue = Task::query()->with('assignedTo')
            ->where('status', '!=', 'termine')
            ->whereNotNull('due_date')->whereDate('due_date', '<', $today)
            ->when($isManager, function (Builder $q) use ($managedInternIds, $user): void {
                if ($managedInternIds->isEmpty()) {
                    $q->whereRaw('1 = 0');
                } else {
                    $q->whereHas('internship.interns', fn ($s) => $s->whereIn('interns.id', $managedInternIds))
                        ->whereHas('internship', fn ($s) => $this->scopeManagedInternships($s, $user));
                }
            })
            ->orderBy('due_date')->limit(6)->get();
        $panels[] = [
            'title' => 'Tâches en retard',
            'icon' => 'bi-exclamation-circle',
            'tone' => 'default',
            'count' => $overdue->count(),
            'viewUrl' => route('tasks.index'),
            'viewLabel' => 'Voir les tâches',
            'empty' => 'Aucune tâche en retard.',
            'items' => $taskItems($overdue),
        ];

        // Interns needing follow-up (reuse smart alerts).
        $followUp = collect($smartAlerts)->take(5)->map(fn ($item) => [
            'title' => $item['intern']->user?->full_name ?? 'Stagiaire',
            'meta'  => $item['alert']['message'],
            'badge' => null,
            'url'   => $isManager && $user->hasRole('Encadrant')
                ? route('supervisor.interns.show', $item['intern'])
                : route('interns.index'),
        ])->all();
        $panels[] = [
            'title' => 'Stagiaires à suivre',
            'icon' => 'bi-person-exclamation',
            'tone' => 'default',
            'count' => count($followUp),
            'viewUrl' => $user->hasRole('Encadrant') ? route('supervisor.interns') : route('interns.index'),
            'viewLabel' => 'Voir les stagiaires',
            'empty' => 'Aucun stagiaire à risque détecté.',
            'items' => $followUp,
        ];

        return $panels;
    }

    private function scopeManagedInternships($query, User $user): void
    {
        $query->where(function (Builder $subQuery) use ($user): void {
            if ($user->hasRole('Responsable de competence')) {
                $subQuery->orWhere('responsible_id', $user->id);
            }

            if ($user->hasRole('Encadrant')) {
                $subQuery->orWhere('supervisor_id', $user->id);
            }
        });
    }
}
