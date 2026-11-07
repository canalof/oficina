<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/** Planos iniciais (preços e limites podem ser mudados no painel da plataforma). */
return new class extends Migration
{
    public function up(): void
    {
        $agora = now();
        DB::table('planos')->insert([
            ['nome' => 'Essencial', 'slug' => 'essencial', 'preco_centavos' => 19900, 'limite_usuarios' => 3, 'limite_lojas' => 1, 'limite_consultas_placa' => 100, 'whatsapp_automatico' => false, 'ativo' => true, 'ordem' => 1, 'created_at' => $agora, 'updated_at' => $agora],
            ['nome' => 'Profissional', 'slug' => 'profissional', 'preco_centavos' => 27900, 'limite_usuarios' => 8, 'limite_lojas' => 1, 'limite_consultas_placa' => 300, 'whatsapp_automatico' => true, 'ativo' => true, 'ordem' => 2, 'created_at' => $agora, 'updated_at' => $agora],
            ['nome' => 'Rede', 'slug' => 'rede', 'preco_centavos' => 44900, 'limite_usuarios' => null, 'limite_lojas' => 5, 'limite_consultas_placa' => 800, 'whatsapp_automatico' => true, 'ativo' => true, 'ordem' => 3, 'created_at' => $agora, 'updated_at' => $agora],
        ]);
        // Empresas que já existiam: plano Profissional em teste
        $prof = DB::table('planos')->where('slug', 'profissional')->value('id');
        DB::table('empresas')->whereNull('plano_id')->update(['plano_id' => $prof, 'trial_ate' => $agora->copy()->addDays(14)->toDateString()]);
    }

    public function down(): void
    {
        DB::table('empresas')->update(['plano_id' => null]);
        DB::table('planos')->whereIn('slug', ['essencial', 'profissional', 'rede'])->delete();
    }
};
