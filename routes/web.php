<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ClienteController;
use App\Http\Controllers\EmpresaController;
use App\Http\Controllers\OrcamentoPublicoController;
use App\Http\Controllers\OrdemServicoController;
use App\Http\Controllers\OrdemServicoFotoController;
use App\Http\Controllers\OrdemServicoItemController;
use App\Http\Controllers\PainelController;
use App\Http\Controllers\RecepcaoController;
use App\Http\Controllers\ServicoController;
use App\Http\Controllers\UsuarioController;
use App\Http\Controllers\VeiculoController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/entrar', [AuthController::class, 'form'])->name('login');
    Route::post('/entrar', [AuthController::class, 'entrar'])->middleware('throttle:login')->name('login.entrar');
});

// Orçamento que o cliente abre pelo link do WhatsApp (sem login)
Route::middleware('throttle:orcamento')->group(function () {
    Route::get('/orcamento/{token}', [OrcamentoPublicoController::class, 'ver'])->name('orcamento.ver');
    Route::post('/orcamento/{token}', [OrcamentoPublicoController::class, 'aprovar'])->middleware('throttle:orcamento-aprovar')->name('orcamento.aprovar');
    Route::get('/orcamento/{token}/foto/{foto}', [OrcamentoPublicoController::class, 'foto'])->whereNumber('foto')->name('orcamento.foto');
});

Route::middleware('auth')->group(function () {
    Route::post('/sair', [AuthController::class, 'sair'])->name('logout');
    Route::get('/', PainelController::class)->name('painel');

    // Recepção por placa
    Route::get('/recepcao', [RecepcaoController::class, 'index'])->name('recepcao');
    Route::get('/recepcao/placa', [RecepcaoController::class, 'buscar'])->middleware('throttle:placa')->name('recepcao.buscar');

    Route::resource('clientes', ClienteController::class);

    Route::resource('veiculos', VeiculoController::class);
    Route::post('/veiculos/{veiculo}/transferir', [VeiculoController::class, 'transferir'])->name('veiculos.transferir');

    // Ordens de serviço
    Route::resource('os', OrdemServicoController::class)->parameters(['os' => 'os'])->except(['destroy']);
    Route::post('/os/{os}/acao/{acao}', [OrdemServicoController::class, 'acao'])->whereIn('acao', ['enviar', 'aprovar', 'iniciar', 'concluir', 'entregar', 'cancelar'])->name('os.acao');
    Route::put('/os/{os}/diagnostico', [OrdemServicoController::class, 'diagnostico'])->name('os.diagnostico');
    Route::put('/os/{os}/desconto', [OrdemServicoController::class, 'desconto'])->name('os.desconto');
    Route::get('/os/{os}/imprimir', [OrdemServicoController::class, 'imprimir'])->name('os.imprimir');
    Route::post('/os/{os}/itens', [OrdemServicoItemController::class, 'store'])->name('os.itens.store');
    Route::delete('/os/{os}/itens/{item}', [OrdemServicoItemController::class, 'destroy'])->name('os.itens.destroy');
    Route::post('/os/{os}/fotos', [OrdemServicoFotoController::class, 'store'])->name('os.fotos.store');
    Route::get('/os/{os}/fotos/{foto}', [OrdemServicoFotoController::class, 'show'])->name('os.fotos.show');
    Route::delete('/os/{os}/fotos/{foto}', [OrdemServicoFotoController::class, 'destroy'])->name('os.fotos.destroy');

    Route::resource('servicos', ServicoController::class)->only(['index', 'store', 'update', 'destroy']);

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
