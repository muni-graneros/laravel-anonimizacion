<?php

use Anonimizacion\Http\AnonimizacionController;
use Anonimizacion\Http\VerificarTokenDeServicio;
use Illuminate\Support\Facades\Route;

Route::prefix('anonimizacion')->group(function () {
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
