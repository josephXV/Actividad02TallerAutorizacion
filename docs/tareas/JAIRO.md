# Tarea de Jairo Steven Herrera Romero

**Rama:** `jairo/abac-sede-tests` · **Reflexión:** Discusión 19.2

> **Orden de merge del equipo:** Diana → Franklin → Emesis → Jairo.  
> Haces merge **al final** (después de Emesis). Antes de empezar: `git pull origin main` (debe traer el rol coordinador y la Policy de Emesis). Si Emesis aún no hizo merge, espera: tu `EstudiantePolicy.php` y tu `DatosPruebaSeeder.php` reemplazan los de ella y ya incluyen su trabajo.

> Los bloques de código de esta guía están completos y listos para pegar: reemplaza **todo** el contenido del archivo (salvo que diga lo contrario). Los archivos están en la raíz del proyecto `api-academica`.

## 1. Preparar el entorno

```bash
git clone https://github.com/josephXV/Actividad02TallerAutorizacion.git api-academica
cd api-academica
composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate:fresh --seed
git checkout -b jairo/abac-sede-tests
```

- En PowerShell, `touch database/database.sqlite` es `New-Item database/database.sqlite -ItemType File`.
- Comprueba tu identidad de Git (debe ser **tu** usuario de GitHub, para que el commit quede a tu nombre):
  `git config user.name` y `git config user.email` (si están vacíos: `git config user.name "Tu Nombre"` y `git config user.email "tu-correo-de-github"`).
- Si ya clonaste antes, no vuelvas a clonar: `cd api-academica`, `git checkout main`, `git pull origin main` y luego crea tu rama.

## 2. Archivos a crear o modificar

### 1. Crear `database/migrations/2026_11_24_000418_create_sedes_table.php`

Migración de la tabla `sedes`.

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sedes', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 120)->unique();
            $table->string('codigo', 20)->unique();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sedes');
    }
};
```

### 2. Crear `database/migrations/2026_11_24_000419_add_sede_id_to_users_and_estudiantes.php`

Migración que agrega `sede_id` a `users` y `estudiantes`.

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('sede_id')->nullable()->constrained('sedes')->nullOnDelete();
        });

        Schema::table('estudiantes', function (Blueprint $table) {
            $table->foreignId('sede_id')->nullable()->constrained('sedes')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('estudiantes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('sede_id');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('sede_id');
        });
    }
};
```

### 3. Crear `app/Models/Sede.php`

Modelo `Sede`.

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Sede extends Model
{
    protected $table = 'sedes';

    protected $fillable = ['nombre', 'codigo'];
}
```

### 4. Reemplazar `app/Models/User.php`

Agrega `sede_id` a `#[Fillable]` y la relación `sede()`.

```php
<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'email', 'password', 'carrera_id', 'sede_id'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable, HasRoles;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function carrera(): BelongsTo
    {
        return $this->belongsTo(Carrera::class);
    }

    public function sede(): BelongsTo
    {
        return $this->belongsTo(Sede::class);
    }
}
```

### 5. Reemplazar `app/Models/Estudiante.php`

