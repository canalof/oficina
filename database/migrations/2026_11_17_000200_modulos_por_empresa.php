<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Módulos opcionais por empresa (condutores/CNH, bancos/Pix, comissões, notas fiscais).
 * Oficina nova começa só com o básico. Nas existentes, liga o que já tem uso.
 * A Locação continua em empresas.modulo_locacao.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('empresas', fn (Blueprint $t) => $t->json('modulos')->nullable());

        $usa = fn (string $tabela) => Schema::hasTable($tabela) ? DB::table($tabela)->distinct()->pluck('empresa_id')->all() : [];
        $condutores = array_merge($usa('mobilidade_condutores'), $usa('mobilidade_retiradas'), $usa('conferencias_cnh'), $usa('cnh_integracoes'));
        $bancos = array_merge($usa('contas_bancarias'), $usa('cobrancas_pix'));
        $comissoes = array_merge($usa('comissoes'), DB::table('users')->where('comissao_percentual', '>', 0)->distinct()->pluck('empresa_id')->all());
        $fiscal = $usa('notas_fiscais');

        foreach (DB::table('empresas')->get(['id', 'modulo_locacao', 'fiscal_token']) as $e) {
            $modulos = array_values(array_filter([
                (in_array($e->id, $condutores) || $e->modulo_locacao) ? 'condutores' : null,
                in_array($e->id, $bancos) ? 'bancos' : null,
                in_array($e->id, $comissoes) ? 'comissoes' : null,
                (in_array($e->id, $fiscal) || filled($e->fiscal_token)) ? 'fiscal' : null,
            ]));
            DB::table('empresas')->where('id', $e->id)->update(['modulos' => json_encode($modulos)]);
        }
    }

    public function down(): void
    {
        Schema::table('empresas', fn (Blueprint $t) => $t->dropColumn('modulos'));
    }
};
