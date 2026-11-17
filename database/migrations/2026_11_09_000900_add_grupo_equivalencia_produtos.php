<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('produtos', function (Blueprint $table) {
            $table->string('grupo_equivalencia', 80)->nullable();
            $table->index(['empresa_id', 'grupo_equivalencia']);
        });
    }

    public function down(): void
    {
        Schema::table('produtos', function (Blueprint $table) {
            $table->dropIndex(['empresa_id', 'grupo_equivalencia']);
            $table->dropColumn('grupo_equivalencia');
        });
    }
};
