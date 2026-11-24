<?php

use App\Http\Controllers\AgendaController;
use App\Http\Controllers\CaixaController;
use App\Http\Controllers\ComissaoController;
use App\Http\Controllers\CompraController;
use App\Http\Controllers\ContaPagarController;
use App\Http\Controllers\ContaReceberController;
use App\Http\Controllers\FornecedorController;
use App\Http\Controllers\LembreteController;
use App\Http\Controllers\NotaFiscalController;
use App\Http\Controllers\ProdutoController;
use App\Http\Controllers\RecebimentoController;
use App\Http\Controllers\RelatorioController;
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

// Avisos do gateway de cobrança (sem login; conferidos pelo token)
Route::post('/webhooks/asaas', [\App\Http\Controllers\WebhookCobrancaController::class, 'asaas'])->middleware('throttle:120,1')->name('webhooks.asaas');
Route::view('/instalar', 'instalar')->name('instalar');

Route::middleware('guest')->group(function () {
    Route::get('/cadastro', [\App\Http\Controllers\CadastroController::class, 'form'])->name('cadastro');
    Route::post('/cadastro', [\App\Http\Controllers\CadastroController::class, 'store'])->middleware('throttle:5,10')->name('cadastro.store');
    Route::get('/entrar', [AuthController::class, 'form'])->name('login');
    Route::post('/entrar', [AuthController::class, 'entrar'])->middleware('throttle:login')->name('login.entrar');
    Route::get('/entrar/codigo', [\App\Http\Controllers\DoisFatoresController::class, 'form'])->name('login.codigo');
    Route::post('/entrar/codigo', [\App\Http\Controllers\DoisFatoresController::class, 'verificar'])->middleware('throttle:login')->name('login.codigo.verificar');
    Route::get('/esqueci-senha', [\App\Http\Controllers\RecuperarSenhaController::class, 'form'])->name('password.request');
    Route::post('/esqueci-senha', [\App\Http\Controllers\RecuperarSenhaController::class, 'enviar'])->middleware('throttle:login')->name('password.email');
    Route::get('/redefinir-senha/{token}', [\App\Http\Controllers\RecuperarSenhaController::class, 'redefinirForm'])->name('password.reset');
    Route::post('/redefinir-senha', [\App\Http\Controllers\RecuperarSenhaController::class, 'redefinir'])->middleware('throttle:login')->name('password.update');
});

Route::get('/cliente/{token}', [\App\Http\Controllers\PortalClienteController::class, 'ver'])->middleware('throttle:orcamento')->name('portal.ver');
Route::post('/cliente/{token}/agendamento', [\App\Http\Controllers\PortalClienteController::class, 'solicitar'])->middleware('throttle:orcamento')->name('portal.solicitar');
Route::post('/cliente/{token}/agendamento/{solicitacao}/responder', [\App\Http\Controllers\PortalClienteController::class, 'responderProposta'])->whereNumber('solicitacao')->middleware('throttle:orcamento')->name('portal.proposta.responder');

// Orçamento que o cliente abre pelo link do WhatsApp (sem login)
Route::middleware('throttle:orcamento')->group(function () {
    Route::get('/orcamento/{token}', [OrcamentoPublicoController::class, 'ver'])->name('orcamento.ver');
    Route::post('/orcamento/{token}', [OrcamentoPublicoController::class, 'aprovar'])->middleware('throttle:orcamento-aprovar')->name('orcamento.aprovar');
    Route::get('/orcamento/{token}/foto/{foto}', [OrcamentoPublicoController::class, 'foto'])->whereNumber('foto')->name('orcamento.foto');
});

