@php
    // Timeline steps centralised in App\Support\AttestationWorkflow.
    $steps = \App\Support\AttestationWorkflow::steps($requestItem);
@endphp

<div class="attestation-timeline">
    @foreach($steps as $step)
        <div class="attestation-timeline-step @class(['is-done' => $step['done'], 'is-current' => $step['current']])">
            <span class="attestation-timeline-dot"></span>
            <span class="attestation-timeline-label">{{ $step['label'] }}</span>
        </div>
    @endforeach
</div>
