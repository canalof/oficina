<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * F-247-03: a conferência manual passa a ser vinculada ao condutor cadastrado e
 * a registrar a origem declaratória em evidencias.
 *
 * Em instalações que já possuem as colunas (caso da produção auditada) esta
 * migração é um no-op. Em instalações novas, cria as colunas que faltavam no
 * arquivo original de 000500, sem remover nem reescrever dados existentes.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('conferencias_cnh', 'condutor_id')) {
            Schema::table('conferencias_cnh', function (Blueprint $table) {
                $table->foreignId('condutor_id')->nullable()->after('user_id')
                    ->constrained('mobilidade_condutores')->nullOnDelete();
            });
        }

        if (! Schema::hasColumn('conferencias_cnh', 'evidencias')) {
            Schema::table('conferencias_cnh', function (Blueprint $table) {
                $table->json('evidencias')->nullable()->after('resultado');
            });
        }
    }

    /**
     * Não remove colunas: elas podem ter existido antes desta migração. A
     * reversão do código não depende da remoção e preserva o histórico.
     */
    public function down(): void
    {
        // Intencionalmente vazio (migração de reconciliação não destrutiva).
    }
};
