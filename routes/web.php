<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ClienteController;
use App\Http\Controllers\EmpresaController;
use App\Http\Controllers\PainelController;
use App\Http\Controllers\RecepcaoController;
use App\Http\Controllers\UsuarioController;
use App\Http\Controllers\VeiculoController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/entrar', [AuthController::class, 'form'])->name('login');
    Route::post('/entrar', [AuthController::class, 'entrar'])->middleware('throttle:6,1')->name('login.entrar');
});

Route::middleware('auth')->group(function () {
    Route::post('/sair', [AuthController::class, 'sair'])->name('logout');
    Route::get('/', PainelController::class)->name('painel');

    // Recepção por placa
    Route::get('/recepcao', [RecepcaoController::class, 'index'])->name('recepcao');
    Route::get('/recepcao/placa', [RecepcaoController::class, 'buscar'])->middleware('throttle:30,1')->name('recepcao.buscar');

    Route::resource('clientes', ClienteController::class);

    Route::resource('veiculos', VeiculoController::class);
    Route::post('/veiculos/{veiculo}/transferir', [VeiculoController::class, 'transferir'])->name('veiculos.transferir');

    Route::middleware('can:usuarios.gerenciar')->group(function () {
        Route::resource('usuarios', UsuarioController::class)->except(['show', 'destroy']);
    });

    Route::middleware('can:empresa.configurar')->group(function () {
        Route::get('/empresa', [EmpresaController::class, 'edit'])->name('empresa.edit');
        Route::put('/empresa', [EmpresaController::class, 'update'])->name('empresa.update');
    });

    Route::get('/conta/senha', [AuthController::class, 'senhaForm'])->name('senha');
    Route::put('/conta/senha', [AuthController::class, 'senhaSalvar'])->name('senha.salvar');
});
