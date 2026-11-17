<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('pneu_inspecoes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas');
            $table->foreignId('veiculo_id')->constrained('veiculos');
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('posicao', 20);
            $table->string('medida', 30)->nullable();
            $table->string('marca', 80)->nullable();
            $table->string('dot', 4)->nullable();
            $table->unsignedSmallInteger('sulco_decimos_mm')->nullable();
            $table->unsignedInteger('km_registrado')->nullable();
            $table->text('observacoes')->nullable();
            $table->timestamps();
            $table->index(['empresa_id', 'veiculo_id', 'created_at']);
        });
    }
    public function down(): void { Schema::dropIfExists('pneu_inspecoes'); }
};
