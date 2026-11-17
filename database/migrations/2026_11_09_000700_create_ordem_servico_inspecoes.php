<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ordem_servico_inspecoes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas');
            $table->foreignId('ordem_servico_id')->constrained('ordens_servico')->cascadeOnDelete();
            $table->foreignId('item_id')->nullable()->constrained('ordem_servico_itens')->nullOnDelete();
            $table->foreignId('foto_id')->nullable()->constrained('ordem_servico_fotos')->nullOnDelete();
            $table->string('componente', 100);
            $table->string('gravidade', 16);
            $table->text('observacao')->nullable();
            $table->date('revisar_em')->nullable();
            $table->unsignedInteger('revisar_km')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ordem_servico_inspecoes');
    }
};
