<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('autocenter_servicos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas');
            $table->foreignId('veiculo_id')->constrained('veiculos');
            $table->foreignId('ordem_servico_id')->nullable()->constrained('ordens_servico')->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('tipo', 30);
            $table->unsignedInteger('km_registrado')->nullable();
            $table->string('posicao_origem', 30)->nullable();
            $table->string('posicao_destino', 30)->nullable();
            $table->text('observacoes')->nullable();
            $table->timestamps();
            $table->index(['empresa_id', 'veiculo_id', 'created_at'], 'autocenter_empresa_veiculo_data_idx');
        });
    }
    public function down(): void { Schema::dropIfExists('autocenter_servicos'); }
};
