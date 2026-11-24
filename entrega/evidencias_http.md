# Evidencias de pruebas HTTP (estado: base + Gates + Policy + coordinador)

Resultados esperados según la guía. Pendientes de verificar mediante ejecución local con `php artisan serve` y `migrate:fresh --seed`.

| Caso | Petición | HTTP |
|---|---|---|
| Sin token | GET /estudiantes | 401 |
| Admin dashboard (admin) | GET /admin/dashboard | 200 |
| Admin dashboard (profesor ITI) | GET /admin/dashboard | 403 |
| Admin dashboard (estudiante) | GET /admin/dashboard | 403 |
| Profesor dashboard (profesor) | GET /profesor/dashboard | 200 |
| Profesor dashboard (admin) | GET /profesor/dashboard | 403 |
| Profesor dashboard (estudiante) | GET /profesor/dashboard | 403 |
| Admin ve Ana(1) | GET /estudiantes/1 | 200 |
| Ana ve Ana(1) | GET /estudiantes/1 | 200 |
| Ana ve Luis(2) | GET /estudiantes/2 | 403 |
| Ana ve Marta inactiva(3) | GET /estudiantes/3 | 403 |
| Profesor ITI ve Ana(1) | GET /estudiantes/1 | 200 |
| Profesor ITI ve Luis(2) | GET /estudiantes/2 | 403 |
| Profesor ITI edita Ana(1) | PUT /estudiantes/1 | 200 |
| Profesor ITI edita Luis(2) | PUT /estudiantes/2 | 403 |
| Profesor ISW edita Ana(1) | PUT /estudiantes/1 | 403 |
| Coordinador ITI edita Ana(1) | PUT /estudiantes/1 | 200 |
| Coordinador ITI edita Luis(2) | PUT /estudiantes/2 | 403 |
| Coordinador ITI elimina Ana(1) | DELETE /estudiantes/1 | 403 |
| Ana edita Ana(1) | PUT /estudiantes/1 | 403 |
| Profesor crea estudiante | POST /estudiantes | 403 |
| Admin crea estudiante (user_id 1) | POST /estudiantes | 201 |
| Admin elimina Ana(1) | DELETE /estudiantes/1 | 200 |

## Verificación del rol coordinador mediante Tinker

| Verificación | Resultado obtenido |
|---|---|
| Rol asignado | coordinador |
| Permiso estudiantes.ver | Permitido |
| Permiso estudiantes.editar | Permitido |
| Permiso estudiantes.crear | false |
| Permiso estudiantes.eliminar | false |

Las migraciones y los seeders se ejecutaron correctamente.
Los permisos del coordinador fueron verificados mediante Tinker.

Las pruebas HTTP quedan pendientes de la integración
de los controladores y las rutas del equipo.