Agrega `sede_id` a `$fillable` y la relación `sede()`.

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Estudiante extends Model
{
    protected $fillable = [
        'user_id',
        'carrera_id',
        'sede_id',
        'cedula',
        'nombre',
        'apellido',
        'correo_institucional',
        'activo',
    ];

    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
        ];
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function carrera(): BelongsTo
    {
        return $this->belongsTo(Carrera::class);
    }

    public function sede(): BelongsTo
    {
        return $this->belongsTo(Sede::class);
    }
}
```

### 6. Reemplazar `app/Http/Controllers/Api/EstudianteController.php`

Valida `sede_id` en `store` y `update`.

```php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Estudiante;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class EstudianteController extends Controller
{
    public function index()
    {
        Gate::authorize('viewAny', Estudiante::class);

        return Estudiante::with(['carrera', 'usuario'])
            ->paginate(10);
    }

    public function show(Estudiante $estudiante)
    {
        Gate::authorize('view', $estudiante);

        return $estudiante->load(['carrera', 'usuario']);
    }

    public function store(Request $request)
    {
        Gate::authorize('create', Estudiante::class);

        $data = $request->validate([
            'user_id' => ['required', 'exists:users,id', 'unique:estudiantes,user_id'],
            'carrera_id' => ['required', 'exists:carreras,id'],
            'sede_id' => ['sometimes', 'nullable', 'exists:sedes,id'],
            'cedula' => ['required', 'string', 'max:30', 'unique:estudiantes,cedula'],
            'nombre' => ['required', 'string', 'max:120'],
            'apellido' => ['required', 'string', 'max:120'],
            'correo_institucional' => ['required', 'email', 'max:150', 'unique:estudiantes,correo_institucional'],
            'activo' => ['sometimes', 'boolean'],
        ]);

        $estudiante = Estudiante::create($data);

        return response()->json($estudiante, 201);
    }

    public function update(Request $request, Estudiante $estudiante)
    {
        Gate::authorize('update', $estudiante);

        $data = $request->validate([
            'carrera_id' => ['sometimes', 'exists:carreras,id'],
            'sede_id' => ['sometimes', 'nullable', 'exists:sedes,id'],
            'cedula' => [
                'sometimes',
                'string',
                'max:30',
                Rule::unique('estudiantes', 'cedula')->ignore($estudiante->id),
            ],
            'nombre' => ['sometimes', 'string', 'max:120'],
            'apellido' => ['sometimes', 'string', 'max:120'],
            'correo_institucional' => [
                'sometimes',
                'email',
                'max:150',
                Rule::unique('estudiantes', 'correo_institucional')->ignore($estudiante->id),
            ],
            'activo' => ['sometimes', 'boolean'],
        ]);

        $estudiante->update($data);

        return response()->json($estudiante);
    }

    public function destroy(Estudiante $estudiante)
    {
        Gate::authorize('delete', $estudiante);

        $estudiante->delete();

        return response()->json([
            'message' => 'Estudiante eliminado correctamente',
        ]);
    }
}
```

### 7. Reemplazar `database/seeders/DatosPruebaSeeder.php`

Versión final: sedes (Central y Norte), usuarios y estudiantes con sede, profesor de Sede Norte.

```php
<?php

namespace Database\Seeders;

