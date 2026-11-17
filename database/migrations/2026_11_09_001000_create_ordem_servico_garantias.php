<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ordem_servico_garantias', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas');
            $table->foreignId('ordem_servico_id')->constrained('ordens_servico')->cascadeOnDelete();
            $table->foreignId('foto_id')->nullable()->constrained('ordem_servico_fotos')->nullOnDelete();
            $table->string('peca', 150);
            $table->string('fornecedor', 150)->nullable();
            $table->date('prazo')->nullable();
            $table->string('status', 20);
            $table->text('relato');
            $table->text('resolucao')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ordem_servico_garantias');
    }
};
