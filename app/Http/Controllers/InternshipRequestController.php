<?php

namespace App\Http\Controllers;

use App\Models\Absence;
use App\Models\InternshipRequest;
use App\Models\Message;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class InternshipRequestController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();

        $type = $request->query('type');
        $statusFilter = $request->query('status'); // 'pending' | 'done'
        $search = trim((string) $request->query('search', ''));

        $requests = InternshipRequest::query()
            ->with(['intern.user', 'intern.internships', 'processedBy', 'supervisorValidator', 'rcValidator', 'rhProcessor'])
            ->when($user->hasRole('Stagiaire') && $user->intern !== null, fn ($query) => $query->where('intern_id', $user->intern->id))
            ->when($user->hasRole('Stagiaire') && $user->intern === null, fn ($query) => $query->whereRaw('1 = 0'))
            ->when($user->hasRole('Encadrant'), function ($query) use ($user) {
                $query->whereHas('intern.internships', fn ($internshipQuery) => $internshipQuery->where('supervisor_id', $user->id));
            })
            ->when(in_array($type, ['attestation', 'absence', 'prolongation', 'retard_attestation', 'autre'], true),
                fn ($query) => $query->where('type', $type))
            ->when($statusFilter === 'pending', fn ($query) => $query->where('status', 'en_attente'))
            ->when($statusFilter === 'done', fn ($query) => $query->where('status', '!=', 'en_attente'))
            ->when($search !== '', fn ($query) => $query->where(function ($sub) use ($search) {
                $sub->where('message', 'like', "%{$search}%")
                    ->orWhereHas('intern.user', fn ($u) => $u->where('full_name', 'like', "%{$search}%"))
                    ->orWhereHas('intern', fn ($i) => $i->where('cin', 'like', "%{$search}%"));
            }))
            ->orderByRaw("CASE WHEN status = 'en_attente' THEN 0 ELSE 1 END")
            ->latest()
            ->paginate(12)
            ->withQueryString();

        return view('requests.index', compact('requests', 'type', 'statusFilter', 'search'));
    }

    public function create(Request $request): View
    {
        $user = $request->user();

        if (! $user->hasRole('Stagiaire') || $user->intern === null) {
            abort(403, 'Seuls les stagiaires liés à une fiche peuvent créer une demande.');
        }

        $attestationRequest = InternshipRequest::query()
            ->where('intern_id', $user->intern->id)
            ->where('type', 'attestation')
            ->latest()
            ->first();

        $hasAttestationRequest = $attestationRequest !== null;
        $canRequestDelayedAttestation = $attestationRequest !== null
            && ! in_array($attestationRequest->workflow_status, [
                'attestation_generee',
                'attestation_prete',
                'attestation_imprimee',
                'attestation_recuperee',
                'attestation_archivee',
            ], true);

        return view('requests.create', compact('hasAttestationRequest', 'canRequestDelayedAttestation'));
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();

        if (! $user->hasRole('Stagiaire') || $user->intern === null) {
            abort(403, 'Action non autorisée.');
        }

        $validated = $request->validate([
            'type' => ['required', Rule::in(['prolongation', 'attestation', 'retard_attestation', 'absence', 'autre'])],
            'motif_absence' => ['required_if:type,absence', 'nullable', 'string', 'max:255'],
            'message' => ['required', 'string'],
            'rapport_stage' => ['required_if:type,attestation', 'nullable', 'file', 'max:10240', 'mimes:pdf'],
        ]);

        $attestationRequest = InternshipRequest::query()
            ->where('intern_id', $user->intern->id)
            ->where('type', 'attestation')
            ->latest()
            ->first();

        $hasAttestationRequest = $attestationRequest !== null;
        $canRequestDelayedAttestation = $attestationRequest !== null
            && ! in_array($attestationRequest->workflow_status, [
                'attestation_generee',
                'attestation_prete',
                'attestation_imprimee',
                'attestation_recuperee',
                'attestation_archivee',
            ], true);

        if ($validated['type'] === 'attestation' && $hasAttestationRequest) {
            return back()
                ->withInput()
                ->with('error', 'Vous avez déjà envoyé une demande d attestation.');
        }

        if ($validated['type'] === 'retard_attestation' && ! $canRequestDelayedAttestation) {
            return back()
                ->withInput()
                ->with('error', 'La demande de retard d attestation est disponible seulement si votre attestation a déjà été demandée et n est pas encore générée.');
        }

        $reportPath = null;
        $reportOriginalName = null;

        if ($request->hasFile('rapport_stage')) {
            $file = $request->file('rapport_stage');
            $reportPath = $file->store('internship-reports', 'local');
            $reportOriginalName = $file->getClientOriginalName();
        }

        InternshipRequest::query()->create([
            'intern_id' => $user->intern->id,
            'type' => $validated['type'],
            'motif_absence' => $validated['type'] === 'absence' ? $validated['motif_absence'] : null,
            'message' => $validated['message'],
            'report_path' => $reportPath,
            'report_original_name' => $reportOriginalName,
            'status' => 'en_attente',
            'workflow_status' => $validated['type'] === 'attestation' ? 'attente_validation_encadrant' : null,
        ]);

        return redirect()->route('requests.index')->with('success', 'Demande envoyée.');
    }

    public function process(Request $request, InternshipRequest $requestItem): RedirectResponse
    {
        $user = $request->user();

        if (! $user->hasRole('Administrateur', 'Responsable de competence')) {
            abort(403, 'Action réservée aux responsables et administrateurs.');
        }

        if ($requestItem->type === 'attestation') {
            return back()->with('error', 'La demande d attestation suit le circuit encadrant, RC puis RH.');
        }

        $validated = $request->validate([
            'status' => ['required', Rule::in(['acceptee', 'refusee'])],
        ]);

        DB::transaction(function () use ($requestItem, $validated, $user): void {
            $requestItem->update([
                'status' => $validated['status'],
                'processed_by' => $user->id,
            ]);

            if (
                $validated['status'] === 'acceptee'
                && $requestItem->type === 'absence'
                && $requestItem->absence_generated_at === null
            ) {
                Absence::query()->create([
                    'intern_id' => $requestItem->intern_id,
                    'date_absence' => today(),
                    'reason' => $requestItem->motif_absence ?? $requestItem->message,
                    'justified' => true,
                    'recorded_by' => $user->id,
                ]);

                $requestItem->update(['absence_generated_at' => now()]);
            }
        });

        return back()->with('success', 'Demande traitée.');
    }

    public function supervisorValidate(Request $request, InternshipRequest $requestItem): RedirectResponse
    {
        $user = $request->user();

        if ($requestItem->type !== 'attestation') {
            abort(403, 'Cette action concerne seulement les attestations.');
        }

        $isSupervisor = $requestItem->intern
            ?->internships()
            ->where('supervisor_id', $user->id)
            ->exists();

        if (! $user->hasRole('Administrateur') && (! $user->hasRole('Encadrant') || ! $isSupervisor)) {
            abort(403, 'Validation réservée à l encadrant du stagiaire.');
        }

        $validated = $request->validate([
            'supervisor_grade' => ['required', 'integer', 'min:0', 'max:20'],
        ]);

        $requestItem->update([
            'workflow_status' => 'attente_validation_rc',
            'supervisor_validated_by' => $user->id,
            'supervisor_validated_at' => now(),
            'supervisor_grade' => $validated['supervisor_grade'],
        ]);

        return back()->with('success', 'Rapport validé. La demande est transmise au responsable de compétence.');
    }

    public function rcValidate(Request $request, InternshipRequest $requestItem): RedirectResponse
    {
        $user = $request->user();

        if ($requestItem->type !== 'attestation') {
            abort(403, 'Cette action concerne seulement les attestations.');
        }

        $isResponsible = $requestItem->intern
            ?->internships()
            ->where('responsible_id', $user->id)
            ->exists();

        if (! $user->hasRole('Administrateur') && (! $user->hasRole('Responsable de competence') || ! $isResponsible)) {
            abort(403, 'Validation réservée au responsable de compétence du stagiaire.');
        }

        if ($requestItem->supervisor_validated_at === null) {
            return back()->with('error', 'L encadrant doit valider le stage avant la validation du rapport.');
        }

        $requestItem->update([
            'workflow_status' => 'transmise_rh',
            'rc_validated_by' => $user->id,
            'rc_validated_at' => now(),
            'sent_to_rh_at' => now(),
        ]);

        return back()->with('success', 'Rapport validé et transmis au RH.');
    }

    public function rhComplete(Request $request, InternshipRequest $requestItem): RedirectResponse
    {
        $user = $request->user();

        if (! $user->hasRole('Administrateur', 'Responsable RH')) {
            abort(403, 'Action réservée au RH.');
        }

        if ($requestItem->type !== 'attestation') {
            abort(403, 'Cette action concerne seulement les attestations.');
        }

        if (! $user->hasRole('Administrateur') && $requestItem->sent_to_rh_at === null) {
            abort(403, 'Cette attestation n est pas encore transmise au RH.');
        }

        $validated = $requestItem->supervisor_grade === null
            ? $request->validate([
                'supervisor_grade' => ['required', 'integer', 'min:0', 'max:20'],
            ])
            : [];

        DB::transaction(function () use ($requestItem, $user, $validated): void {
            $payload = [
                'status' => 'acceptee',
                'workflow_status' => 'attestation_generee',
                'processed_by' => $user->id,
                'rh_processed_by' => $user->id,
                'rh_processed_at' => now(),
                'sent_to_rh_at' => $requestItem->sent_to_rh_at ?? now(),
            ];

            if ($requestItem->supervisor_grade === null) {
                $payload['supervisor_grade'] = $validated['supervisor_grade'];
                $payload['supervisor_validated_by'] = $requestItem->supervisor_validated_by ?? $user->id;
                $payload['supervisor_validated_at'] = $requestItem->supervisor_validated_at ?? now();
            }

            $requestItem->update($payload);

            if ($requestItem->intern?->user_id !== null) {
                Message::query()->create([
                    'sender_id' => $user->id,
                    'receiver_id' => $requestItem->intern->user_id,
                    'subject' => 'Attestation de stage prête',
                    'body' => 'Votre attestation de stage est prête. Merci de venir la récupérer à l entreprise auprès du service RH.',
                    'is_read' => false,
                ]);

                Message::query()->create([
                    'sender_id' => $user->id,
                    'receiver_id' => $requestItem->intern->user_id,
                    'subject' => 'Veuillez récupérer votre attestation',
                    'body' => 'Veuillez vous présenter à l entreprise pour récupérer votre attestation de stage signée.',
                    'is_read' => false,
                ]);
            }
        });

        return redirect()
            ->route('attestations.show', $requestItem->intern)
            ->with('success', 'Attestation generee. Vous pouvez maintenant l imprimer.');
    }

    public function rhMarkPrinted(Request $request, InternshipRequest $requestItem): RedirectResponse
    {
        $user = $request->user();

        if (! $user->hasRole('Administrateur', 'Responsable RH')) {
            abort(403, 'Action réservée au RH.');
        }

        if (! in_array($requestItem->workflow_status, ['attestation_generee', 'attestation_prete', 'attestation_imprimee', 'attestation_recuperee'], true)) {
            return back()->with('error', 'L attestation doit être générée avant impression.');
        }

        $payload = [
            'attestation_printed_at' => $requestItem->attestation_printed_at ?? now(),
        ];

        if ($requestItem->workflow_status !== 'attestation_recuperee') {
            $payload['workflow_status'] = 'attestation_imprimee';
        }

        $requestItem->update($payload);

        return back()->with('success', 'Attestation marquée comme imprimée.');
    }

    public function rhMarkRecovered(Request $request, InternshipRequest $requestItem): RedirectResponse
    {
        $user = $request->user();

        if (! $user->hasRole('Administrateur', 'Responsable RH')) {
            abort(403, 'Action réservée au RH.');
        }

        if (! in_array($requestItem->workflow_status, ['attestation_generee', 'attestation_prete', 'attestation_imprimee'], true)) {
            return back()->with('error', 'L attestation doit être générée avant récupération.');
        }

        $requestItem->update([
            'workflow_status' => 'attestation_recuperee',
            'attestation_recovered_at' => $requestItem->attestation_recovered_at ?? now(),
        ]);

        return back()->with('success', 'Attestation marquée comme récupérée.');
    }

    public function rhArchive(Request $request, InternshipRequest $requestItem): RedirectResponse
    {
        $user = $request->user();

        if (! $user->hasRole('Administrateur', 'Responsable RH')) {
            abort(403, 'Action réservée au RH.');
        }

        if ($requestItem->type !== 'attestation' || ! in_array($requestItem->workflow_status, ['attestation_generee', 'attestation_prete', 'attestation_imprimee', 'attestation_recuperee'], true)) {
            return back()->with('error', 'L attestation doit être générée avant archivage.');
        }

        DB::transaction(function () use ($requestItem, $user): void {
            $requestItem->update([
                'workflow_status' => 'attestation_archivee',
                'attestation_archived_at' => $requestItem->attestation_archived_at ?? now(),
            ]);

            if ($requestItem->intern?->user_id !== null) {
                Message::query()->create([
                    'sender_id' => $user->id,
                    'receiver_id' => $requestItem->intern->user_id,
                    'subject' => 'Dossier archivé',
                    'body' => 'Votre dossier de stage a été archivé par le service RH.',
                    'is_read' => false,
                ]);
            }
        });

        return back()->with('success', 'Attestation archivée.');
    }

    public function downloadReport(Request $request, InternshipRequest $requestItem): StreamedResponse
    {
        $user = $request->user();

        $canDownload = $user->hasRole('Administrateur', 'Responsable de competence')
            || ($user->hasRole('Encadrant') && $requestItem->intern?->internships()->where('supervisor_id', $user->id)->exists())
            || ($user->hasRole('Stagiaire') && $user->intern !== null && $requestItem->intern_id === $user->intern->id);

        if (! $canDownload || $requestItem->report_path === null) {
            abort(403, 'Accès refusé au rapport.');
        }

        if (! Storage::disk('local')->exists($requestItem->report_path)) {
            abort(404, 'Rapport introuvable.');
        }

        return Storage::disk('local')->download($requestItem->report_path, $requestItem->report_original_name ?? 'rapport-stage.pdf');
    }

    public function destroy(Request $request, InternshipRequest $requestItem): RedirectResponse
    {
        $user = $request->user();

        $canDelete = $user->hasRole('Administrateur', 'Responsable de competence')
            || ($user->hasRole('Stagiaire')
                && $user->intern !== null
                && $requestItem->intern_id === $user->intern->id
                && $requestItem->status === 'en_attente');

        if (! $canDelete) {
            abort(403, 'Suppression non autorisée.');
        }

        $requestItem->delete();

        return back()->with('success', 'Demande supprimée.');
    }
}
