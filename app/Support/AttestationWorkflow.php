<?php

namespace App\Support;

use App\Models\InternshipRequest;

/**
 * Single source of truth for the attestation workflow.
 *
 * Flow: stagiaire envoie le rapport → encadrant valide + note → responsable
 * compétence valide → RH génère / imprime / remet / archive.
 *
 * Centralises the workflow_status labels (previously duplicated and accent-less
 * across several Blade files), the timeline steps, and the "who must act next"
 * logic used by the dashboard and the requests queue.
 */
class AttestationWorkflow
{
    /** Readable French label for a workflow_status (with accents). */
    public static function label(?string $status): string
    {
        return match ($status) {
            'attente_validation_encadrant' => 'En attente — validation encadrant',
            'attente_validation_rc'        => 'En attente — validation RC',
            'transmise_rh'                 => 'Transmise au RH',
            'attestation_generee',
            'attestation_prete'            => 'Attestation générée',
            'attestation_imprimee'         => 'Attestation imprimée',
            'attestation_recuperee'        => 'Attestation récupérée',
            'attestation_archivee'         => 'Dossier archivé',
            default                        => $status ? ucfirst(str_replace('_', ' ', $status)) : '—',
        };
    }

    /** Compact label for chips / dense tables. */
    public static function shortLabel(?string $status): string
    {
        return match ($status) {
            'attente_validation_encadrant' => 'Validation encadrant',
            'attente_validation_rc'        => 'Validation RC',
            'transmise_rh'                 => 'À générer (RH)',
            'attestation_generee',
            'attestation_prete'            => 'Générée',
            'attestation_imprimee'         => 'Imprimée',
            'attestation_recuperee'        => 'Récupérée',
            'attestation_archivee'         => 'Archivée',
            default                        => self::label($status),
        };
    }

    /** Role name expected to act next, or null when no action is pending. */
    public static function pendingRole(InternshipRequest $request): ?string
    {
        return match ($request->workflow_status) {
            'attente_validation_encadrant' => 'Encadrant',
            'attente_validation_rc'        => 'Responsable de competence',
            'transmise_rh'                 => 'Responsable RH',
            default                        => null,
        };
    }

    /** workflow_status values considered "generated" (printable) onward. */
    public static function printableStatuses(): array
    {
        return ['attestation_generee', 'attestation_prete', 'attestation_imprimee', 'attestation_recuperee'];
    }

    /**
     * Ordered timeline steps for a request.
     *
     * @return array<int, array{key:string,label:string,done:bool,current:bool}>
     */
    public static function steps(InternshipRequest $request): array
    {
        $ws = $request->workflow_status;

        $generated = in_array($ws, ['attestation_generee', 'attestation_prete', 'attestation_imprimee', 'attestation_recuperee', 'attestation_archivee'], true);
        $printed   = in_array($ws, ['attestation_imprimee', 'attestation_recuperee', 'attestation_archivee'], true);
        $recovered = in_array($ws, ['attestation_recuperee', 'attestation_archivee'], true);
        $archived  = $ws === 'attestation_archivee';

        $steps = [
            ['key' => 'report',    'label' => 'Rapport envoyé',  'done' => $request->report_path !== null],
            ['key' => 'encadrant', 'label' => 'Validé encadrant', 'done' => $request->supervisor_validated_at !== null],
            ['key' => 'rc',        'label' => 'Validé RC',        'done' => $request->rc_validated_at !== null],
            ['key' => 'rh',        'label' => 'Transmis RH',      'done' => $request->sent_to_rh_at !== null],
            ['key' => 'generee',   'label' => 'Générée',          'done' => $generated],
            ['key' => 'imprimee',  'label' => 'Imprimée',         'done' => $printed],
            ['key' => 'recuperee', 'label' => 'Récupérée',        'done' => $recovered],
            ['key' => 'archivee',  'label' => 'Archivée',         'done' => $archived],
        ];

        // The first not-yet-done step is the "current" one.
        $currentSet = false;
        foreach ($steps as &$step) {
            $step['current'] = false;
            if (! $currentSet && ! $step['done']) {
                $step['current'] = true;
                $currentSet = true;
            }
        }

        return $steps;
    }
}
