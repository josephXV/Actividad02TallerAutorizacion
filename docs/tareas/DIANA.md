# Tarea de Diana Ramírez García

**Rama:** `diana/gates-dashboards` · **Reflexión:** Preguntas 3 y 4

> **Orden de merge del equipo:** Diana → Franklin → Emesis → Jairo.  
> Eres la **primera** en hacer merge (después de la base de Joseph). No dependes de nadie más.

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
git checkout -b diana/gates-dashboards
```

- En PowerShell, `touch database/database.sqlite` es `New-Item database/database.sqlite -ItemType File`.
- Comprueba tu identidad de Git (debe ser **tu** usuario de GitHub, para que el commit quede a tu nombre):
  `git config user.name` y `git config user.email` (si están vacíos: `git config user.name "Tu Nombre"` y `git config user.email "tu-correo-de-github"`).
- Si ya clonaste antes, no vuelvas a clonar: `cd api-academica`, `git checkout main`, `git pull origin main` y luego crea tu rama.

## 2. Archivos a crear o modificar

### 1. Reemplazar `app/Providers/AppServiceProvider.php`

Define los Gates `access-admin-dashboard` y `access-professor-dashboard` (Actividad 1).

```php
<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Gate::define('access-admin-dashboard', function (User $user) {
            return $user->hasRole('administrador');
        });

        // Actividad 1: Gate del dashboard de profesores.
        Gate::define('access-professor-dashboard', function (User $user) {
            return $user->hasRole('profesor');
        });
    }
}
```

### 2. Reemplazar `routes/api.php`

Agrega las rutas `/api/admin/dashboard` y `/api/profesor/dashboard` protegidas con los Gates.

```php
<?php

use App\Http\Controllers\Api\AuthController;
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

});
```

### 3. Reflexión: `entrega/Reflexion_Seguridad.md` (Preguntas 3 y 4)

Abre el archivo y reemplaza **solo** la línea `_Pendiente: ..._` que está entre `<!-- INICIO DIANA ... -->` y `<!-- FIN DIANA -->` por el texto siguiente (no borres los marcadores ni toques las secciones de los demás). Puedes mejorar la redacción con tus propias palabras.

```markdown
**3. ¿Por qué las Policies son adecuadas para operaciones CRUD?** Una Policy agrupa por modelo (`viewAny`, `view`, `create`, `update`, `delete`) las reglas que sí dependen del recurso. Se descubre automáticamente por convención, se invoca con `Gate::authorize` y centraliza la lógica, de modo que no queda dispersa en los controladores.

**4. ¿Cuál es la diferencia entre RBAC y ABAC?** RBAC decide por el rol o permiso asignado (`estudiantes.editar`). ABAC decide por atributos del usuario, del recurso y del contexto (carrera, sede, propiedad, estado activo, horario). En el taller se combinan: el permiso RBAC es necesario pero no suficiente.
```

## 3. Cómo probarlo

Rutas: `GET /api/admin/dashboard` y `GET /api/profesor/dashboard` (usa el procedimiento de abajo cambiando `RUTA`).

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

Resultados esperados (contraseña de todos: `Password123!`):

| Usuario | Ruta | HTTP esperado |
|---|---|---|
| (sin token) | `/api/admin/dashboard` | **401** |
| admin@academica.test | `/api/admin/dashboard` | **200** |
| profesor.iti@academica.test | `/api/admin/dashboard` | **403** |
| estudiante1@academica.test | `/api/admin/dashboard` | **403** |
| profesor.iti@academica.test | `/api/profesor/dashboard` | **200** |
| admin@academica.test | `/api/profesor/dashboard` | **403** |
| estudiante1@academica.test | `/api/profesor/dashboard` | **403** |

Guarda capturas o la salida de `curl -i` de los tres roles (es el entregable de la Actividad 1).

## 4. Commit, push y Pull Request

Revisa que solo hayas tocado tus archivos:

```bash
git status
git diff --stat
```

Sube tu parte:

```bash
git add app/Providers/AppServiceProvider.php routes/api.php entrega/Reflexion_Seguridad.md
git commit -m "feat: gates de dashboard admin y profesor con sus rutas (Actividad 1)"
git push -u origin diana/gates-dashboards
```

Abre el Pull Request hacia `main` (con GitHub CLI o desde la página del repositorio, botón *Compare & pull request*):

```bash
gh pr create --base main --head diana/gates-dashboards --title "feat: Gates y dashboards (admin y profesor) - Actividad 1" --body "Agrega los Gates access-admin-dashboard y access-professor-dashboard, sus rutas y mi sección de la reflexión (preguntas 3 y 4). Probado con admin, profesor y estudiante: 200/403/403 y 401 sin token."
```

Avisa en el grupo cuando abras el PR. Joseph revisa y hace el merge respetando el orden **Diana → Franklin → Emesis → Jairo**.
Si al abrir el PR GitHub marca conflictos, es porque falta hacer `git pull origin main` con los merges anteriores: no resuelvas a ciegas, avisa en el grupo.
