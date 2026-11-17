<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contas_bancarias', function (Blueprint $table) {
            $table->string('pix_tipo', 12)->nullable();
            $table->text('pix_chave')->nullable();
            $table->string('pix_recebedor', 25)->nullable();
            $table->string('pix_cidade', 15)->nullable();
        });
        Schema::table('cobrancas_pix', fn (Blueprint $table) => $table->text('pix_payload')->nullable());
    }

    public function down(): void
    {
        Schema::table('cobrancas_pix', fn (Blueprint $table) => $table->dropColumn('pix_payload'));
        Schema::table('contas_bancarias', fn (Blueprint $table) => $table->dropColumn(['pix_tipo', 'pix_chave', 'pix_recebedor', 'pix_cidade']));
    }
};
