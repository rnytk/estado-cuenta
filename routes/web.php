<?php

use App\Http\Controllers\EstadoCuentaController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('estado-cuenta');
});

Route::get('/api/asociados/buscar', [EstadoCuentaController::class, 'findAssociate'])
    ->name('asociados.buscar');

Route::get('/api/asociados/{codigo}/creditos', [EstadoCuentaController::class, 'creditsForAssociate'])
    ->where('codigo', '\d+')
    ->name('asociados.creditos');

Route::get('/api/creditos/{codigo}/estado-cuenta', [EstadoCuentaController::class, 'show'])
    ->where('codigo', '[A-Za-z0-9-]+')
    ->name('estado-cuenta.show');

Route::get('/creditos/{codigo}/estado-cuenta.pdf', [EstadoCuentaController::class, 'download'])
    ->where('codigo', '[A-Za-z0-9-]+')
    ->name('estado-cuenta.download');

Route::get('/api/ahorros/{codigo}/estado-cuenta', [EstadoCuentaController::class, 'savingsStatement'])
    ->where('codigo', '\d+')
    ->name('ahorros.estado-cuenta.show');

Route::get('/ahorros/{codigo}/estado-cuenta.pdf', [EstadoCuentaController::class, 'downloadSavingsStatement'])
    ->where('codigo', '\d+')
    ->name('ahorros.estado-cuenta.download');
