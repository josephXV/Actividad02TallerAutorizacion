# Tarea de Emesis Mayerli Mairena Sevilla

**Rama:** `emesis/coordinador-evidencias` · **Reflexión:** Preguntas 7 y 8

> **Orden de merge del equipo:** Diana → Franklin → Emesis → Jairo.  
> Haces merge **después de Franklin**. Antes de empezar: `git pull origin main` (debe traer el controlador y la Policy de Franklin). Si Franklin aún no hizo merge, espera.

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
git checkout -b emesis/coordinador-evidencias
```

- En PowerShell, `touch database/database.sqlite` es `New-Item database/database.sqlite -ItemType File`.
- Comprueba tu identidad de Git (debe ser **tu** usuario de GitHub, para que el commit quede a tu nombre):
  `git config user.name` y `git config user.email` (si están vacíos: `git config user.name "Tu Nombre"` y `git config user.email "tu-correo-de-github"`).
- Si ya clonaste antes, no vuelvas a clonar: `cd api-academica`, `git checkout main`, `git pull origin main` y luego crea tu rama.

## 2. Archivos a crear o modificar

### 1. Reemplazar `database/seeders/RolesYPermisosSeeder.php`

Agrega el rol `coordinador` con `estudiantes.ver` y `estudiantes.editar` (Actividad 3).

```php
<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesYPermisosSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permisos = [
            'estudiantes.ver',
            'estudiantes.crear',
            'estudiantes.editar',
            'estudiantes.eliminar',
        ];

        foreach ($permisos as $permiso) {
            Permission::firstOrCreate([
                'name' => $permiso,
                'guard_name' => 'web',
            ]);
        }

        $admin = Role::firstOrCreate(['name' => 'administrador', 'guard_name' => 'web']);
        $profesor = Role::firstOrCreate(['name' => 'profesor', 'guard_name' => 'web']);
        $coordinador = Role::firstOrCreate(['name' => 'coordinador', 'guard_name' => 'web']);
        $estudiante = Role::firstOrCreate(['name' => 'estudiante', 'guard_name' => 'web']);

        $admin->syncPermissions($permisos);

        $profesor->syncPermissions([
            'estudiantes.ver',
            'estudiantes.editar',
        ]);

        // Actividad 3: coordinador consulta y modifica (sujeto a ABAC en la Policy).
        $coordinador->syncPermissions([
            'estudiantes.ver',
            'estudiantes.editar',
        ]);

        $estudiante->syncPermissions([
            'estudiantes.ver',
        ]);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
