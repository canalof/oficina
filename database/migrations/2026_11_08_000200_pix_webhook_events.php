<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('pix_eventos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->foreignId('cobranca_pix_id')->constrained('cobrancas_pix')->cascadeOnDelete();
            $table->string('provedor', 60);
            $table->string('evento_externo', 190);
            $table->string('tipo', 60);
            $table->string('resultado', 30);
            $table->timestamp('recebido_em');
            $table->timestamps();
            $table->unique(['provedor', 'evento_externo'], 'pix_eventos_provedor_unique');
        });
        Schema::table('cobrancas_pix', function (Blueprint $table) {
            $table->string('copia_cola', 4096)->nullable();
            $table->text('qr_code_url')->nullable();
        });
    }
    public function down(): void
    {
        Schema::table('cobrancas_pix', function (Blueprint $table) {
            $table->dropColumn(['copia_cola', 'qr_code_url']);
        });
        Schema::dropIfExists('pix_eventos');
    }
};
