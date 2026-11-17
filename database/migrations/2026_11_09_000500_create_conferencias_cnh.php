<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conferencias_cnh', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->foreignId('ordem_servico_id')->nullable()->constrained('ordens_servico')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('condutor', 120);
            $table->string('finalidade', 12);
            $table->string('metodo', 12);
            $table->string('resultado', 12);
            $table->timestamps();
            $table->index(['empresa_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conferencias_cnh');
    }
};