Route::middleware('auth')->group(function () {
    Route::get('/financeiro/pix', [\App\Http\Controllers\PixController::class, 'index'])->middleware('modulo:bancos')->name('pix.index');
    Route::post('/financeiro/pix', [\App\Http\Controllers\PixController::class, 'store'])->middleware('throttle:10,1')->middleware('modulo:bancos')->name('pix.store');
    Route::post('/financeiro/pix/{cobranca}/cancelar-rascunho', [\App\Http\Controllers\PixController::class, 'cancelarRascunho'])->middleware('throttle:10,1')->middleware('modulo:bancos')->name('pix.cancelar-rascunho');
    Route::post('/financeiro/pix/{cobranca}/confirmar-manual', [\App\Http\Controllers\PixController::class, 'confirmarManual'])->middleware('throttle:10,1')->middleware('modulo:bancos')->name('pix.confirmar-manual');
    Route::get('/financeiro/bancos', [\App\Http\Controllers\BancoController::class, 'index'])->middleware('modulo:bancos')->name('bancos.index');
    Route::post('/financeiro/bancos', [\App\Http\Controllers\BancoController::class, 'store'])->middleware('modulo:bancos')->name('bancos.store');
    Route::put('/financeiro/bancos/{conta}/pix', [\App\Http\Controllers\BancoController::class, 'configurarPix'])->middleware('modulo:bancos')->name('bancos.pix');
    Route::post('/financeiro/bancos/{conta}/movimentos', [\App\Http\Controllers\BancoController::class, 'movimento'])->middleware('modulo:bancos')->name('bancos.movimento');
    Route::post('/financeiro/bancos/{conta}/importar-ofx', [\App\Http\Controllers\BancoController::class, 'importarOfx'])->middleware('modulo:bancos')->name('bancos.importar.ofx');
    Route::post('/financeiro/bancos/movimentos/{movimento}/conciliar', [\App\Http\Controllers\BancoController::class, 'conciliar'])->middleware('modulo:bancos')->name('bancos.conciliar');
    Route::post('/financeiro/bancos/movimentos/{movimento}/desfazer', [\App\Http\Controllers\BancoController::class, 'desfazerConciliacao'])->middleware('modulo:bancos')->name('bancos.desfazer');
    Route::post('/financeiro/bancos/{conta}/importar-csv', [\App\Http\Controllers\BancoController::class, 'importarCsv'])->middleware('modulo:bancos')->name('bancos.importar');

    Route::post('/sair', [AuthController::class, 'sair'])->name('logout');
    Route::get('/', PainelController::class)->name('painel');

    // Recepção por placa
    Route::get('/recepcao', [RecepcaoController::class, 'index'])->name('recepcao');
    Route::view('/tutorial', 'tutorial')->name('tutorial');
    Route::get('/condutores/cnh', [\App\Http\Controllers\ConferenciaCnhController::class, 'index'])->middleware('modulo:condutores')->name('cnh.index');
    Route::prefix('configuracoes/integracoes/cnh')->name('cnh.integracoes.')->middleware('modulo:condutores')->group(function () {
        Route::get('/', [\App\Http\Controllers\CnhIntegracaoController::class, 'index'])->name('index');
        Route::put('/retencao', [\App\Http\Controllers\CnhIntegracaoController::class, 'salvarRetencao'])->name('retencao');
        Route::put('/{provedor}', [\App\Http\Controllers\CnhIntegracaoController::class, 'salvar'])->name('salvar');
        Route::post('/{provedor}/testar', [\App\Http\Controllers\CnhIntegracaoController::class, 'testar'])->middleware('throttle:5,1')->name('testar');
        Route::post('/{provedor}/estado', [\App\Http\Controllers\CnhIntegracaoController::class, 'estado'])->name('estado');
    });
    Route::post('/condutores/cnh', [\App\Http\Controllers\ConferenciaCnhController::class, 'store'])->middleware('modulo:condutores')->middleware('throttle:20,1')->name('cnh.store');
    Route::post('/condutores/cnh/consultar', [\App\Http\Controllers\ConferenciaCnhController::class, 'consultar'])->middleware('modulo:condutores')->middleware('throttle:5,1')->name('cnh.consultar');
    Route::prefix('mobilidade')->name('mobilidade.')->group(function () {
        Route::get('/', [\App\Http\Controllers\MobilidadeController::class, 'index'])->name('index');
        Route::post('/condutores', [\App\Http\Controllers\MobilidadeController::class, 'condutor'])->name('condutor.store');
        Route::get('/condutores/{condutor}', [\App\Http\Controllers\MobilidadeController::class, 'mostrarCondutor'])->name('condutor.show');
        Route::put('/condutores/{condutor}', [\App\Http\Controllers\MobilidadeController::class, 'condutor'])->name('condutor.update');
        Route::post('/condutores/{condutor}/verificar', [\App\Http\Controllers\MobilidadeController::class, 'verificar'])->name('condutor.verificar');
        Route::post('/condutores/{condutor}/consultar', [\App\Http\Controllers\CnhConsultaController::class, 'store'])->middleware('throttle:5,1')->name('condutor.consultar');
        Route::post('/os/{os}/retirada', [\App\Http\Controllers\MobilidadeController::class, 'retirada'])->name('retirada.store');
        Route::put('/politica', [\App\Http\Controllers\MobilidadeController::class, 'politica'])->name('politica');
        Route::get('/documentos', [\App\Http\Controllers\MobilidadeDocumentoController::class, 'index'])->name('documentos');
        Route::put('/modelos/{tipo}', [\App\Http\Controllers\MobilidadeDocumentoController::class, 'modelo'])->name('modelo');
        Route::post('/documentos', [\App\Http\Controllers\MobilidadeDocumentoController::class, 'gerar'])->name('documento.gerar');
        Route::get('/documentos/{documento}', [\App\Http\Controllers\MobilidadeDocumentoController::class, 'mostrar'])->name('documento');
        Route::post('/fotos', [\App\Http\Controllers\MobilidadeDocumentoController::class, 'anexar'])->name('foto.store');
        Route::get('/fotos/{foto}', [\App\Http\Controllers\MobilidadeDocumentoController::class, 'foto'])->name('foto');
    });
    // Locação (módulo ligado por empresa): carro reserva ligado à OS e aluguel sem OS.
    Route::prefix('locacao')->name('locacao.')->group(function () {
        Route::get('/', [\App\Http\Controllers\LocacaoController::class, 'index'])->name('index');
        Route::post('/frota', [\App\Http\Controllers\LocacaoController::class, 'frota'])->name('frota.store');
        Route::put('/frota/{frota}', [\App\Http\Controllers\LocacaoController::class, 'frota'])->name('frota.update');
        Route::post('/reservas', [\App\Http\Controllers\LocacaoController::class, 'reservar'])->name('reserva.store');
        Route::post('/reservas/{reserva}/{acao}', [\App\Http\Controllers\LocacaoController::class, 'movimentar'])
            ->whereIn('acao', ['entregar', 'devolver', 'prorrogar', 'cancelar'])->name('reserva.acao');
    });
    Route::get('/recepcao/placa', [RecepcaoController::class, 'buscar'])->middleware('throttle:placa')->name('recepcao.buscar');

    Route::resource('clientes', ClienteController::class);
    Route::post('/clientes/{cliente}/portal', [\App\Http\Controllers\PortalClienteController::class, 'gerar'])->name('clientes.portal');
    Route::delete('/clientes/{cliente}/portal', [\App\Http\Controllers\PortalClienteController::class, 'revogar'])->name('clientes.portal.revogar');
    Route::get('/financeiro/exportar/{tipo}', \App\Http\Controllers\ExportacaoController::class)->whereIn('tipo', ['os', 'receber', 'pagar'])->name('exportar');

    Route::get('/veiculos/{veiculo}/historico-manutencao', \App\Http\Controllers\HistoricoManutencaoController::class)->name('veiculos.historico-manutencao');
    Route::get('/veiculos/{veiculo}/historico-manutencao.csv', [\App\Http\Controllers\HistoricoManutencaoController::class, 'exportarCsv'])->middleware('throttle:30,1')->name('veiculos.historico-manutencao.csv');
    Route::get('/veiculos/{veiculo}/autocenter', [\App\Http\Controllers\AutocenterServicoController::class, 'index'])->name('veiculos.autocenter.index');
    Route::post('/veiculos/{veiculo}/autocenter', [\App\Http\Controllers\AutocenterServicoController::class, 'store'])->middleware('throttle:30,1')->name('veiculos.autocenter.store');
    Route::get('/veiculos/{veiculo}/pneus', [\App\Http\Controllers\PneuInspecaoController::class, 'index'])->name('veiculos.pneus.index');
    Route::post('/veiculos/{veiculo}/pneus', [\App\Http\Controllers\PneuInspecaoController::class, 'store'])->middleware('throttle:30,1')->name('veiculos.pneus.store');
    Route::resource('veiculos', VeiculoController::class);
    Route::post('/veiculos/{veiculo}/transferir', [VeiculoController::class, 'transferir'])->name('veiculos.transferir');

    // Ordens de serviço
    Route::resource('os', OrdemServicoController::class)->parameters(['os' => 'os'])->except(['destroy']);
    Route::post('/os/{os}/acao/{acao}', [OrdemServicoController::class, 'acao'])->whereIn('acao', ['enviar', 'aprovar', 'iniciar', 'concluir', 'entregar', 'cancelar', 'novo-link', 'reabrir'])->name('os.acao');
    Route::put('/os/{os}/diagnostico', [OrdemServicoController::class, 'diagnostico'])->name('os.diagnostico');
    Route::put('/os/{os}/desconto', [OrdemServicoController::class, 'desconto'])->name('os.desconto');
    Route::get('/os/{os}/imprimir', [OrdemServicoController::class, 'imprimir'])->name('os.imprimir');
    Route::post('/os/{os}/conversa', [\App\Http\Controllers\OsMensagemController::class, 'store'])->middleware('throttle:30,1')->name('os.mensagens.store');
    Route::post('/os/{os}/itens', [OrdemServicoItemController::class, 'store'])->name('os.itens.store');
    Route::post('/os/{os}/modelos/{modelo}', [\App\Http\Controllers\ModeloOsController::class, 'aplicar'])->name('os.modelo.aplicar');
    Route::put('/os/{os}/itens/{item}', [OrdemServicoItemController::class, 'update'])->name('os.itens.update');
    Route::delete('/os/{os}/itens/{item}', [OrdemServicoItemController::class, 'destroy'])->name('os.itens.destroy');
    Route::post('/os/{os}/fotos', [OrdemServicoFotoController::class, 'store'])->name('os.fotos.store');
    Route::post('/os/{os}/inspecao', [\App\Http\Controllers\OrdemServicoInspecaoController::class, 'store'])->name('os.inspecao.store');
    Route::put('/os/{os}/laudo-alinhamento', [\App\Http\Controllers\OrdemServicoLaudoController::class, 'update'])->name('os.laudo.update');
    Route::put('/os/{os}/compra-peca', [\App\Http\Controllers\OsOperacaoController::class, 'compra'])->name('os.compra-peca');
    Route::get('/os/{os}/compra-peca/{item}', [\App\Http\Controllers\OsOperacaoController::class, 'pedido'])->whereNumber('item')->name('os.pedido-peca');
    Route::post('/os/{os}/terceirizados', [\App\Http\Controllers\OsOperacaoController::class, 'terceirizar'])->name('os.terceirizados.store');
    Route::put('/os/{os}/terceirizados/{terceirizado}', [\App\Http\Controllers\OsOperacaoController::class, 'atualizarTerceirizado'])->name('os.terceirizados.update');
    Route::put('/os/{os}/troca-oleo', [\App\Http\Controllers\OsOperacaoController::class, 'trocaOleo'])->name('os.troca-oleo');
    Route::post('/os/{os}/inspecao/{inspecao}/lembrete', [\App\Http\Controllers\OrdemServicoInspecaoController::class, 'lembrar'])->name('os.inspecao.lembrete');
    Route::post('/os/{os}/garantias', [\App\Http\Controllers\OrdemServicoGarantiaController::class, 'store'])->name('os.garantias.store');
    Route::get('/clientes/{cliente}/extrato-frota', [\App\Http\Controllers\ClienteController::class, 'extratoFrota'])->name('clientes.extrato-frota');
    Route::put('/os/{os}/garantias/{garantia}', [\App\Http\Controllers\OrdemServicoGarantiaController::class, 'update'])->name('os.garantias.update');
    Route::post('/os/{os}/garantias/{garantia}/retorno', [\App\Http\Controllers\OrdemServicoGarantiaController::class, 'retorno'])->name('os.garantias.retorno');
    Route::get('/os/{os}/fotos/{foto}', [OrdemServicoFotoController::class, 'show'])->name('os.fotos.show');
    Route::delete('/os/{os}/fotos/{foto}', [OrdemServicoFotoController::class, 'destroy'])->name('os.fotos.destroy');

    Route::get('/historico/{tipo}/{id}', \App\Http\Controllers\HistoricoController::class)->whereNumber('id')->name('historico');
    Route::get('/cadastros/{tipo}', [\App\Http\Controllers\CadastroAuxiliarController::class, 'index'])->whereIn('tipo', ['categorias', 'fabricantes', 'categorias-servico'])->name('auxiliar.index');
    Route::post('/cadastros/{tipo}', [\App\Http\Controllers\CadastroAuxiliarController::class, 'store'])->whereIn('tipo', ['categorias', 'fabricantes', 'categorias-servico'])->name('auxiliar.store');
    Route::put('/cadastros/{tipo}/{id}', [\App\Http\Controllers\CadastroAuxiliarController::class, 'update'])->whereIn('tipo', ['categorias', 'fabricantes', 'categorias-servico'])->whereNumber('id')->name('auxiliar.update');
    Route::get('/importar/{tipo}', [\App\Http\Controllers\ImportacaoController::class, 'form'])->whereIn('tipo', ['clientes', 'produtos'])->name('importar');
    Route::get('/importar/{tipo}/modelo', [\App\Http\Controllers\ImportacaoController::class, 'modelo'])->whereIn('tipo', ['clientes', 'produtos'])->name('importar.modelo');
    Route::post('/importar/{tipo}', [\App\Http\Controllers\ImportacaoController::class, 'importar'])->whereIn('tipo', ['clientes', 'produtos'])->middleware('throttle:10,1')->name('importar.enviar');
    Route::get('/exportar/{tipo}', [\App\Http\Controllers\ImportacaoController::class, 'exportar'])->whereIn('tipo', ['clientes', 'veiculos', 'produtos', 'fornecedores'])->name('exportar.cadastro');

    Route::resource('servicos', ServicoController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::get('/modelos-os', [\App\Http\Controllers\ModeloOsController::class, 'index'])->name('modelos-os.index');
    Route::post('/modelos-os', [\App\Http\Controllers\ModeloOsController::class, 'store'])->name('modelos-os.store');
    Route::put('/modelos-os/{modelo}', [\App\Http\Controllers\ModeloOsController::class, 'update'])->name('modelos-os.update');

    // Estoque e compras
    Route::prefix('estoque/balanco')->name('balancos.')->controller(\App\Http\Controllers\BalancoController::class)->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('/novo', 'create')->name('create');
        Route::post('/', 'store')->middleware('throttle:20,1')->name('store');
        Route::get('/planilha', 'planilha')->name('planilha');
        Route::post('/planilha', 'receberPlanilha')->middleware('throttle:10,1')->name('planilha.enviar');
        Route::get('/{balanco}', 'show')->whereNumber('balanco')->name('show');
    });
    Route::resource('produtos', ProdutoController::class);
    Route::post('/produtos/{produto}/movimentar', [ProdutoController::class, 'movimentar'])->name('produtos.movimentar');
    Route::resource('fornecedores', FornecedorController::class)->parameters(['fornecedores' => 'fornecedor'])->except(['show', 'destroy']);
    Route::resource('compras', CompraController::class)->only(['index', 'store', 'show', 'destroy']);
    Route::post('/compras/{compra}/lancar', [CompraController::class, 'lancar'])->name('compras.lancar');
    Route::get('/compras/{compra}/xml', [CompraController::class, 'xml'])->name('compras.xml');

    // Caixa e financeiro
    Route::get('/caixa', [CaixaController::class, 'index'])->name('caixa.index');
    Route::get('/caixa/{caixa}', [CaixaController::class, 'show'])->name('caixa.show');
    Route::post('/caixa/abrir', [CaixaController::class, 'abrir'])->name('caixa.abrir');
    Route::post('/caixa/movimento', [CaixaController::class, 'movimento'])->name('caixa.movimento');
    Route::post('/caixa/fechar', [CaixaController::class, 'fechar'])->name('caixa.fechar');
    Route::post('/os/{os}/receber', [RecebimentoController::class, 'store'])->name('os.receber');
    Route::get('/financeiro/receber', [ContaReceberController::class, 'index'])->name('receber.index');
    Route::post('/financeiro/receber', [ContaReceberController::class, 'store'])->name('receber.store');
    Route::post('/financeiro/receber/{conta}/receber', [ContaReceberController::class, 'receber'])->name('receber.receber');
    Route::post('/financeiro/receber/{conta}/estornar', [ContaReceberController::class, 'estornar'])->name('receber.estornar');
    Route::post('/financeiro/receber/{conta}/cancelar', [ContaReceberController::class, 'cancelar'])->name('receber.cancelar');
    Route::get('/financeiro/pagar', [ContaPagarController::class, 'index'])->name('pagar.index');
    Route::post('/financeiro/pagar', [ContaPagarController::class, 'store'])->name('pagar.store');
    Route::post('/financeiro/pagar/{conta}/pagar', [ContaPagarController::class, 'pagar'])->name('pagar.pagar');
    Route::post('/financeiro/pagar/{conta}/cancelar', [ContaPagarController::class, 'cancelar'])->name('pagar.cancelar');
    Route::get('/financeiro/comissoes', [ComissaoController::class, 'index'])->name('comissoes.index');
    Route::post('/financeiro/comissoes/pagar', [ComissaoController::class, 'pagar'])->name('comissoes.pagar');
    Route::get('/financeiro/resumo', [RelatorioController::class, 'index'])->name('relatorio.index');

    // Notas fiscais
    Route::get('/notas', [NotaFiscalController::class, 'index'])->name('notas.index');
    Route::post('/os/{os}/notas/{tipo}', [NotaFiscalController::class, 'emitir'])->whereIn('tipo', ['nfse', 'nfce', 'nfe'])->name('notas.emitir');
    Route::post('/notas/{nota}/atualizar', [NotaFiscalController::class, 'atualizar'])->name('notas.atualizar');
    Route::post('/notas/{nota}/cancelar', [NotaFiscalController::class, 'cancelar'])->name('notas.cancelar');

    // Agenda e lembretes
    Route::get('/agenda', [AgendaController::class, 'index'])->name('agenda.index');
    Route::post('/agenda', [AgendaController::class, 'store'])->name('agenda.store');
    Route::post('/agenda/solicitacoes/{solicitacao}', [AgendaController::class, 'responder'])->name('agenda.solicitacoes.responder');
    Route::post('/agenda/{agendamento}/os', [AgendaController::class, 'abrirOs'])->name('agenda.abrir-os');
    Route::put('/agenda/{agendamento}', [AgendaController::class, 'update'])->name('agenda.update');
    Route::delete('/agenda/{agendamento}', [AgendaController::class, 'destroy'])->name('agenda.destroy');
    Route::get('/lembretes', [LembreteController::class, 'index'])->name('lembretes.index');
    Route::post('/lembretes/{lembrete}/enviar', [LembreteController::class, 'enviar'])->name('lembretes.enviar');
    Route::post('/lembretes/{lembrete}/confirmar-manual', [LembreteController::class, 'confirmarEnvioManual'])->name('lembretes.confirmar-manual');
    Route::post('/lembretes/{lembrete}/descartar', [LembreteController::class, 'descartar'])->name('lembretes.descartar');

    Route::middleware('can:usuarios.gerenciar')->group(function () {
        Route::resource('usuarios', UsuarioController::class)->except(['show', 'destroy']);
    });

    Route::middleware('can:empresa.configurar')->group(function () {
        Route::get('/empresa', [EmpresaController::class, 'edit'])->name('empresa.edit');
        Route::put('/empresa', [EmpresaController::class, 'update'])->name('empresa.update');
    });

    Route::get('/assinatura', [\App\Http\Controllers\AssinaturaController::class, 'index'])->name('assinatura');
    Route::post('/assinatura', [\App\Http\Controllers\AssinaturaController::class, 'assinar'])->middleware('throttle:10,1')->name('assinatura.assinar');

    // Painel da plataforma (só admin da plataforma, com 2 etapas)
    Route::middleware('can:plataforma')->prefix('plataforma')->name('plataforma.')->group(function () {
        Route::get('/', [\App\Http\Controllers\PlataformaController::class, 'index'])->name('index');
        Route::get('/empresas/{empresa}', [\App\Http\Controllers\PlataformaController::class, 'empresa'])->name('empresa');
        Route::put('/empresas/{empresa}', [\App\Http\Controllers\PlataformaController::class, 'atualizarEmpresa'])->name('empresa.update');
        Route::put('/empresas/{empresa}/dados', [\App\Http\Controllers\PlataformaController::class, 'atualizarDados'])->name('empresa.dados');
        Route::post('/empresas/{empresa}/liberar', [\App\Http\Controllers\PlataformaController::class, 'liberarCadastro'])->name('empresa.liberar');
        Route::post('/empresas/{empresa}/cobrancas', [\App\Http\Controllers\PlataformaController::class, 'registrarCobranca'])->name('empresa.cobrancas.store');
        Route::post('/empresas/{empresa}/cobrancas/{fatura}/confirmar', [\App\Http\Controllers\PlataformaController::class, 'confirmarCobranca'])->whereNumber('fatura')->name('empresa.cobrancas.confirmar');
        Route::get('/planos', [\App\Http\Controllers\PlataformaController::class, 'planos'])->name('planos');
        Route::post('/planos', [\App\Http\Controllers\PlataformaController::class, 'salvarPlano'])->name('planos.store');
        Route::put('/planos/{plano}', [\App\Http\Controllers\PlataformaController::class, 'salvarPlano'])->name('planos.update');
        Route::get('/webhooks', [\App\Http\Controllers\PlataformaController::class, 'webhooks'])->name('webhooks');
    });

    Route::get('/conta/seguranca', [\App\Http\Controllers\DoisFatoresController::class, 'seguranca'])->name('seguranca');
    Route::post('/conta/seguranca/ativar', [\App\Http\Controllers\DoisFatoresController::class, 'ativar'])->middleware('throttle:10,1')->name('seguranca.ativar');
    Route::post('/conta/seguranca/desativar', [\App\Http\Controllers\DoisFatoresController::class, 'desativar'])->name('seguranca.desativar');
    Route::get('/conta/senha', [AuthController::class, 'senhaForm'])->name('senha');
    Route::put('/conta/senha', [AuthController::class, 'senhaSalvar'])->name('senha.salvar');
});
