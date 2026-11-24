# Tarea de Franklin Josué Castillo Umaña

**Rama:** `franklin/estudiantes-policy` · **Reflexión:** Preguntas 5 y 6

> **Orden de merge del equipo:** Diana → Franklin → Emesis → Jairo.  
> Haces merge **después de Diana**. Antes de empezar: `git pull origin main` (debe traer los Gates y el `routes/api.php` de Diana). Si Diana aún no hizo merge, espera.

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
git checkout -b franklin/estudiantes-policy
```

- En PowerShell, `touch database/database.sqlite` es `New-Item database/database.sqlite -ItemType File`.
- Comprueba tu identidad de Git (debe ser **tu** usuario de GitHub, para que el commit quede a tu nombre):
  `git config user.name` y `git config user.email` (si están vacíos: `git config user.name "Tu Nombre"` y `git config user.email "tu-correo-de-github"`).
- Si ya clonaste antes, no vuelvas a clonar: `cd api-academica`, `git checkout main`, `git pull origin main` y luego crea tu rama.

## 2. Archivos a crear o modificar

### 1. Crear `app/Http/Controllers/Api/EstudianteController.php`

Controlador CRUD de estudiantes; cada método llama a `Gate::authorize`.

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

### 2. Reemplazar `routes/api.php`

Reemplaza el archivo: conserva lo de Diana y agrega las rutas explícitas de estudiantes (POST con `permission:estudiantes.crear`).

```php
<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\EstudianteController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {

    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);

    Route::get('/admin/dashboard', function (Request $request) {
        Gate::authorize('access-admin-dashboard');

        return response()->json([
            'message' => 'Bienvenido al dashboard administrativo',
            'user' => $request->user()->name,
        ]);
    });

    // Actividad 1
    Route::get('/profesor/dashboard', function (Request $request) {
        Gate::authorize('access-professor-dashboard');

        return response()->json([
            'message' => 'Bienvenido al dashboard de profesores',
            'user' => $request->user()->name,
        ]);
    });

    // Rutas explícitas (no apiResource) para aplicar el middleware RBAC por endpoint.
    Route::get('/estudiantes', [EstudianteController::class, 'index']);

    Route::post('/estudiantes', [EstudianteController::class, 'store'])
        ->middleware('permission:estudiantes.crear');

    Route::get('/estudiantes/{estudiante}', [EstudianteController::class, 'show']);
    Route::put('/estudiantes/{estudiante}', [EstudianteController::class, 'update']);
    Route::patch('/estudiantes/{estudiante}', [EstudianteController::class, 'update']);
    Route::delete('/estudiantes/{estudiante}', [EstudianteController::class, 'destroy']);

});
```

### 3. Crear `app/Policies/EstudiantePolicy.php`

Policy base (RBAC + regla de carrera del profesor) y Actividad 2 (un estudiante no ve registros inactivos de otros).

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

        // El profesor consulta registros de su carrera.
        if ($user->hasRole('profesor')) {
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

        // ABAC: el profesor necesita el permiso RBAC y pertenecer a la misma carrera.
        if ($user->hasRole('profesor')) {
            return $user->can('estudiantes.editar')
                && $user->carrera_id !== null
                && $user->carrera_id === $estudiante->carrera_id;
        }

        return false;
    }

    public function delete(User $user, Estudiante $estudiante): bool
    {
        return $user->can('estudiantes.eliminar');
    }
}
```

### 4. Reemplazar `database/seeders/DatosPruebaSeeder.php`

Agrega a Marta (estudiante3, **inactiva**, carrera ITI) para probar la Actividad 2.

```php
<?php

namespace Database\Seeders;

use App\Models\Carrera;
use App\Models\Estudiante;
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

        $crearUsuario = function (string $email, string $name, string $rol, ?Carrera $carrera): User {
            $user = User::firstOrCreate(
                ['email' => $email],
                [
                    'name' => $name,
                    'password' => Hash::make('Password123!'),
                    'carrera_id' => $carrera?->id,
                ]
            );
            $user->syncRoles([$rol]);

            return $user;
        };

        $crearUsuario('admin@academica.test', 'Administrador General', 'administrador', null);
        $crearUsuario('profesor.iti@academica.test', 'Profesor de TI', 'profesor', $sistemas);
        $crearUsuario('profesor.isw@academica.test', 'Profesor de Software', 'profesor', $software);

        $usuarioEstudiante1 = $crearUsuario('estudiante1@academica.test', 'Ana Estudiante', 'estudiante', $sistemas);
        $usuarioEstudiante2 = $crearUsuario('estudiante2@academica.test', 'Luis Estudiante', 'estudiante', $software);
        $usuarioEstudiante3 = $crearUsuario('estudiante3@academica.test', 'Marta Inactiva', 'estudiante', $sistemas);

        Estudiante::firstOrCreate(
            ['user_id' => $usuarioEstudiante1->id],
            [
                'carrera_id' => $sistemas->id,
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
                'cedula' => '2-2222-2222',
                'nombre' => 'Luis',
                'apellido' => 'Estudiante',
                'correo_institucional' => 'luis@estudiantes.test',
                'activo' => true,
            ]
        );

        // Actividad 2: registro inactivo de la misma carrera de Ana.
        Estudiante::firstOrCreate(
            ['user_id' => $usuarioEstudiante3->id],
            [
                'carrera_id' => $sistemas->id,
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

### 5. Reflexión: `entrega/Reflexion_Seguridad.md` (Preguntas 5 y 6)

Abre el archivo y reemplaza **solo** la línea `_Pendiente: ..._` que está entre `<!-- INICIO FRANKLIN ... -->` y `<!-- FIN FRANKLIN -->` por el texto siguiente (no borres los marcadores ni toques las secciones de los demás). Puedes mejorar la redacción con tus propias palabras.

```markdown
**5. ¿Por qué `estudiantes.editar` no debería permitir modificar a cualquier estudiante?** El permiso solo indica que la *clase* de acción está habilitada. Sin verificar atributos, un profesor de ITI podría modificar estudiantes de ISW o de otra sede (acceso horizontal indebido, IDOR). Por eso la Policy exige además misma carrera (y, en la Actividad 4, misma sede).

