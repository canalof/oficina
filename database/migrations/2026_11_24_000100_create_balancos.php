<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Balanço de estoque: contagem de vários produtos de uma vez, aplicada só onde há diferença. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('balancos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('observacao', 255)->nullable();
            $table->unsignedInteger('produtos_contados')->default(0);
            $table->unsignedInteger('produtos_ajustados')->default(0);
            $table->bigInteger('diferenca_valor_centavos')->default(0); // pelo custo médio; negativo = faltou mercadoria
            $table->timestamps();
            $table->index(['empresa_id', 'created_at']);
        });

        Schema::create('balanco_itens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained()->cascadeOnDelete();
            $table->foreignId('balanco_id')->constrained('balancos')->cascadeOnDelete();
            $table->foreignId('produto_id')->constrained('produtos')->cascadeOnDelete();
            $table->decimal('saldo_visto', 12, 3)->nullable(); // saldo quando a tela/planilha foi gerada
            $table->decimal('saldo_sistema', 12, 3);            // saldo na hora de aplicar
            $table->decimal('contado', 12, 3);
            $table->decimal('diferenca', 12, 3);
            $table->unsignedInteger('custo_unitario_centavos')->default(0);
            $table->index(['empresa_id', 'balanco_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('balanco_itens');
        Schema::dropIfExists('balancos');
    }
};
