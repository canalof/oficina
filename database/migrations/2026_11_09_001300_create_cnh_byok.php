<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cnh_integracoes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas');
            $table->string('provedor', 32);
            $table->text('credenciais');
            $table->json('finalidades');
            $table->boolean('ativa')->default(false);
            $table->timestamp('autorizada_em');
            $table->timestamp('testada_em')->nullable();
            $table->boolean('teste_ok')->nullable();
            $table->timestamps();
            $table->unique(['empresa_id', 'provedor']);
        });
        Schema::create('cnh_consultas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas');
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('condutor_id')->constrained('mobilidade_condutores')->cascadeOnDelete();
            $table->foreignId('ordem_servico_id')->nullable()->constrained('ordens_servico')->nullOnDelete();
            $table->string('provedor', 32);
            $table->string('finalidade', 20);
            $table->string('status', 20);
            $table->json('evidencias')->nullable();
            $table->timestamps();
            $table->index(['empresa_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cnh_consultas');
        Schema::dropIfExists('cnh_integracoes');
    }
};
