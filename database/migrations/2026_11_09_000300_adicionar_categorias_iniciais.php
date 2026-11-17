<?php

use App\Models\Categoria;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('empresas')
            ->whereNotExists(fn ($query) => $query->selectRaw('1')->from('users')
                ->whereColumn('users.empresa_id', 'empresas.id')->where('users.admin_plataforma', true))
            ->orderBy('id')->select('id')->chunkById(200, function ($empresas) {
                foreach ($empresas as $empresa) {
                    Categoria::criarPadroes($empresa->id);
                }
            });
    }

    public function down(): void
    {
        // Categorias podem estar em uso ou ter sido personalizadas pela oficina.
    }
};
