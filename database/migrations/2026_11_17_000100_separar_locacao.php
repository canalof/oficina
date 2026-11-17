<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Separa a Locação da oficina:
 * - módulo por empresa (empresas.modulo_locacao), ligado onde já há frota ou reservas;
 * - reserva com modalidade: carro_reserva (ligada a uma OS) ou aluguel (só cliente, sem OS);
 * - tipo da reserva passa a dizer a origem do carro: frota (própria) ou parceira;
 * - contas a receber ganham categoria, para separar a Locação nos relatórios.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('empresas', function (Blueprint $t) {
            $t->boolean('modulo_locacao')->default(false);
        });
        Schema::table('contas_receber', function (Blueprint $t) {
            $t->string('categoria', 24)->nullable();
        });
        Schema::table('mobilidade_reservas', function (Blueprint $t) {
            $t->string('modalidade', 16)->default('carro_reserva');
            $t->foreignId('cliente_id')->nullable()->constrained('clientes')->restrictOnDelete();
        });
        Schema::table('mobilidade_reservas', function (Blueprint $t) {
            $t->unsignedBigInteger('ordem_servico_id')->nullable()->change();
        });

        DB::table('mobilidade_reservas')->whereIn('tipo', ['reserva', 'locacao'])->update(['tipo' => 'frota']);
        foreach (DB::table('mobilidade_reservas')->whereNull('cliente_id')->whereNotNull('ordem_servico_id')->get(['id', 'ordem_servico_id']) as $r) {
            DB::table('mobilidade_reservas')->where('id', $r->id)->update([
                'cliente_id' => DB::table('ordens_servico')->where('id', $r->ordem_servico_id)->value('cliente_id'),
            ]);
        }
        DB::table('contas_receber')->where('descricao', 'like', 'Mobilidade · reserva #%')->update(['categoria' => 'locacao']);

        $comLocacao = DB::table('mobilidade_frota')->pluck('empresa_id')
            ->merge(DB::table('mobilidade_reservas')->pluck('empresa_id'))->unique()->values();
        DB::table('empresas')->whereIn('id', $comLocacao)->update(['modulo_locacao' => true]);
    }

    public function down(): void
    {
        if (DB::table('mobilidade_reservas')->whereNull('ordem_servico_id')->exists()) {
            throw new RuntimeException('Há aluguéis sem OS. Exclua ou ligue esses registros a uma OS antes de desfazer esta migration.');
        }
        DB::table('mobilidade_reservas')->where('tipo', 'frota')->where('diaria_centavos', '>', 0)->update(['tipo' => 'locacao']);
        DB::table('mobilidade_reservas')->where('tipo', 'frota')->update(['tipo' => 'reserva']);

        Schema::table('mobilidade_reservas', function (Blueprint $t) {
            $t->unsignedBigInteger('ordem_servico_id')->nullable(false)->change();
        });
        Schema::table('mobilidade_reservas', function (Blueprint $t) {
            $t->dropConstrainedForeignId('cliente_id');
            $t->dropColumn('modalidade');
        });
        Schema::table('contas_receber', function (Blueprint $t) {
            $t->dropColumn('categoria');
        });
        Schema::table('empresas', fn (Blueprint $t) => $t->dropColumn('modulo_locacao'));
    }
};
