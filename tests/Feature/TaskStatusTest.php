<?php

namespace Tests\Feature;

use App\Models\Intern;
use App\Models\Internship;
use App\Models\Role;
use App\Models\Task;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class TaskStatusTest extends TestCase
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

    private function taskFor(User $ownerUser, User $supervisor): Task
    {
        $intern = Intern::query()->create([
            'user_id' => $ownerUser->id,
            'cin' => 'CIN' . $ownerUser->id,
            'school' => 'École Test',
            'specialty' => 'Informatique',
            'start_date' => now()->subMonth(),
            'end_date' => now()->addMonth(),
        ]);

        $internship = Internship::query()->create([
            'title' => 'Stage Test',
            'department' => 'IT',
            'status' => 'en_cours',
            'supervisor_id' => $supervisor->id,
            'intern_id' => $intern->id,
            'start_date' => now()->subMonth(),
            'end_date' => now()->addMonth(),
        ]);
        $intern->internships()->attach($internship->id);

        return Task::query()->create([
            'internship_id' => $internship->id,
            'assigned_by' => $supervisor->id,
            'assigned_to' => $ownerUser->id,
            'title' => 'Implémenter la page',
            'status' => 'a_faire',
            'due_date' => now()->addWeek(),
        ]);
    }

    public function test_owner_intern_can_update_task_status(): void
    {
        $supervisor = $this->userWithRole('Encadrant');
        $owner = $this->userWithRole('Stagiaire');
        $task = $this->taskFor($owner, $supervisor);

        $this->actingAs($owner)
            ->patch(route('tasks.status', $task), ['status' => 'en_cours'])
            ->assertOk();

        $this->assertSame('en_cours', $task->fresh()->status);
    }

    public function test_non_owner_intern_cannot_update_task_status(): void
    {
        $supervisor = $this->userWithRole('Encadrant');
        $owner = $this->userWithRole('Stagiaire');
        $task = $this->taskFor($owner, $supervisor);

        $otherIntern = $this->userWithRole('Stagiaire');

        $this->actingAs($otherIntern)
            ->patch(route('tasks.status', $task), ['status' => 'termine'])
            ->assertForbidden();

        $this->assertSame('a_faire', $task->fresh()->status);
    }
}
