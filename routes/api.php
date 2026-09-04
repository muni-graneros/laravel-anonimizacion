<?php

use Anonimizacion\Http\AnonimizacionController;
use Anonimizacion\Http\ForzarJson;
use Anonimizacion\Http\VerificarTokenDeServicio;
use Illuminate\Support\Facades\Route;

// `ForzarJson` va en TODO el grupo, incluido /health: sin él, un fallo de
// validación redirige (302) en vez de responder 422, y los consumidores de esta
// API -n8n, curl, los micros de Python- mandan `Accept: */*`. Ver el docblock
// del middleware.
Route::prefix('anonimizacion')->middleware(ForzarJson::class)->group(function () {
    Route::get('health', [AnonimizacionController::class, 'health']);

    Route::middleware([VerificarTokenDeServicio::class, 'throttle:anonimizacion'])->group(function () {
        Route::post('amordazar', [AnonimizacionController::class, 'amordazar']);
        Route::post('restaurar', [AnonimizacionController::class, 'restaurar']);
        // Con token a propósito: los nombres de los consumidores y su
        // volumen son información de negocio, no algo que deba quedar
        // abierto. Prometheus puede enviar el header igual que cualquiera.
        Route::get('metrics', [AnonimizacionController::class, 'metrics']);
    });
});
