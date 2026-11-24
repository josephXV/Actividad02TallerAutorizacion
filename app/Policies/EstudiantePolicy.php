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
