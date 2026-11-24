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

    // Rutas explicitas para aplicar el permiso RBAC de creacion por endpoint.
    Route::get('/estudiantes', [EstudianteController::class, 'index']);
    Route::post('/estudiantes', [EstudianteController::class, 'store'])
        ->middleware('permission:estudiantes.crear');
    Route::get('/estudiantes/{estudiante}', [EstudianteController::class, 'show']);
    Route::put('/estudiantes/{estudiante}', [EstudianteController::class, 'update']);
    Route::patch('/estudiantes/{estudiante}', [EstudianteController::class, 'update']);
    Route::delete('/estudiantes/{estudiante}', [EstudianteController::class, 'destroy']);

});
