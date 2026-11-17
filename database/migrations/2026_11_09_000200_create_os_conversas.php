<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('os_mensagens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->foreignId('ordem_servico_id')->constrained('ordens_servico')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('perfil_mencionado', 16)->nullable();
            $table->text('mensagem');
            $table->timestamps();
        });
        Schema::create('os_conversa_leituras', function (Blueprint $table) {
            $table->foreignId('ordem_servico_id')->constrained('ordens_servico')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedBigInteger('ultima_mensagem_id');
            $table->primary(['ordem_servico_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('os_conversa_leituras');
        Schema::dropIfExists('os_mensagens');
    }
};
