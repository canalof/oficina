<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lembretes', function (Blueprint $table) {
            $table->foreignId('inspecao_id')->nullable()->unique()->constrained('ordem_servico_inspecoes')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('lembretes', function (Blueprint $table) {
            $table->dropForeign(['inspecao_id']);
            $table->dropUnique(['inspecao_id']);
            $table->dropColumn('inspecao_id');
        });
    }
};
