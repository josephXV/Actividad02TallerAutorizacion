<?php

namespace Tests\Feature;

use App\Models\Carrera;
use App\Models\Estudiante;
use App\Models\Sede;
use App\Models\User;
use Database\Seeders\RolesYPermisosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AutorizacionEstudiantesTest extends TestCase
{
    use RefreshDatabase;

    private Carrera $iti;
    private Carrera $isw;
    private Sede $central;
    private Sede $norte;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesYPermisosSeeder::class);

        $this->iti = Carrera::create(['nombre' => 'Tecnologías de Información', 'codigo' => 'ITI']);
        $this->isw = Carrera::create(['nombre' => 'Ingeniería de Software', 'codigo' => 'ISW']);
        $this->central = Sede::create(['nombre' => 'Sede Central', 'codigo' => 'CEN']);
        $this->norte = Sede::create(['nombre' => 'Sede Norte', 'codigo' => 'NOR']);
    }

    private function usuario(string $rol, ?Carrera $carrera = null, ?Sede $sede = null): User
    {
        $user = User::factory()->create([
            'carrera_id' => $carrera?->id,
            'sede_id' => $sede?->id,
        ]);
        $user->assignRole($rol);

        return $user;
    }

    private function estudianteDe(User $user, Carrera $carrera, ?Sede $sede = null, bool $activo = true): Estudiante
    {
        return Estudiante::create([
            'user_id' => $user->id,
            'carrera_id' => $carrera->id,
            'sede_id' => $sede?->id,
            'cedula' => 'C-'.$user->id,
            'nombre' => 'Nombre'.$user->id,
            'apellido' => 'Apellido',
            'correo_institucional' => "e{$user->id}@test.test",
            'activo' => $activo,
        ]);
    }

    // ---------- Autenticación (401) vs. autorización (403) ----------

    public function test_request_without_token_is_unauthorized(): void
    {
        $this->getJson('/api/estudiantes')->assertUnauthorized();
        $this->getJson('/api/admin/dashboard')->assertUnauthorized();
    }

    public function test_login_returns_token_and_register_ignores_role(): void
    {
        $this->postJson('/api/register', [
            'name' => 'Hacker',
            'email' => 'h@test.test',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'role' => 'administrador',
            'roles' => ['administrador'],
        ])->assertCreated()->assertJsonStructure(['token']);

        $this->assertCount(0, User::where('email', 'h@test.test')->first()->roles);

        $this->postJson('/api/login', [
            'email' => 'h@test.test',
            'password' => 'Password123!',
        ])->assertOk()->assertJsonStructure(['token']);
    }

    // ---------- Gates ----------

    public function test_admin_dashboard_gate(): void
    {
        $this->actingAs($this->usuario('administrador'), 'sanctum')->getJson('/api/admin/dashboard')->assertOk();
        $this->actingAs($this->usuario('profesor', $this->iti), 'sanctum')->getJson('/api/admin/dashboard')->assertForbidden();
        $this->actingAs($this->usuario('estudiante', $this->iti), 'sanctum')->getJson('/api/admin/dashboard')->assertForbidden();
    }

    public function test_professor_dashboard_gate(): void
    {
        $this->actingAs($this->usuario('profesor', $this->iti), 'sanctum')->getJson('/api/profesor/dashboard')->assertOk();
        $this->actingAs($this->usuario('administrador'), 'sanctum')->getJson('/api/profesor/dashboard')->assertForbidden();
        $this->actingAs($this->usuario('estudiante', $this->iti), 'sanctum')->getJson('/api/profesor/dashboard')->assertForbidden();
    }

    // ---------- RBAC ----------

    public function test_only_admin_can_create_students(): void
    {
        $propietario = User::factory()->create();
        $payload = [
            'user_id' => $propietario->id,
            'carrera_id' => $this->iti->id,
            'cedula' => '3-3333-3333',
            'nombre' => 'Carlos',
            'apellido' => 'Ejemplo',
            'correo_institucional' => 'carlos@estudiantes.test',
            'activo' => true,
        ];

        $this->actingAs($this->usuario('profesor', $this->iti), 'sanctum')->postJson('/api/estudiantes', $payload)->assertForbidden();
        $this->actingAs($this->usuario('estudiante', $this->iti), 'sanctum')->postJson('/api/estudiantes', $payload)->assertForbidden();
        $this->actingAs($this->usuario('administrador'), 'sanctum')->postJson('/api/estudiantes', $payload)->assertCreated();
    }

    public function test_only_admin_can_delete_students(): void
    {
        $e = $this->estudianteDe(User::factory()->create(), $this->iti, $this->central);

        $this->actingAs($this->usuario('profesor', $this->iti, $this->central), 'sanctum')->deleteJson("/api/estudiantes/{$e->id}")->assertForbidden();
        $this->actingAs($this->usuario('administrador'), 'sanctum')->deleteJson("/api/estudiantes/{$e->id}")->assertOk();
    }

    public function test_any_authenticated_user_can_list_students(): void
    {
        foreach (['administrador', 'profesor', 'coordinador', 'estudiante'] as $rol) {
            $this->actingAs($this->usuario($rol, $this->iti, $this->central), 'sanctum')
                ->getJson('/api/estudiantes')->assertOk();
        }
    }

    // ---------- Policies: view ----------

    public function test_student_can_view_own_record(): void
    {
        $user = $this->usuario('estudiante', $this->isw);
        $e = $this->estudianteDe($user, $this->isw);

        $this->actingAs($user, 'sanctum')->getJson("/api/estudiantes/{$e->id}")->assertOk();
    }

    public function test_student_cannot_view_another_student_record(): void
    {
        $user1 = $this->usuario('estudiante', $this->isw);
        $e2 = $this->estudianteDe(User::factory()->create(), $this->isw);

        $this->actingAs($user1, 'sanctum')->getJson("/api/estudiantes/{$e2->id}")->assertForbidden();
    }

    // Actividad 2
    public function test_student_cannot_view_inactive_record_of_same_career(): void
    {
        $user = $this->usuario('estudiante', $this->iti, $this->central);
        $activo = $this->estudianteDe(User::factory()->create(), $this->iti, $this->central, true);
        $inactivo = $this->estudianteDe(User::factory()->create(), $this->iti, $this->central, false);
        $propioInactivo = $this->estudianteDe($user, $this->iti, $this->central, false);

        $this->actingAs($user, 'sanctum');
        $this->getJson("/api/estudiantes/{$inactivo->id}")->assertForbidden();
        $this->getJson("/api/estudiantes/{$activo->id}")->assertForbidden();
        $this->getJson("/api/estudiantes/{$propioInactivo->id}")->assertOk();
    }

    public function test_professor_views_only_students_of_own_career(): void
    {
        $prof = $this->usuario('profesor', $this->iti, $this->central);
        $ana = $this->estudianteDe(User::factory()->create(), $this->iti, $this->central);
        $luis = $this->estudianteDe(User::factory()->create(), $this->isw, $this->central);

        $this->actingAs($prof, 'sanctum');
        $this->getJson("/api/estudiantes/{$ana->id}")->assertOk();
        $this->getJson("/api/estudiantes/{$luis->id}")->assertForbidden();
    }

    // ---------- Policies / ABAC: update ----------

    public function test_student_cannot_update_own_record(): void
    {
        $user = $this->usuario('estudiante', $this->iti, $this->central);
        $e = $this->estudianteDe($user, $this->iti, $this->central);

        $this->actingAs($user, 'sanctum')->putJson("/api/estudiantes/{$e->id}", ['nombre' => 'X'])->assertForbidden();
    }

    public function test_admin_can_update_any_student(): void
    {
        $e = $this->estudianteDe(User::factory()->create(), $this->iti, $this->central);

        $this->actingAs($this->usuario('administrador'), 'sanctum')
            ->putJson("/api/estudiantes/{$e->id}", ['nombre' => 'Ana María'])
            ->assertOk()->assertJsonPath('nombre', 'Ana María');
    }

    public function test_professor_cannot_update_student_from_another_career(): void
    {
        $profesor = $this->usuario('profesor', $this->iti);
        $e = $this->estudianteDe(User::factory()->create(), $this->isw);

        $this->actingAs($profesor, 'sanctum')
            ->putJson("/api/estudiantes/{$e->id}", ['nombre' => 'Nombre modificado'])
            ->assertForbidden();
    }

    /**
     * Actividad 4: escenarios carrera × sede.
     */
    public function test_abac_career_and_site_matrix(): void
    {
        $profesor = $this->usuario('profesor', $this->iti, $this->central);

        $casos = [
            'misma carrera y misma sede' => [$this->iti, $this->central, 200],
            'misma carrera y sede diferente' => [$this->iti, $this->norte, 403],
            'carrera diferente y misma sede' => [$this->isw, $this->central, 403],
            'carrera diferente y sede diferente' => [$this->isw, $this->norte, 403],
        ];

        foreach ($casos as $nombre => [$carrera, $sede, $esperado]) {
            $e = $this->estudianteDe(User::factory()->create(), $carrera, $sede);

            $this->actingAs($profesor, 'sanctum')
                ->putJson("/api/estudiantes/{$e->id}", ['nombre' => 'Cambio'])
                ->assertStatus($esperado, "Escenario: {$nombre}");
        }
    }

    public function test_professor_without_site_cannot_update(): void
    {
        $profesor = $this->usuario('profesor', $this->iti, null);
        $e = $this->estudianteDe(User::factory()->create(), $this->iti, null);

        $this->actingAs($profesor, 'sanctum')->putJson("/api/estudiantes/{$e->id}", ['nombre' => 'X'])->assertForbidden();
    }

    // ---------- Actividad 3: coordinador ----------

    public function test_coordinator_has_expected_permissions(): void
    {
        $coord = $this->usuario('coordinador', $this->iti, $this->central);

        $this->assertEqualsCanonicalizing(
            ['estudiantes.ver', 'estudiantes.editar'],
            $coord->getAllPermissions()->pluck('name')->all()
        );
        $this->assertFalse($coord->can('estudiantes.crear'));
        $this->assertFalse($coord->can('estudiantes.eliminar'));
    }

    public function test_coordinator_can_update_only_within_abac_scope(): void
    {
        $coord = $this->usuario('coordinador', $this->iti, $this->central);
        $propio = $this->estudianteDe(User::factory()->create(), $this->iti, $this->central);
        $otro = $this->estudianteDe(User::factory()->create(), $this->isw, $this->central);

        $this->actingAs($coord, 'sanctum');
        $this->putJson("/api/estudiantes/{$propio->id}", ['nombre' => 'OK'])->assertOk();
        $this->putJson("/api/estudiantes/{$otro->id}", ['nombre' => 'NO'])->assertForbidden();
        $this->deleteJson("/api/estudiantes/{$propio->id}")->assertForbidden();
    }
}
