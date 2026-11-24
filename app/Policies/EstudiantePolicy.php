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
