<?php

namespace Tests\Feature;

use App\Models\Intern;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class DashboardTest extends TestCase
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

    public function test_dashboard_loads_for_each_role(): void
    {
        $roles = [
            'Administrateur',
            'Responsable RH',
            'Responsable de competence',
            'Encadrant',
            'Stagiaire',
        ];

        foreach ($roles as $roleName) {
            $user = $this->userWithRole($roleName);

            if ($roleName === 'Stagiaire') {
                Intern::query()->create([
                    'user_id' => $user->id,
                    'cin' => 'CIN' . $user->id,
                    'school' => 'École Test',
                    'specialty' => 'Informatique',
                    'start_date' => now()->subMonth(),
                    'end_date' => now()->addMonth(),
                ]);
                $user->refresh();
            }

            $this->actingAs($user)
                ->get(route('dashboard'))
                ->assertOk()
                ->assertSee('Tableau de bord');
        }
    }
}
