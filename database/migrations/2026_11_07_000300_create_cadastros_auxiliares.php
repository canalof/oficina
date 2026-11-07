<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 2 (Torque 2.0): Renavam e motor no veículo, categorias e fabricantes de produto,
 * referência do fabricante, custo médio (com último custo) e saldo anterior nos movimentos de estoque.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('veiculos', function (Blueprint $table) {
            $table->string('renavam', 11)->nullable()->after('chassi');
            $table->string('motor', 40)->nullable()->after('versao');
        });

        foreach (['categorias', 'fabricantes'] as $t) {
            Schema::create($t, function (Blueprint $table) {
                $table->id();
                $table->foreignId('empresa_id')->constrained()->cascadeOnDelete();
                $table->string('nome', 80);
                $table->boolean('ativo')->default(true);
                $table->timestamps();
                $table->unique(['empresa_id', 'nome']);
            });
        }

        Schema::table('produtos', function (Blueprint $table) {
            $table->foreignId('categoria_id')->nullable()->after('empresa_id')->constrained('categorias')->nullOnDelete();
            $table->foreignId('fabricante_id')->nullable()->after('categoria_id')->constrained('fabricantes')->nullOnDelete();
            $table->string('referencia_fabricante', 40)->nullable()->after('codigo_barras');
            $table->unsignedInteger('ultimo_custo_centavos')->nullable()->after('custo_centavos');
        });

        Schema::table('estoque_movimentos', function (Blueprint $table) {
            $table->decimal('saldo_anterior', 12, 3)->nullable()->after('quantidade');
        });

        // Movimentos antigos: saldo anterior = saldo - quantidade
        DB::table('estoque_movimentos')->update(['saldo_anterior' => DB::raw('saldo - quantidade')]);

        // A "marca" que era texto vira fabricante cadastrado
        $agora = now();
        foreach (DB::table('produtos')->whereNotNull('marca')->where('marca', '<>', '')->select('empresa_id', 'marca')->distinct()->get() as $p) {
            $nome = mb_substr(trim($p->marca), 0, 80);
            $id = DB::table('fabricantes')->where('empresa_id', $p->empresa_id)->where('nome', $nome)->value('id')
                ?? DB::table('fabricantes')->insertGetId(['empresa_id' => $p->empresa_id, 'nome' => $nome, 'ativo' => true, 'created_at' => $agora, 'updated_at' => $agora]);
            DB::table('produtos')->where('empresa_id', $p->empresa_id)->where('marca', $p->marca)->update(['fabricante_id' => $id]);
        }
        DB::table('produtos')->update(['ultimo_custo_centavos' => DB::raw('custo_centavos')]);
    }

    public function down(): void
    {
        Schema::table('estoque_movimentos', fn (Blueprint $t) => $t->dropColumn('saldo_anterior'));
        Schema::table('produtos', function (Blueprint $t) {
            $t->dropConstrainedForeignId('categoria_id');
            $t->dropConstrainedForeignId('fabricante_id');
            $t->dropColumn(['referencia_fabricante', 'ultimo_custo_centavos']);
        });
        Schema::dropIfExists('fabricantes');
        Schema::dropIfExists('categorias');
        Schema::table('veiculos', fn (Blueprint $t) => $t->dropColumn(['renavam', 'motor']));
    }
};
