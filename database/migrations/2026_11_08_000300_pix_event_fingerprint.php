<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('pix_eventos', function (Blueprint $table) {
            // Nullable para permitir upgrade de instalações com eventos históricos.
            // Novos eventos exigem digest preenchido pelo serviço.
            $table->char('impressao_payload', 64)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('pix_eventos', fn (Blueprint $table) => $table->dropColumn('impressao_payload'));
    }
};
