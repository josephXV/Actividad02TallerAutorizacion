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
