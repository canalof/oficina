<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Etapa 4: caixa do dia, contas a receber e a pagar, comissões. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->decimal('comissao_percentual', 5, 2)->default(0)->after('perfil');
        });

        Schema::create('caixas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained()->cascadeOnDelete();
            $table->foreignId('aberto_por')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('fechado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('aberto_em');
            $table->dateTime('fechado_em')->nullable();
            $table->integer('saldo_inicial_centavos')->default(0);
            $table->integer('dinheiro_esperado_centavos')->nullable();
            $table->integer('dinheiro_contado_centavos')->nullable();
            $table->string('observacao', 255)->nullable();
            $table->timestamps();
            $table->index(['empresa_id', 'fechado_em']);
        });

        Schema::create('caixa_movimentos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained()->cascadeOnDelete();
            $table->foreignId('caixa_id')->constrained('caixas')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('tipo', 12); // recebimento | pagamento | suprimento | sangria
            $table->string('forma', 12); // dinheiro | pix | debito | credito | boleto | transferencia
            $table->unsignedInteger('valor_centavos');
            $table->string('descricao', 200);
            $table->unsignedBigInteger('conta_receber_id')->nullable();
            $table->unsignedBigInteger('conta_pagar_id')->nullable();
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('contas_receber', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained()->cascadeOnDelete();
            $table->foreignId('cliente_id')->nullable()->constrained('clientes')->nullOnDelete();
            $table->foreignId('ordem_servico_id')->nullable()->constrained('ordens_servico')->nullOnDelete();
            $table->string('descricao', 200);
            $table->string('parcela', 10)->nullable();
            $table->unsignedInteger('valor_centavos');
            $table->date('vencimento');
            $table->string('forma', 12); // como foi combinado (prazo, boleto, cartão...)
            $table->string('status', 10)->default('aberta'); // aberta | recebida | cancelada
            $table->dateTime('recebido_em')->nullable();
            $table->string('forma_recebida', 12)->nullable();
            $table->timestamps();
            $table->index(['empresa_id', 'status', 'vencimento']);
        });

        Schema::create('contas_pagar', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained()->cascadeOnDelete();
            $table->foreignId('fornecedor_id')->nullable()->constrained('fornecedores')->nullOnDelete();
            $table->foreignId('compra_id')->nullable()->constrained('compras')->nullOnDelete();
            $table->string('categoria', 40);
            $table->string('descricao', 200);
            $table->string('documento', 40)->nullable();
            $table->unsignedInteger('valor_centavos');
            $table->date('vencimento');
            $table->string('status', 10)->default('aberta'); // aberta | paga | cancelada
            $table->dateTime('pago_em')->nullable();
            $table->string('forma_paga', 12)->nullable();
            $table->timestamps();
            $table->index(['empresa_id', 'status', 'vencimento']);
        });

        Schema::create('comissoes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ordem_servico_id')->constrained('ordens_servico')->cascadeOnDelete();
            $table->unsignedInteger('base_centavos');
            $table->decimal('percentual', 5, 2);
            $table->unsignedInteger('valor_centavos');
            $table->date('competencia');
            $table->dateTime('paga_em')->nullable();
            $table->timestamps();
            $table->unique(['ordem_servico_id', 'user_id']);
        });

        Schema::table('ordens_servico', function (Blueprint $table) {
            $table->dateTime('recebido_em')->nullable()->after('entregue_em');
        });
    }

    public function down(): void
    {
        Schema::table('ordens_servico', fn (Blueprint $t) => $t->dropColumn('recebido_em'));
        Schema::dropIfExists('comissoes');
        Schema::dropIfExists('contas_pagar');
        Schema::dropIfExists('contas_receber');
        Schema::dropIfExists('caixa_movimentos');
        Schema::dropIfExists('caixas');
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn('comissao_percentual'));
    }
};
