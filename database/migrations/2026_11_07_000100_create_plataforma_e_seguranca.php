<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Plataforma SaaS (planos, assinatura, faturas, webhooks), segurança (2 fatores),
 * orçamento com validade e versão, aceites, política de estoque, capacidade da agenda,
 * registro de mensagens de WhatsApp e portal do cliente.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('planos', function (Blueprint $table) {
            $table->id();
            $table->string('nome', 60);
            $table->string('slug', 30)->unique();
            $table->unsignedInteger('preco_centavos');
            $table->unsignedSmallInteger('limite_usuarios')->nullable(); // null = sem limite
            $table->unsignedSmallInteger('limite_lojas')->nullable();
            $table->unsignedInteger('limite_consultas_placa')->nullable(); // por mês
            $table->boolean('whatsapp_automatico')->default(false);
            $table->boolean('ativo')->default(true);
            $table->unsignedTinyInteger('ordem')->default(0);
            $table->timestamps();
        });

        // A coluna de texto "plano" (nunca usada) dá lugar à relação com a tabela de planos
        Schema::table('empresas', fn (Blueprint $t) => $t->dropColumn('plano'));

        Schema::table('empresas', function (Blueprint $table) {
            $table->foreignId('plano_id')->nullable()->constrained('planos')->nullOnDelete();
            $table->string('assinatura_status', 12)->default('trial'); // trial | ativa | inadimplente | suspensa | cancelada
            $table->date('trial_ate')->nullable();
            $table->string('cobranca_cliente_id', 60)->nullable();
            $table->string('cobranca_assinatura_id', 60)->nullable();
            $table->date('proximo_vencimento')->nullable();
            $table->dateTime('suspensa_em')->nullable();
            $table->boolean('estoque_negativo')->default(true);
            $table->unsignedTinyInteger('agenda_capacidade')->default(2);
            $table->unsignedSmallInteger('orcamento_validade_dias')->default(15);
            $table->index('assinatura_status');
        });

        Schema::create('faturas_plataforma', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained()->cascadeOnDelete();
            $table->string('externo_id', 60)->unique();
            $table->unsignedInteger('valor_centavos');
            $table->date('vencimento')->nullable();
            $table->string('status', 12)->default('pendente'); // pendente | paga | vencida | estornada
            $table->string('url', 500)->nullable();
            $table->dateTime('paga_em')->nullable();
            $table->timestamps();
        });

        Schema::create('webhooks_recebidos', function (Blueprint $table) {
            $table->id();
            $table->string('provedor', 20);
            $table->string('evento_id', 80);
            $table->string('evento', 60);
            $table->json('payload');
            $table->dateTime('processado_em')->nullable();
            $table->string('erro', 255)->nullable();
            $table->timestamps();
            $table->unique(['provedor', 'evento_id']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->boolean('admin_plataforma')->default(false);
            $table->text('dois_fatores_segredo')->nullable();
            $table->text('dois_fatores_recuperacao')->nullable();
            $table->dateTime('dois_fatores_ativo_em')->nullable();
        });

        Schema::table('ordens_servico', function (Blueprint $table) {
            $table->unsignedInteger('versao')->default(1);
            $table->dateTime('token_expira_em')->nullable();
        });

        // Prova do aceite: o que exatamente o cliente aprovou
        Schema::create('orcamento_aceites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ordem_servico_id')->constrained('ordens_servico')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('versao');
            $table->string('canal', 10); // link | balcao
            $table->string('nome', 120);
            $table->string('ip', 45)->nullable();
            $table->string('navegador', 255)->nullable();
            $table->json('itens');
            $table->unsignedInteger('total_centavos');
            $table->char('hash', 64);
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('mensagens_whatsapp', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained()->cascadeOnDelete();
            $table->string('destino', 20);
            $table->string('tipo', 20); // lembrete | agenda | teste
            $table->unsignedBigInteger('referencia_id')->nullable();
            $table->text('texto');
            $table->string('status', 10)->default('pendente'); // pendente | enviada | falhou
            $table->unsignedTinyInteger('tentativas')->default(0);
            $table->string('erro', 255)->nullable();
            $table->dateTime('enviada_em')->nullable();
            $table->timestamps();
            $table->index(['empresa_id', 'status']);
        });

        Schema::table('clientes', function (Blueprint $table) {
            $table->string('portal_token', 48)->nullable()->unique();
        });
    }

    public function down(): void
    {
        Schema::table('clientes', function (Blueprint $t) {
            $t->dropUnique(['portal_token']);
            $t->dropColumn('portal_token');
        });
        Schema::dropIfExists('mensagens_whatsapp');
        Schema::dropIfExists('orcamento_aceites');
        Schema::table('ordens_servico', fn (Blueprint $t) => $t->dropColumn(['versao', 'token_expira_em']));
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn(['admin_plataforma', 'dois_fatores_segredo', 'dois_fatores_recuperacao', 'dois_fatores_ativo_em']));
        Schema::dropIfExists('webhooks_recebidos');
        Schema::dropIfExists('faturas_plataforma');
        Schema::table('empresas', function (Blueprint $t) {
            $t->dropConstrainedForeignId('plano_id');
            $t->dropIndex(['assinatura_status']);
            $t->dropColumn(['assinatura_status', 'trial_ate', 'cobranca_cliente_id', 'cobranca_assinatura_id', 'proximo_vencimento', 'suspensa_em', 'estoque_negativo', 'agenda_capacidade', 'orcamento_validade_dias']);
        });
        Schema::dropIfExists('planos');
        Schema::table('empresas', fn (Blueprint $t) => $t->string('plano', 20)->nullable());
    }
};
