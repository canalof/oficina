<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('contas_bancarias', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->string('apelido', 100);
            $table->string('banco_codigo', 10)->nullable();
            $table->string('banco_nome', 100);
            $table->string('agencia', 20)->nullable();
            $table->string('numero_conta', 40)->nullable();
            $table->string('tipo', 20)->default('corrente');
            $table->bigInteger('saldo_inicial_centavos')->default(0);
            $table->boolean('ativa')->default(true);
            $table->timestamps();
            $table->index(['empresa_id', 'ativa']);
        });

        Schema::create('movimentos_bancarios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->foreignId('conta_bancaria_id')->constrained('contas_bancarias')->restrictOnDelete();
            $table->date('data_movimento');
            $table->bigInteger('valor_centavos'); // positivo = crédito, negativo = débito
            $table->string('descricao', 255);
            $table->string('origem', 20)->default('manual');
            $table->string('referencia_externa', 190)->nullable();
            $table->string('hash_importacao', 64)->nullable();
            $table->string('status_conciliacao', 20)->default('pendente');
            $table->foreignId('conta_receber_id')->nullable()->constrained('contas_receber')->nullOnDelete();
            $table->foreignId('conta_pagar_id')->nullable()->constrained('contas_pagar')->nullOnDelete();
            $table->foreignId('criado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['empresa_id', 'conta_bancaria_id', 'data_movimento'], 'mov_banc_tenant_conta_data_idx');
            $table->unique(['conta_bancaria_id', 'hash_importacao'], 'mov_banc_import_unique');
        });

        Schema::create('cobrancas_pix', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->foreignId('conta_bancaria_id')->constrained('contas_bancarias')->restrictOnDelete();
            $table->foreignId('conta_receber_id')->nullable()->constrained('contas_receber')->nullOnDelete();
            $table->string('provedor', 60);
            $table->string('id_externo', 190)->nullable();
            $table->string('txid', 100)->nullable();
            $table->unsignedBigInteger('valor_centavos');
            $table->string('status', 20)->default('pendente');
            $table->timestamp('expira_em')->nullable();
            $table->timestamp('confirmado_em')->nullable();
            $table->timestamps();
            $table->unique(['empresa_id', 'provedor', 'id_externo'], 'pix_tenant_provedor_ext_unique');
            $table->index(['empresa_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cobrancas_pix');
        Schema::dropIfExists('movimentos_bancarios');
        Schema::dropIfExists('contas_bancarias');
    }
};
