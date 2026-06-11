<?php

namespace Tests\Feature;

use App\Models\Intern;
use App\Models\Internship;
use App\Models\InternshipRequest;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class RequestFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    private function userWithRole(string $roleName): User
    {
        $role = Role::query()->where('name', $roleName)->firstOrFail();

        return User::query()->create([
            'full_name' => $roleName . ' Test',
            'email' => Str::random(10) . '@test.local',
            'password_hash' => Hash::make('password123'),
            'role_id' => $role->id,
            'is_active' => true,
        ]);
    }

    private function intern(User $user): Intern
    {
        return Intern::query()->create([
            'user_id' => $user->id,
            'cin' => 'CIN' . $user->id,
            'school' => 'École Test',
            'specialty' => 'Informatique',
            'start_date' => now()->subMonths(2),
            'end_date' => now()->addMonth(),
        ]);
    }

    public function test_intern_can_create_a_request(): void
    {
        $studentUser = $this->userWithRole('Stagiaire');
        $this->intern($studentUser);
        $studentUser->refresh();

        $this->actingAs($studentUser)
            ->post(route('requests.store'), [
                'type' => 'autre',
                'message' => 'Je souhaite des informations sur ma convention.',
            ])
            ->assertRedirect(route('requests.index'));

        $this->assertDatabaseHas('requests', [
            'intern_id' => $studentUser->intern->id,
            'type' => 'autre',
            'status' => 'en_attente',
        ]);
    }

    public function test_attestation_moves_through_the_main_workflow_steps(): void
    {
        $encadrant = $this->userWithRole('Encadrant');
        $rc = $this->userWithRole('Responsable de competence');
        $rh = $this->userWithRole('Responsable RH');
        $studentUser = $this->userWithRole('Stagiaire');
        $intern = $this->intern($studentUser);

        $internship = Internship::query()->create([
            'title' => 'Plateforme de gestion',
            'department' => 'IT',
            'status' => 'en_cours',
            'supervisor_id' => $encadrant->id,
            'responsible_id' => $rc->id,
            'intern_id' => $intern->id,
            'start_date' => now()->subMonths(2),
            'end_date' => now()->addMonth(),
        ]);
        $intern->internships()->attach($internship->id);

        $request = InternshipRequest::query()->create([
            'intern_id' => $intern->id,
            'type' => 'attestation',
            'message' => 'Demande attestation',
            'report_path' => 'internship-reports/report.pdf',
            'report_original_name' => 'report.pdf',
            'status' => 'en_attente',
            'workflow_status' => 'attente_validation_encadrant',
        ]);

        // 1. Encadrant grades + validates.
        $this->actingAs($encadrant)
            ->patch(route('requests.supervisor-validate', $request), ['supervisor_grade' => 15])
            ->assertRedirect();
        $this->assertSame('attente_validation_rc', $request->fresh()->workflow_status);
        $this->assertSame(15, (int) $request->fresh()->supervisor_grade);

        // 2. Responsable de compétence validates.
        $this->actingAs($rc)
            ->patch(route('requests.rc-validate', $request))
            ->assertRedirect();
        $this->assertSame('transmise_rh', $request->fresh()->workflow_status);
        $this->assertNotNull($request->fresh()->sent_to_rh_at);

        // 3. RH generates the attestation.
        $this->actingAs($rh)
            ->patch(route('requests.rh-complete', $request))
            ->assertRedirect();
        $this->assertSame('attestation_generee', $request->fresh()->workflow_status);
        $this->assertSame('acceptee', $request->fresh()->status);
    }
}
