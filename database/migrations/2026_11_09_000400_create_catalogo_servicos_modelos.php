<?php

use App\Models\CategoriaServico;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categorias_servico', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->string('nome', 80);
            $table->boolean('ativo')->default(true);
            $table->timestamps();
            $table->unique(['empresa_id', 'nome']);
        });
        Schema::table('servicos', function (Blueprint $table) {
            $table->foreignId('categoria_servico_id')->nullable()->constrained('categorias_servico')->nullOnDelete();
        });
        Schema::create('modelos_os', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->string('nome', 120);
            $table->json('servico_ids');
            $table->json('produto_ids');
            $table->boolean('ativo')->default(true);
            $table->timestamps();
            $table->unique(['empresa_id', 'nome']);
        });
        DB::table('empresas')->whereNotExists(fn ($q) => $q->selectRaw('1')->from('users')
            ->whereColumn('users.empresa_id', 'empresas.id')->where('users.admin_plataforma', true))
            ->select('id')->chunkById(200, function ($empresas) {
                foreach ($empresas as $empresa) {
                    CategoriaServico::criarPadroes($empresa->id);
                }
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('modelos_os');
        Schema::table('servicos', fn (Blueprint $table) => $table->dropConstrainedForeignId('categoria_servico_id'));
        Schema::dropIfExists('categorias_servico');
    }
};
