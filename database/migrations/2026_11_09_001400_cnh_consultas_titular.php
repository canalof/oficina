<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * F-247-02: permite registrar consultas feitas sem condutor previamente
 * cadastrado (tela geral / modo legado) e guarda o identificador pseudônimo do
 * titular (HMAC-SHA256 do CPF), nunca o CPF em claro.
 *
 * Operação não destrutiva: apenas relaxa a nulidade de condutor_id e adiciona
 * uma coluna/index novos. A tabela estava vazia no momento da implantação.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cnh_consultas', function (Blueprint $table) {
            $table->unsignedBigInteger('condutor_id')->nullable()->change();
            $table->char('titular_hash', 64)->nullable()->after('condutor_id');
            $table->index(['empresa_id', 'titular_hash'], 'cnh_consultas_empresa_titular_index');
        });
    }

    public function down(): void
    {
        $pendentes = DB::table('cnh_consultas')->whereNull('condutor_id')->count();
        if ($pendentes > 0) {
            throw new RuntimeException(
                "Rollback recusado: {$pendentes} consulta(s) sem condutor precisam ser preservadas. "
                .'Exporte esses registros para a trilha de auditoria antes de reverter a coluna condutor_id.'
            );
        }

        Schema::table('cnh_consultas', function (Blueprint $table) {
            $table->dropIndex('cnh_consultas_empresa_titular_index');
            $table->dropColumn('titular_hash');
            $table->unsignedBigInteger('condutor_id')->nullable(false)->change();
        });
    }
};
