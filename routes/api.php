<?php

use Anonimizacion\Http\AnonimizacionController;
use Anonimizacion\Http\VerificarTokenDeServicio;
use Illuminate\Support\Facades\Route;

Route::prefix('anonimizacion')->group(function () {
    Route::get('health', [AnonimizacionController::class, 'health']);

    Route::middleware([VerificarTokenDeServicio::class, 'throttle:60,1'])->group(function () {
        Route::post('amordazar', [AnonimizacionController::class, 'amordazar']);
        Route::post('restaurar', [AnonimizacionController::class, 'restaurar']);
    });
});
