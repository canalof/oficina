<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Etapas 5 e 6: notas fiscais, agenda e lembretes de revisão. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('empresas', function (Blueprint $table) {
            $table->string('inscricao_estadual', 20)->nullable();
            $table->string('inscricao_municipal', 20)->nullable();
            $table->string('codigo_municipio', 7)->nullable(); // IBGE
            $table->string('logradouro')->nullable();
            $table->string('numero', 20)->nullable();
            $table->string('bairro', 80)->nullable();
            $table->string('cep', 8)->nullable();
            $table->unsignedTinyInteger('regime_tributario')->default(1); // 1 Simples, 2 Simples excesso, 3 Normal
            $table->string('cfop_padrao', 4)->default('5102');
            $table->string('csosn_padrao', 3)->default('102');
            $table->string('item_lista_servico', 10)->default('14.01');
            $table->decimal('aliquota_iss', 5, 2)->default(0);
            $table->unsignedInteger('serie_nfce')->default(1);
            $table->string('fiscal_ambiente', 12)->default('homologacao'); // homologacao | producao
            $table->text('fiscal_token')->nullable(); // criptografado
            $table->string('whatsapp_instancia', 80)->nullable(); // Evolution API
        });

        Schema::create('notas_fiscais', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ordem_servico_id')->nullable()->constrained('ordens_servico')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('tipo', 5); // nfse | nfce | nfe
            $table->string('referencia', 60)->unique();
            $table->string('provedor', 20);
            $table->string('ambiente', 12);
            $table->string('status', 12)->default('processando'); // processando | autorizada | erro | cancelada
            $table->string('numero', 20)->nullable();
            $table->string('serie', 5)->nullable();
            $table->string('chave', 60)->nullable();
            $table->unsignedInteger('valor_centavos');
            $table->string('url_pdf', 500)->nullable();
            $table->string('url_xml', 500)->nullable();
            $table->text('mensagem')->nullable();
            $table->json('envio')->nullable();
            $table->json('resposta')->nullable();
            $table->dateTime('autorizada_em')->nullable();
            $table->dateTime('cancelada_em')->nullable();
            $table->timestamps();
            $table->index(['empresa_id', 'status']);
        });

        Schema::table('servicos', function (Blueprint $table) {
            $table->unsignedSmallInteger('revisao_meses')->nullable();
            $table->unsignedInteger('revisao_km')->nullable();
        });

        Schema::create('agendamentos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained()->cascadeOnDelete();
            $table->foreignId('cliente_id')->nullable()->constrained('clientes')->nullOnDelete();
            $table->foreignId('veiculo_id')->nullable()->constrained('veiculos')->nullOnDelete();
            $table->foreignId('ordem_servico_id')->nullable()->constrained('ordens_servico')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('nome', 120);
            $table->string('telefone', 20)->nullable();
            $table->string('placa', 7)->nullable();
            $table->dateTime('inicio');
            $table->unsignedSmallInteger('duracao_minutos')->default(60);
            $table->string('servico', 200);
            $table->string('status', 12)->default('agendado'); // agendado | confirmado | chegou | faltou | cancelado
            $table->dateTime('lembrete_enviado_em')->nullable();
            $table->text('observacoes')->nullable();
            $table->timestamps();
            $table->index(['empresa_id', 'inicio']);
        });

        Schema::create('lembretes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained()->cascadeOnDelete();
            $table->foreignId('veiculo_id')->constrained('veiculos')->cascadeOnDelete();
            $table->foreignId('cliente_id')->nullable()->constrained('clientes')->nullOnDelete();
            $table->foreignId('ordem_servico_id')->nullable()->constrained('ordens_servico')->nullOnDelete();
            $table->string('descricao', 200);
            $table->date('data_prevista');
            $table->unsignedInteger('km_previsto')->nullable();
            $table->string('status', 12)->default('pendente'); // pendente | enviado | agendado | descartado
            $table->dateTime('enviado_em')->nullable();
            $table->timestamps();
            $table->index(['empresa_id', 'status', 'data_prevista']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lembretes');
        Schema::dropIfExists('agendamentos');
        Schema::table('servicos', fn (Blueprint $t) => $t->dropColumn(['revisao_meses', 'revisao_km']));
        Schema::dropIfExists('notas_fiscais');
        Schema::table('empresas', fn (Blueprint $t) => $t->dropColumn([
            'inscricao_estadual', 'inscricao_municipal', 'codigo_municipio', 'logradouro', 'numero', 'bairro', 'cep',
            'regime_tributario', 'cfop_padrao', 'csosn_padrao', 'item_lista_servico', 'aliquota_iss', 'serie_nfce',
            'fiscal_ambiente', 'fiscal_token', 'whatsapp_instancia',
        ]));
    }
};
