# API Académica – Taller de Autorización (Laravel 13)

Curso **Tecnologías y Sistemas Web III (ITI-621)** · Actividad 02: mecanismos de autorización
(Gates, Policies, RBAC y ABAC) sobre una API REST de gestión de estudiantes.

Stack: Laravel 13 · PHP 8.3+ · Sanctum · Spatie Laravel Permission · SQLite

## Estado de la base

Esta rama `main` contiene **solo la base**: autenticación con Sanctum (`register`, `login`, `me`, `logout`),
modelos y migraciones (`carreras`, `estudiantes`, `users.carrera_id`), roles/permisos de Spatie
(`administrador`, `profesor`, `estudiante`) y datos de prueba. **Todavía no hay** Gates, Policies,
controlador de estudiantes, pruebas de autorización ni actividades: cada integrante las agrega con su commit.

## Instalación

```bash
git clone <URL-DEL-REPO>
cd api-academica
composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate:fresh --seed
php artisan serve
```

Usuarios de prueba (contraseña `Password123!`):

| Usuario | Rol | Carrera |
|---|---|---|
| admin@academica.test | administrador | – |
| profesor.iti@academica.test | profesor | ITI |
| profesor.isw@academica.test | profesor | ISW |
| estudiante1@academica.test | estudiante | ITI |
| estudiante2@academica.test | estudiante | ISW |

## Quién hace qué

Cada integrante sigue su guía en `docs/tareas/`, trabaja en su propia rama (`NOMBRE/parte`) y abre un PR a `main`.

| Orden de merge | Integrante | Guía | Parte |
|---|---|---|---|
| – | Joseph | [JOSEPH.md](docs/tareas/JOSEPH.md) | Base del proyecto + reflexión (preguntas 1 y 2) |
| 1 | Diana Ramírez García | [DIANA.md](docs/tareas/DIANA.md) | Gate `access-admin-dashboard`, ruta `/api/admin/dashboard`, Actividad 1 (Gate y ruta del profesor), reflexión (3 y 4) |
| 2 | Franklin Josué Castillo Umaña | [FRANKLIN.md](docs/tareas/FRANKLIN.md) | `EstudianteController`, rutas de estudiantes, `EstudiantePolicy` base, Actividad 2 (inactivos), reflexión (5 y 6) |
| 3 | Emesis Mayerli Mairena Sevilla | [EMESIS.md](docs/tareas/EMESIS.md) | Actividad 3 (rol `coordinador`), colección Postman, `evidencias_http.md`, reflexión (7 y 8) |
| 4 | Jairo Steven Herrera Romero | [JAIRO.md](docs/tareas/JAIRO.md) | Actividad 4 (`sede_id` + ABAC carrera y sede), `AutorizacionEstudiantesTest`, discusión 19.2 |

**Orden de merge obligatorio:** Diana → Franklin → Emesis → Jairo (cada parte depende de la anterior).
Antes de empezar, haz `git pull origin main` si la persona anterior ya hizo merge.

## Reglas del equipo

- Un PR por persona, hacia `main`; no hagas push directo a `main`.
- Nunca subas `.env`, `vendor/` ni `database/database.sqlite`.
- Mensajes de commit con formato `feat: ...`.
