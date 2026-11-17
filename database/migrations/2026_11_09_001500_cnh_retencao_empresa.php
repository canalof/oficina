<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * F-247-04: retenção de evidências de CNH configurável por empresa.
 *
 * NULL = herda a política da instalação (config cnh.retencao_dias) e, se esta
 * também não estiver definida, aplica-se o limite técnico de segurança. Zero ou
 * valores fora de 30..3650 nunca são aceitos como "ilimitado".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('empresas', function (Blueprint $table) {
            $table->unsignedSmallInteger('cnh_retencao_dias')->nullable()->after('mobilidade_bloqueia_divergencia');
        });
    }

    public function down(): void
    {
        Schema::table('empresas', function (Blueprint $table) {
            $table->dropColumn('cnh_retencao_dias');
        });
    }
};