use App\Models\Carrera;
use App\Models\Estudiante;
use App\Models\Sede;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatosPruebaSeeder extends Seeder
{
    public function run(): void
    {
        $sistemas = Carrera::firstOrCreate(
            ['codigo' => 'ITI'],
            ['nombre' => 'Ingeniería en Tecnologías de Información']
        );

        $software = Carrera::firstOrCreate(
            ['codigo' => 'ISW'],
            ['nombre' => 'Ingeniería de Software']
        );

        $central = Sede::firstOrCreate(['codigo' => 'CEN'], ['nombre' => 'Sede Central']);
        $norte = Sede::firstOrCreate(['codigo' => 'NOR'], ['nombre' => 'Sede Norte']);

        $crearUsuario = function (string $email, string $name, string $rol, ?Carrera $carrera, ?Sede $sede): User {
            $user = User::firstOrCreate(
                ['email' => $email],
                [
                    'name' => $name,
                    'password' => Hash::make('Password123!'),
                    'carrera_id' => $carrera?->id,
                    'sede_id' => $sede?->id,
                ]
            );
            $user->syncRoles([$rol]);

            return $user;
        };

        $crearUsuario('admin@academica.test', 'Administrador General', 'administrador', null, null);
        $crearUsuario('profesor.iti@academica.test', 'Profesor de TI', 'profesor', $sistemas, $central);
        $crearUsuario('profesor.isw@academica.test', 'Profesor de Software', 'profesor', $software, $central);
        $crearUsuario('profesor.iti.norte@academica.test', 'Profesor de TI (Norte)', 'profesor', $sistemas, $norte);
        $crearUsuario('coordinador.iti@academica.test', 'Coordinador de TI', 'coordinador', $sistemas, $central);

        $usuarioEstudiante1 = $crearUsuario('estudiante1@academica.test', 'Ana Estudiante', 'estudiante', $sistemas, $central);
        $usuarioEstudiante2 = $crearUsuario('estudiante2@academica.test', 'Luis Estudiante', 'estudiante', $software, $central);
        $usuarioEstudiante3 = $crearUsuario('estudiante3@academica.test', 'Marta Inactiva', 'estudiante', $sistemas, $central);

        Estudiante::firstOrCreate(
            ['user_id' => $usuarioEstudiante1->id],
            [
                'carrera_id' => $sistemas->id,
                'sede_id' => $central->id,
                'cedula' => '1-1111-1111',
                'nombre' => 'Ana',
                'apellido' => 'Estudiante',
                'correo_institucional' => 'ana@estudiantes.test',
                'activo' => true,
            ]
        );

        Estudiante::firstOrCreate(
            ['user_id' => $usuarioEstudiante2->id],
            [
                'carrera_id' => $software->id,
                'sede_id' => $central->id,
                'cedula' => '2-2222-2222',
                'nombre' => 'Luis',
                'apellido' => 'Estudiante',
                'correo_institucional' => 'luis@estudiantes.test',
                'activo' => true,
            ]
        );

        // Registro inactivo de la misma carrera de Ana (Actividad 2).
        Estudiante::firstOrCreate(
            ['user_id' => $usuarioEstudiante3->id],
            [
                'carrera_id' => $sistemas->id,
                'sede_id' => $central->id,
                'cedula' => '4-4444-4444',
                'nombre' => 'Marta',
                'apellido' => 'Inactiva',
                'correo_institucional' => 'marta@estudiantes.test',
                'activo' => false,
            ]
        );
    }
}
```

### 8. Reemplazar `app/Policies/EstudiantePolicy.php`

Regla ABAC final: profesor/coordinador editan solo con la **misma carrera y la misma sede**.

```php
<?php

namespace App\Policies;

use App\Models\Estudiante;
use App\Models\User;

class EstudiantePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('estudiantes.ver');
    }

    public function view(User $user, Estudiante $estudiante): bool
    {
        // El administrador puede consultar cualquier registro.
        if ($user->hasRole('administrador')) {
            return true;
        }

        // El profesor y el coordinador consultan registros de su carrera.
        if ($user->hasAnyRole(['profesor', 'coordinador'])) {
            return $user->carrera_id !== null
                && $user->carrera_id === $estudiante->carrera_id;
        }

        if ($user->hasRole('estudiante')) {
            // Actividad 2: un estudiante nunca consulta registros inactivos de otros,
            // aunque pertenezcan a su misma carrera.
            if ($estudiante->user_id !== $user->id && ! $estudiante->activo) {
                return false;
            }

            // El estudiante solo puede consultar su propio registro.
            return $estudiante->user_id === $user->id;
        }

        return false;
    }

    public function create(User $user): bool
    {
        return $user->can('estudiantes.crear');
    }

    public function update(User $user, Estudiante $estudiante): bool
    {
        // El administrador tiene permiso general de modificación.
        if ($user->hasRole('administrador')) {
            return $user->can('estudiantes.editar');
        }

        // ABAC (Actividad 4): permiso RBAC + misma carrera + misma sede.
        // Aplica a profesor y coordinador (Actividad 3).
        if ($user->hasAnyRole(['profesor', 'coordinador'])) {
            return $user->can('estudiantes.editar')
                && $user->carrera_id !== null
                && $user->carrera_id === $estudiante->carrera_id
                && $user->sede_id !== null
                && $user->sede_id === $estudiante->sede_id;
        }

        return false;
    }

    public function delete(User $user, Estudiante $estudiante): bool
    {
        return $user->can('estudiantes.eliminar');
    }
}
```

### 9. Crear `tests/Feature/AutorizacionEstudiantesTest.php`

Las 18 pruebas automatizadas de autorización.

```php
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
```

### 10. Reflexión: `entrega/Reflexion_Seguridad.md` (Discusión 19.2)

Abre el archivo y reemplaza **solo** la línea `_Pendiente: ..._` que está entre `<!-- INICIO JAIRO ... -->` y `<!-- FIN JAIRO -->` por el texto siguiente (no borres los marcadores ni toques las secciones de los demás). Puedes mejorar la redacción con tus propias palabras.

```markdown
1. **¿Qué sucedería si el profesor cambia de carrera?** Como el atributo se evalúa en cada petición, pierde de inmediato el acceso a la carrera anterior y lo gana en la nueva. Los tokens existentes siguen siendo válidos porque la decisión no está guardada en el token.
2. **¿Qué sucede si el estudiante cambia de carrera?** El profesor de la carrera anterior deja de poder modificarlo y el de la nueva carrera puede hacerlo (siempre que coincida la sede).
3. **¿Por qué no basta con verificar el rol `profesor`?** Autorizaría a cualquier profesor sobre cualquier estudiante de la institución.
4. **¿Qué otros atributos podrían incorporarse?** Período académico, estado de matrícula, sede, departamento, horario, nivel de la asignatura y estado de la cuenta.
```

## 3. Cómo probarlo

Reconstruye la base (hay migraciones nuevas): `php artisan migrate:fresh --seed`.

**Pruebas automatizadas** (lo principal de tu parte):

```bash
php artisan test
```

Resultado esperado: **18 passed (44 assertions)**. Si algo falla, copia el error en el grupo antes de hacer commit.

La prueba `test_abac_career_and_site_matrix` cubre los 4 escenarios de la Actividad 4:

| Escenario | Resultado |
|---|---|
| Misma carrera y misma sede | 200 (permitido) |
| Misma carrera y sede diferente | 403 |
| Carrera diferente y misma sede | 403 |
| Carrera diferente y sede diferente | 403 |

**Prueba manual opcional** (procedimiento de login/token como en las otras guías): con `profesor.iti.norte@academica.test` (ITI, Sede Norte) hacer `PUT /api/estudiantes/1` con `{"nombre":"X"}` → **403** (misma carrera, otra sede); con `profesor.iti@academica.test` (ITI, Sede Central) → **200**.

## 4. Commit, push y Pull Request

Revisa que solo hayas tocado tus archivos:

```bash
git status
git diff --stat
```

Sube tu parte:

```bash
git add database/migrations app/Models app/Http/Controllers/Api/EstudianteController.php database/seeders/DatosPruebaSeeder.php app/Policies/EstudiantePolicy.php tests/Feature/AutorizacionEstudiantesTest.php entrega/Reflexion_Seguridad.md
git commit -m "feat: sede_id con regla ABAC carrera+sede y pruebas de autorización (Actividad 4)"
git push -u origin jairo/abac-sede-tests
```

Abre el Pull Request hacia `main` (con GitHub CLI o desde la página del repositorio, botón *Compare & pull request*):

```bash
gh pr create --base main --head jairo/abac-sede-tests --title "feat: ABAC por carrera y sede + pruebas automatizadas - Actividad 4" --body "Agrega sedes (migraciones, modelo, seeders), la regla ABAC carrera+sede en EstudiantePolicy, las 18 pruebas de autorización (todas en verde) y la discusión 19.2."
```

Avisa en el grupo cuando abras el PR. Joseph revisa y hace el merge respetando el orden **Diana → Franklin → Emesis → Jairo**.
Si al abrir el PR GitHub marca conflictos, es porque falta hacer `git pull origin main` con los merges anteriores: no resuelvas a ciegas, avisa en el grupo.
