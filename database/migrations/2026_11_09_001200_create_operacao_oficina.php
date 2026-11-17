<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('os_compras_pendentes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained();
            $table->foreignId('ordem_servico_id')->constrained('ordens_servico')->cascadeOnDelete();
            $table->foreignId('item_id')->unique()->constrained('ordem_servico_itens')->cascadeOnDelete();
            $table->foreignId('compra_item_id')->nullable()->constrained('compra_itens')->nullOnDelete();
            $table->json('cotacoes');
            $table->unsignedTinyInteger('escolhida')->nullable();
            $table->timestamps();
        });
        Schema::create('os_terceirizados', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained();
            $table->foreignId('ordem_servico_id')->constrained('ordens_servico')->cascadeOnDelete();
            $table->string('servico', 150);
            $table->string('parceiro', 150);
            $table->string('responsavel', 120);
            $table->unsignedInteger('custo_centavos');
            $table->date('previsao');
            $table->string('status', 20);
            $table->timestamps();
        });
        Schema::create('os_trocas_oleo', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained();
            $table->foreignId('ordem_servico_id')->unique()->constrained('ordens_servico')->cascadeOnDelete();
            $table->string('especificacao', 120);
            $table->decimal('litros', 6, 2);
            $table->string('filtro', 120)->nullable();
            $table->unsignedInteger('km');
            $table->date('realizada_em');
            $table->timestamps();
        });
        Schema::table('ordem_servico_garantias', function (Blueprint $table) {
            $table->foreignId('retorno_id')->nullable()->unique()->constrained('ordens_servico')->nullOnDelete();
            $table->unsignedInteger('custo_pecas_centavos')->nullable();
            $table->unsignedInteger('custo_mao_obra_centavos')->nullable();
        });
        Schema::create('agenda_solicitacoes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained();
            $table->foreignId('cliente_id')->constrained();
            $table->foreignId('veiculo_id')->constrained();
            $table->foreignId('agendamento_id')->nullable()->constrained('agendamentos')->nullOnDelete();
            $table->dateTime('preferencia');
            $table->string('servico', 200);
            $table->string('status', 20)->default('pendente');
            $table->timestamps();
            $table->index(['empresa_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agenda_solicitacoes');
        Schema::table('ordem_servico_garantias', function (Blueprint $table) {
            $table->dropForeign(['retorno_id']);
            $table->dropUnique(['retorno_id']);
            $table->dropColumn('retorno_id');
            $table->dropColumn(['custo_pecas_centavos', 'custo_mao_obra_centavos']);
        });
        Schema::dropIfExists('os_trocas_oleo');
        Schema::dropIfExists('os_terceirizados');
        Schema::dropIfExists('os_compras_pendentes');
    }
};