**6. ¿Qué riesgo existe si el registro público permite enviar el rol?** Escalada de privilegios por asignación masiva: cualquiera podría registrarse como `administrador`. Los roles se asignan solo desde código de confianza (seeders o un panel administrativo autorizado), nunca desde datos que envía el cliente.
```

## 3. Cómo probarlo

Primero reconstruye la base (hay un usuario y estudiante nuevos): `php artisan migrate:fresh --seed`.

Rutas: `/api/estudiantes` y `/api/estudiantes/{id}` (id 1 = Ana ITI, 2 = Luis ISW, 3 = Marta ITI **inactiva**). Usa el procedimiento de abajo.

```bash
# 1) Inicia el servidor (déjalo corriendo en otra terminal)
php artisan serve

# 2) Obtén un token (cambia el correo según el usuario a probar) y cópialo del resultado
curl -s -X POST http://127.0.0.1:8000/api/login \
  -H 'Accept: application/json' -H 'Content-Type: application/json' \
  -d '{"email":"admin@academica.test","password":"Password123!"}'

# 3) Guarda el token en una variable
export TOKEN='PEGA_AQUI_EL_TOKEN_REAL'

# 4) Llama al endpoint
curl -i http://127.0.0.1:8000/api/RUTA -H 'Accept: application/json' -H "Authorization: Bearer $TOKEN"
```

> En PowerShell usa `curl.exe` (no `curl`) y `$env:TOKEN='...'`, o prueba todo con **Postman/Bruno**:
> `POST /api/login` y luego Authorization → Bearer Token. No escribas literalmente `PEGA_AQUI_EL_TOKEN_REAL`.

Para PUT/POST añade `-X PUT -H 'Content-Type: application/json' -d '{"nombre":"Ana María","apellido":"Estudiante"}'`. DELETE: `-X DELETE`.

| Usuario | Operación | Registro | HTTP esperado |
|---|---|---|---|
| (sin token) | GET `/api/estudiantes` | – | **401** |
| estudiante1 (Ana) | GET | 1 (Ana) | **200** |
| estudiante1 (Ana) | GET | 2 (Luis) | **403** |
| estudiante1 (Ana) | GET | 3 (Marta, inactiva, misma carrera) | **403** (Actividad 2) |
| admin | GET | 1 | **200** |
| profesor.iti | GET | 1 | **200** |
| profesor.iti | GET | 2 | **403** |
| profesor.iti | PUT | 1 | **200** |
| profesor.iti | PUT | 2 | **403** |
| profesor.isw | PUT | 1 | **403** |
| estudiante1 (Ana) | PUT | 1 | **403** |
| profesor.iti | POST `/api/estudiantes` | – | **403** (sin `estudiantes.crear`) |
| admin | POST con JSON válido (ver abajo) | – | **201** |
| admin | DELETE | 1 | **200** |

JSON válido para POST (`user_id` debe ser un usuario sin estudiante, p. ej. `1` = admin; si ya existe, usa otro):

```json
{"user_id":1,"carrera_id":1,"cedula":"3-3333-3333","nombre":"Carlos","apellido":"Ejemplo","correo_institucional":"carlos@estudiantes.test","activo":true}
```

Revisa también `php artisan route:list --path=api`: deben verse las rutas de estudiantes dentro de `auth:sanctum`.

## 4. Commit, push y Pull Request

Revisa que solo hayas tocado tus archivos:

```bash
git status
git diff --stat
```

Sube tu parte:

```bash
git add app/Http/Controllers/Api/EstudianteController.php routes/api.php app/Policies/EstudiantePolicy.php database/seeders/DatosPruebaSeeder.php entrega/Reflexion_Seguridad.md
git commit -m "feat: controlador de estudiantes, rutas y EstudiantePolicy con regla de inactivos (Actividad 2)"
git push -u origin franklin/estudiantes-policy
```

Abre el Pull Request hacia `main` (con GitHub CLI o desde la página del repositorio, botón *Compare & pull request*):

```bash
gh pr create --base main --head franklin/estudiantes-policy --title "feat: EstudianteController, rutas y EstudiantePolicy - Actividad 2" --body "Agrega EstudianteController, rutas explícitas (POST con permission:estudiantes.crear), EstudiantePolicy base, regla de registros inactivos (Actividad 2), a Marta inactiva en el seeder y mi sección de la reflexión (5 y 6). Probado con curl según la tabla de la guía."
```

Avisa en el grupo cuando abras el PR. Joseph revisa y hace el merge respetando el orden **Diana → Franklin → Emesis → Jairo**.
Si al abrir el PR GitHub marca conflictos, es porque falta hacer `git pull origin main` con los merges anteriores: no resuelvas a ciegas, avisa en el grupo.
