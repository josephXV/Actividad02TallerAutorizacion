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