```

### 2. Reemplazar `app/Policies/EstudiantePolicy.php`

El coordinador se trata como el profesor: ver y editar solo estudiantes de su carrera (RBAC + ABAC).

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

        // ABAC: profesor y coordinador (Actividad 3) necesitan el permiso RBAC
        // y pertenecer a la misma carrera.
        if ($user->hasAnyRole(['profesor', 'coordinador'])) {
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

### 3. Reemplazar `database/seeders/DatosPruebaSeeder.php`

Agrega el usuario `coordinador.iti@academica.test` (carrera ITI).

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
        $crearUsuario('coordinador.iti@academica.test', 'Coordinador de TI', 'coordinador', $sistemas);

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

### 4. Crear `entrega/API_Academica.postman_collection.json`

Colección de Postman con todas las pruebas (importar en Postman/Bruno).

```json
{
  "info": {
    "name": "API Académica - Autorización",
    "schema": "https://schema.getpostman.com/json/collections/v2.1.0/collection.json"
  },
  "variable": [
    {
      "key": "base_url",
      "value": "http://127.0.0.1:8000/api"
    },
    {
      "key": "t_admin",
      "value": ""
    },
    {
      "key": "t_piti",
      "value": ""
    },
    {
      "key": "t_pisw",
      "value": ""
    },
    {
      "key": "t_coord",
      "value": ""
    },
    {
      "key": "t_ana",
      "value": ""
    }
  ],
  "item": [
    {
      "name": "0. Login (guarda tokens)",
      "item": [
        {
          "name": "Login Admin",
          "request": {
            "method": "POST",
            "header": [
              {
                "key": "Accept",
                "value": "application/json"
              },
              {
                "key": "Content-Type",
                "value": "application/json"
              }
            ],
            "url": {
              "raw": "{{base_url}}/login",
              "host": [
                "{{base_url}}"
              ],
              "path": [
                "login"
              ]
            },
            "body": {
              "mode": "raw",
              "raw": "{\n  \"email\": \"admin@academica.test\",\n  \"password\": \"Password123!\"\n}",
              "options": {
                "raw": {
                  "language": "json"
                }
              }
            }
          },
          "event": [
            {
              "listen": "test",
              "script": {
                "type": "text/javascript",
                "exec": [
                  "pm.collectionVariables.set('t_admin', pm.response.json().token);"
                ]
              }
            }
          ]
        },
        {
          "name": "Login Profesor ITI",
          "request": {
            "method": "POST",
            "header": [
              {
                "key": "Accept",
                "value": "application/json"
              },
              {
                "key": "Content-Type",
                "value": "application/json"
              }
            ],
            "url": {
              "raw": "{{base_url}}/login",
              "host": [
                "{{base_url}}"
              ],
              "path": [
                "login"
              ]
            },
            "body": {
              "mode": "raw",
              "raw": "{\n  \"email\": \"profesor.iti@academica.test\",\n  \"password\": \"Password123!\"\n}",
              "options": {
                "raw": {
                  "language": "json"
                }
              }
            }
          },
          "event": [
            {
              "listen": "test",
              "script": {
                "type": "text/javascript",
                "exec": [
                  "pm.collectionVariables.set('t_piti', pm.response.json().token);"
                ]
              }
            }
          ]
        },
        {
          "name": "Login Profesor ISW",
          "request": {
            "method": "POST",
            "header": [
              {
                "key": "Accept",
                "value": "application/json"
              },
              {
                "key": "Content-Type",
                "value": "application/json"
              }
            ],
            "url": {
              "raw": "{{base_url}}/login",
              "host": [
                "{{base_url}}"
              ],
              "path": [
                "login"
              ]
            },
            "body": {
              "mode": "raw",
              "raw": "{\n  \"email\": \"profesor.isw@academica.test\",\n  \"password\": \"Password123!\"\n}",
              "options": {
                "raw": {
                  "language": "json"
                }
              }
            }
          },
          "event": [
            {
              "listen": "test",
              "script": {
                "type": "text/javascript",
                "exec": [
                  "pm.collectionVariables.set('t_pisw', pm.response.json().token);"
                ]
              }
            }
          ]
        },
        {
          "name": "Login Coordinador ITI",
          "request": {
            "method": "POST",
            "header": [
              {
                "key": "Accept",
                "value": "application/json"
              },
              {
                "key": "Content-Type",
                "value": "application/json"
              }
            ],
            "url": {
              "raw": "{{base_url}}/login",
              "host": [
                "{{base_url}}"
              ],
              "path": [
                "login"
              ]
            },
            "body": {
              "mode": "raw",
              "raw": "{\n  \"email\": \"coordinador.iti@academica.test\",\n  \"password\": \"Password123!\"\n}",
              "options": {
                "raw": {
                  "language": "json"
                }
              }
            }
          },
          "event": [
            {
              "listen": "test",
              "script": {
                "type": "text/javascript",
                "exec": [
                  "pm.collectionVariables.set('t_coord', pm.response.json().token);"
                ]
              }
            }
          ]
        },
        {
          "name": "Login Estudiante Ana",
          "request": {
            "method": "POST",
            "header": [
              {
                "key": "Accept",
                "value": "application/json"
              },
              {
                "key": "Content-Type",
                "value": "application/json"
              }
            ],
            "url": {
              "raw": "{{base_url}}/login",
              "host": [
                "{{base_url}}"
              ],
              "path": [
                "login"
              ]
            },
            "body": {
              "mode": "raw",
              "raw": "{\n  \"email\": \"estudiante1@academica.test\",\n  \"password\": \"Password123!\"\n}",
              "options": {
                "raw": {
                  "language": "json"
                }
              }
            }
          },
          "event": [
            {
              "listen": "test",
              "script": {
                "type": "text/javascript",
                "exec": [
                  "pm.collectionVariables.set('t_ana', pm.response.json().token);"
                ]
              }
            }
          ]
        }
      ]
    },
    {
      "name": "1. Autenticación",
      "item": [
        {
          "name": "Registro",
          "request": {
            "method": "POST",
            "header": [
              {
                "key": "Accept",
                "value": "application/json"
              },
              {
                "key": "Content-Type",
                "value": "application/json"
              }
            ],
            "url": {
              "raw": "{{base_url}}/register",
              "host": [
                "{{base_url}}"
              ],
              "path": [
                "register"
              ]
            },
            "body": {
              "mode": "raw",
              "raw": "{\n  \"name\": \"Nuevo\",\n  \"email\": \"nuevo@academica.test\",\n  \"password\": \"Password123!\",\n  \"password_confirmation\": \"Password123!\"\n}",
              "options": {
                "raw": {
                  "language": "json"
                }
              }
            }
          }
        },
        {
          "name": "Me (admin)",
          "request": {
            "method": "GET",
            "header": [
              {
                "key": "Accept",
                "value": "application/json"
              }
            ],
            "url": {
              "raw": "{{base_url}}/me",
              "host": [
                "{{base_url}}"
              ],
              "path": [
                "me"
              ]
            },
            "auth": {
              "type": "bearer",
              "bearer": [
                {
                  "key": "token",
                  "value": "{{t_admin}}",
                  "type": "string"
                }
              ]
            }
          }
        },
        {
          "name": "Sin token -> 401",
          "request": {
            "method": "GET",
            "header": [
              {
                "key": "Accept",
                "value": "application/json"
              }
            ],
            "url": {
              "raw": "{{base_url}}/estudiantes",
              "host": [
                "{{base_url}}"
              ],
              "path": [
                "estudiantes"
              ]
            }
          }
        }
      ]
    },
    {
      "name": "2. Gates",
      "item": [
        {
          "name": "Admin dashboard admin 200",
          "request": {
            "method": "GET",
            "header": [
              {
                "key": "Accept",
                "value": "application/json"
              }
            ],
            "url": {
              "raw": "{{base_url}}/admin/dashboard",
              "host": [
                "{{base_url}}"
              ],
              "path": [
                "admin",
                "dashboard"
              ]
            },
            "auth": {
              "type": "bearer",
              "bearer": [
                {
                  "key": "token",
                  "value": "{{t_admin}}",
                  "type": "string"
                }
              ]
            }
          }
        },
        {
          "name": "Admin dashboard profesor 403",
          "request": {
            "method": "GET",
            "header": [
              {
                "key": "Accept",
                "value": "application/json"
              }
            ],
            "url": {
              "raw": "{{base_url}}/admin/dashboard",
              "host": [
                "{{base_url}}"
              ],
              "path": [
                "admin",
                "dashboard"
              ]
            },
            "auth": {
              "type": "bearer",
              "bearer": [
                {
                  "key": "token",
                  "value": "{{t_piti}}",
                  "type": "string"
                }
              ]
            }
          }
        },
        {
          "name": "Admin dashboard estudiante 403",
          "request": {
            "method": "GET",
            "header": [
              {
                "key": "Accept",
                "value": "application/json"
              }
            ],
            "url": {
              "raw": "{{base_url}}/admin/dashboard",
              "host": [
                "{{base_url}}"
              ],
              "path": [
                "admin",
                "dashboard"
              ]
            },
            "auth": {
              "type": "bearer",
              "bearer": [
                {
                  "key": "token",
                  "value": "{{t_ana}}",
                  "type": "string"
                }
              ]
            }
          }
        },
        {
          "name": "Profesor dashboard profesor 200",
          "request": {
            "method": "GET",
            "header": [
              {
                "key": "Accept",
                "value": "application/json"
              }
            ],
            "url": {
              "raw": "{{base_url}}/profesor/dashboard",
              "host": [
                "{{base_url}}"
              ],
              "path": [
                "profesor",
                "dashboard"
              ]
            },
            "auth": {
              "type": "bearer",
              "bearer": [
                {
                  "key": "token",
                  "value": "{{t_piti}}",
                  "type": "string"
                }
              ]
            }
          }
        },
        {
          "name": "Profesor dashboard admin 403",
          "request": {
            "method": "GET",
            "header": [
              {
                "key": "Accept",
                "value": "application/json"
              }
            ],
            "url": {
              "raw": "{{base_url}}/profesor/dashboard",
              "host": [
                "{{base_url}}"
              ],
              "path": [
                "profesor",
                "dashboard"
              ]
            },
            "auth": {
              "type": "bearer",
              "bearer": [
                {
                  "key": "token",
                  "value": "{{t_admin}}",
                  "type": "string"
                }
              ]
            }
          }
        },
        {
          "name": "Profesor dashboard estudiante 403",
          "request": {
            "method": "GET",
            "header": [
              {
                "key": "Accept",
                "value": "application/json"
              }
            ],
            "url": {
              "raw": "{{base_url}}/profesor/dashboard",
              "host": [
                "{{base_url}}"
              ],
              "path": [
                "profesor",
                "dashboard"
              ]
            },
            "auth": {
              "type": "bearer",
              "bearer": [
                {
                  "key": "token",
                  "value": "{{t_ana}}",
                  "type": "string"
                }
              ]
            }
          }
        }
      ]
    },
    {
      "name": "3. RBAC",
      "item": [
        {
          "name": "Crear estudiante (admin) 201",
          "request": {
            "method": "POST",
            "header": [
              {
                "key": "Accept",
                "value": "application/json"
              },
              {
                "key": "Content-Type",
                "value": "application/json"
              }
            ],
            "url": {
              "raw": "{{base_url}}/estudiantes",
              "host": [
                "{{base_url}}"
              ],
              "path": [
                "estudiantes"
              ]
            },
            "auth": {
              "type": "bearer",
              "bearer": [
                {
                  "key": "token",
                  "value": "{{t_admin}}",
                  "type": "string"
                }
              ]
            },
            "body": {
              "mode": "raw",
              "raw": "{\n  \"user_id\": 1,\n  \"carrera_id\": 1,\n  \"cedula\": \"3-3333-3333\",\n  \"nombre\": \"Carlos\",\n  \"apellido\": \"Ejemplo\",\n  \"correo_institucional\": \"carlos@estudiantes.test\",\n  \"activo\": true\n}",
              "options": {
                "raw": {
                  "language": "json"
                }
              }
            }
          }
        },
        {
          "name": "Crear estudiante (profesor) 403",
          "request": {
            "method": "POST",
            "header": [
              {
                "key": "Accept",
                "value": "application/json"
              },
              {
                "key": "Content-Type",
                "value": "application/json"
              }
            ],
            "url": {
              "raw": "{{base_url}}/estudiantes",
              "host": [
                "{{base_url}}"
              ],
              "path": [
                "estudiantes"
              ]
            },
            "auth": {
              "type": "bearer",
              "bearer": [
                {
                  "key": "token",
                  "value": "{{t_piti}}",
                  "type": "string"
                }
              ]
            },
            "body": {
              "mode": "raw",
              "raw": "{}",
              "options": {
                "raw": {
                  "language": "json"
                }
              }
            }
          }
        },
        {
          "name": "Listado (estudiante) 200",
          "request": {
            "method": "GET",
            "header": [
              {
                "key": "Accept",
                "value": "application/json"
              }
            ],
            "url": {
              "raw": "{{base_url}}/estudiantes",
              "host": [
                "{{base_url}}"
              ],
              "path": [
                "estudiantes"
              ]
            },
            "auth": {
              "type": "bearer",
              "bearer": [
                {
                  "key": "token",
                  "value": "{{t_ana}}",
                  "type": "string"
                }
              ]
            }
          }
        }
      ]
    },
    {
      "name": "4. Policies y ABAC",
      "item": [
        {
          "name": "Ana ve Ana 200",
          "request": {
            "method": "GET",
            "header": [
              {
                "key": "Accept",
                "value": "application/json"
              }
            ],
            "url": {
              "raw": "{{base_url}}/estudiantes/1",
              "host": [
                "{{base_url}}"
              ],
              "path": [
                "estudiantes",
                "1"
              ]
            },
            "auth": {
              "type": "bearer",
              "bearer": [
                {
                  "key": "token",
                  "value": "{{t_ana}}",
                  "type": "string"
                }
              ]
            }
          }
        },
        {
          "name": "Ana ve Luis 403",
          "request": {
            "method": "GET",
            "header": [
              {
                "key": "Accept",
                "value": "application/json"
              }
            ],
            "url": {
              "raw": "{{base_url}}/estudiantes/2",
              "host": [
                "{{base_url}}"
              ],
              "path": [
                "estudiantes",
                "2"
              ]
            },
            "auth": {
              "type": "bearer",
              "bearer": [
                {
                  "key": "token",
                  "value": "{{t_ana}}",
                  "type": "string"
                }
              ]
            }
          }
        },
        {
          "name": "Ana ve Marta inactiva 403",
          "request": {
            "method": "GET",
            "header": [
              {
                "key": "Accept",
                "value": "application/json"
              }
            ],
            "url": {
              "raw": "{{base_url}}/estudiantes/3",
              "host": [
                "{{base_url}}"
              ],
              "path": [
                "estudiantes",
                "3"
              ]
            },
            "auth": {
              "type": "bearer",
              "bearer": [
                {
                  "key": "token",
                  "value": "{{t_ana}}",
                  "type": "string"
                }
              ]
            }
          }
        },
        {
          "name": "Prof ITI ve Ana 200",
          "request": {
            "method": "GET",
            "header": [
              {
                "key": "Accept",
                "value": "application/json"
              }
            ],
            "url": {
              "raw": "{{base_url}}/estudiantes/1",
              "host": [
                "{{base_url}}"
              ],
              "path": [
                "estudiantes",
                "1"
              ]
            },
            "auth": {
              "type": "bearer",
              "bearer": [
                {
                  "key": "token",
                  "value": "{{t_piti}}",
                  "type": "string"
                }
              ]
            }
          }
        },
        {
          "name": "Prof ITI ve Luis 403",
          "request": {
            "method": "GET",
            "header": [
              {
                "key": "Accept",
                "value": "application/json"
              }
            ],
            "url": {
              "raw": "{{base_url}}/estudiantes/2",
              "host": [
                "{{base_url}}"
              ],
              "path": [
                "estudiantes",
                "2"
              ]
            },
            "auth": {
              "type": "bearer",
              "bearer": [
                {
                  "key": "token",
                  "value": "{{t_piti}}",
                  "type": "string"
                }
              ]
            }
          }
        },
        {
          "name": "Prof ITI edita Ana 200",
          "request": {
            "method": "PUT",
            "header": [
              {
                "key": "Accept",
                "value": "application/json"
              },
              {
                "key": "Content-Type",
                "value": "application/json"
              }
            ],
            "url": {
              "raw": "{{base_url}}/estudiantes/1",
              "host": [
                "{{base_url}}"
              ],
              "path": [
                "estudiantes",
                "1"
              ]
            },
            "auth": {
              "type": "bearer",
              "bearer": [
                {
                  "key": "token",
                  "value": "{{t_piti}}",
                  "type": "string"
                }
              ]
            },
            "body": {
              "mode": "raw",
              "raw": "{\n  \"nombre\": \"Ana María\",\n  \"apellido\": \"Estudiante\"\n}",
              "options": {
                "raw": {
                  "language": "json"
                }
              }
            }
          }
        },
        {
          "name": "Prof ITI edita Luis 403",
          "request": {
            "method": "PUT",
            "header": [
              {
                "key": "Accept",
                "value": "application/json"
              },
              {
                "key": "Content-Type",
                "value": "application/json"
              }
            ],
            "url": {
              "raw": "{{base_url}}/estudiantes/2",
              "host": [
                "{{base_url}}"
              ],
              "path": [
                "estudiantes",
                "2"
              ]
            },
            "auth": {
              "type": "bearer",
              "bearer": [
                {
                  "key": "token",
                  "value": "{{t_piti}}",
                  "type": "string"
                }
              ]
            },
            "body": {
              "mode": "raw",
              "raw": "{\n  \"nombre\": \"Ana María\",\n  \"apellido\": \"Estudiante\"\n}",
              "options": {
                "raw": {
                  "language": "json"
                }
              }
            }
          }
        },
        {
          "name": "Prof ISW edita Ana 403",
          "request": {
            "method": "PUT",
            "header": [
              {
                "key": "Accept",
                "value": "application/json"
              },
              {
                "key": "Content-Type",
                "value": "application/json"
              }
            ],
            "url": {
              "raw": "{{base_url}}/estudiantes/1",
              "host": [
                "{{base_url}}"
              ],
              "path": [
                "estudiantes",
                "1"
              ]
            },
            "auth": {
              "type": "bearer",
              "bearer": [
                {
                  "key": "token",
                  "value": "{{t_pisw}}",
                  "type": "string"
                }
              ]
            },
            "body": {
              "mode": "raw",
              "raw": "{\n  \"nombre\": \"X\"\n}",
              "options": {
                "raw": {
                  "language": "json"
                }
              }
            }
          }
        },
        {
          "name": "Ana edita Ana 403",
          "request": {
            "method": "PUT",
            "header": [
              {
                "key": "Accept",
                "value": "application/json"
              },
              {
                "key": "Content-Type",
                "value": "application/json"
              }
            ],
            "url": {
              "raw": "{{base_url}}/estudiantes/1",
              "host": [
                "{{base_url}}"
              ],
              "path": [
                "estudiantes",
                "1"
              ]
            },
            "auth": {
              "type": "bearer",
              "bearer": [
                {
                  "key": "token",
                  "value": "{{t_ana}}",
                  "type": "string"
                }
              ]
            },
            "body": {
              "mode": "raw",
              "raw": "{\n  \"nombre\": \"X\"\n}",
              "options": {
                "raw": {
                  "language": "json"
                }
              }
            }
          }
        }
      ]
    },
    {
      "name": "5. Coordinador (Actividad 3)",
      "item": [
        {
          "name": "Coordinador edita Ana 200",
          "request": {
            "method": "PUT",
            "header": [
              {
                "key": "Accept",
                "value": "application/json"
              },
              {
                "key": "Content-Type",
                "value": "application/json"
              }
            ],
            "url": {
              "raw": "{{base_url}}/estudiantes/1",
              "host": [
                "{{base_url}}"
              ],
              "path": [
                "estudiantes",
                "1"
              ]
            },
            "auth": {
              "type": "bearer",
              "bearer": [
                {
                  "key": "token",
                  "value": "{{t_coord}}",
                  "type": "string"
                }
              ]
            },
            "body": {
              "mode": "raw",
              "raw": "{\n  \"nombre\": \"Ana\"\n}",
              "options": {
                "raw": {
                  "language": "json"
                }
              }
            }
          }
        },
        {
          "name": "Coordinador edita Luis 403",
          "request": {
            "method": "PUT",
            "header": [
              {
                "key": "Accept",
                "value": "application/json"
              },
              {
                "key": "Content-Type",
                "value": "application/json"
              }
            ],
            "url": {
              "raw": "{{base_url}}/estudiantes/2",
              "host": [
                "{{base_url}}"
              ],
              "path": [
                "estudiantes",
                "2"
              ]
            },
            "auth": {
              "type": "bearer",
              "bearer": [
                {
                  "key": "token",
                  "value": "{{t_coord}}",
                  "type": "string"
                }
              ]
            },
            "body": {
              "mode": "raw",
              "raw": "{\n  \"nombre\": \"X\"\n}",
              "options": {
                "raw": {
                  "language": "json"
                }
              }
            }
          }
        },
        {
          "name": "Coordinador elimina Ana 403",
          "request": {
            "method": "DELETE",
            "header": [
              {
                "key": "Accept",
                "value": "application/json"
              }
            ],
            "url": {
              "raw": "{{base_url}}/estudiantes/1",
              "host": [
                "{{base_url}}"
              ],
              "path": [
                "estudiantes",
                "1"
              ]
            },
            "auth": {
              "type": "bearer",
              "bearer": [
                {
                  "key": "token",
                  "value": "{{t_coord}}",
                  "type": "string"
                }
              ]
            }
          }
        }
      ]
    },
    {
      "name": "6. Eliminación",
      "item": [
        {
          "name": "Admin elimina Ana 200",
          "request": {
            "method": "DELETE",
            "header": [
              {
                "key": "Accept",
                "value": "application/json"
              }
            ],
            "url": {
              "raw": "{{base_url}}/estudiantes/1",
              "host": [
                "{{base_url}}"
              ],
              "path": [
                "estudiantes",
                "1"
              ]
            },
            "auth": {
              "type": "bearer",
              "bearer": [
                {
                  "key": "token",
                  "value": "{{t_admin}}",
                  "type": "string"
                }
              ]
            }
          }
        }
      ]
    }
  ]
}
```

### 5. Crear `entrega/evidencias_http.md`

Evidencia de los códigos HTTP reales de las pruebas.

```markdown
# Evidencias de pruebas HTTP (estado: base + Gates + Policy + coordinador)

Resultados reales obtenidos con `php artisan serve` y la base sembrada (`migrate:fresh --seed`).

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
```

### 6. Reflexión: `entrega/Reflexion_Seguridad.md` (Preguntas 7 y 8)

Abre el archivo y reemplaza **solo** la línea `_Pendiente: ..._` que está entre `<!-- INICIO EMESIS ... -->` y `<!-- FIN EMESIS -->` por el texto siguiente (no borres los marcadores ni toques las secciones de los demás). Puedes mejorar la redacción con tus propias palabras.

```markdown
**7. ¿Por qué la autorización debe realizarse en el backend?** El cliente (interfaz, Postman, curl) está bajo control del atacante: ocultar un botón no impide llamar al endpoint. Solo el servidor puede imponer las reglas de forma fiable.

**8. ¿Qué diferencia existe entre HTTP 401 y HTTP 403?** `401 Unauthorized`: no hay una identidad válida (falta el token o es inválido); se resuelve autenticándose. `403 Forbidden`: la identidad es válida pero no tiene permiso; volver a autenticarse no cambia el resultado.
```

## 3. Cómo probarlo

Reconstruye la base: `php artisan migrate:fresh --seed`.

**1) Permisos del coordinador (evidencia de la Actividad 3)** en Tinker:

```bash
php artisan tinker
```
```php
$u = App\Models\User::where('email', 'coordinador.iti@academica.test')->first();
$u->getRoleNames();                              // ["coordinador"]
$u->getAllPermissions()->pluck('name');          // estudiantes.ver, estudiantes.editar
$u->can('estudiantes.crear');                    // false
$u->can('estudiantes.eliminar');                 // false
exit
```

**2) Endpoints** (usa el procedimiento de abajo con `coordinador.iti@academica.test`):

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

| Usuario | Operación | Registro | HTTP esperado |
|---|---|---|---|
| coordinador.iti | PUT (`{"nombre":"Ana"}`) | 1 (Ana, ITI) | **200** |
| coordinador.iti | PUT | 2 (Luis, ISW) | **403** |
| coordinador.iti | DELETE | 1 | **403** |
| coordinador.iti | GET | 1 | **200** |

**3) Postman:** importa `entrega/API_Academica.postman_collection.json` (Import → archivo). Ejecuta primero la carpeta **0. Login** (guarda los tokens) y luego las demás. Ajusta `base_url` si usas otro puerto. Los resultados esperados están en el nombre de cada request (200/403/401...).
Entre las pruebas de Postman, vuelve a ejecutar `php artisan migrate:fresh --seed` si borraste a Ana (la última carpeta elimina el registro 1).

El archivo `entrega/evidencias_http.md` ya trae la tabla con los resultados obtenidos con este mismo código; si quieres, repite algunas pruebas y verifica que coincidan.

## 4. Commit, push y Pull Request

Revisa que solo hayas tocado tus archivos:

```bash
git status
git diff --stat
```

Sube tu parte:

```bash
git add database/seeders/RolesYPermisosSeeder.php app/Policies/EstudiantePolicy.php database/seeders/DatosPruebaSeeder.php entrega/API_Academica.postman_collection.json entrega/evidencias_http.md entrega/Reflexion_Seguridad.md
git commit -m "feat: rol coordinador, colección Postman y evidencias HTTP (Actividad 3)"
git push -u origin emesis/coordinador-evidencias
```

Abre el Pull Request hacia `main` (con GitHub CLI o desde la página del repositorio, botón *Compare & pull request*):

```bash
gh pr create --base main --head emesis/coordinador-evidencias --title "feat: rol coordinador, Postman y evidencias - Actividad 3" --body "Agrega el rol coordinador (seeder y Policy), el usuario de prueba, la colección Postman, evidencias_http.md y mi sección de la reflexión (7 y 8)."
```

Avisa en el grupo cuando abras el PR. Joseph revisa y hace el merge respetando el orden **Diana → Franklin → Emesis → Jairo**.
Si al abrir el PR GitHub marca conflictos, es porque falta hacer `git pull origin main` con los merges anteriores: no resuelvas a ciegas, avisa en el grupo.
