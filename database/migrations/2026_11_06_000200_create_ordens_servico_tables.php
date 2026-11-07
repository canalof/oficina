<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Catálogo de serviços com preço padrão
        Schema::create('servicos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained()->cascadeOnDelete();
            $table->string('nome', 120);
            $table->unsignedInteger('preco_centavos')->default(0);
            $table->unsignedSmallInteger('tempo_minutos')->nullable();
            $table->unsignedSmallInteger('garantia_dias')->nullable();
            $table->boolean('ativo')->default(true);
            $table->timestamps();
            $table->softDeletes();
            $table->index(['empresa_id', 'nome']);
        });

        Schema::create('ordens_servico', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('numero');
            $table->foreignId('veiculo_id')->constrained('veiculos');
            $table->foreignId('cliente_id')->nullable()->constrained('clientes')->nullOnDelete();
            $table->foreignId('consultor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('mecanico_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 20)->default('orcamento');
            $table->unsignedInteger('km_entrada')->nullable();
            $table->unsignedTinyInteger('combustivel')->nullable(); // 0 a 4 (vazio, 1/4, 1/2, 3/4, cheio)
            $table->text('defeito_relatado')->nullable();
            $table->text('diagnostico')->nullable();
            $table->text('observacoes')->nullable();
            $table->json('checklist')->nullable();
            $table->unsignedInteger('desconto_centavos')->default(0);
            $table->unsignedInteger('total_centavos')->default(0);
            $table->dateTime('previsao_entrega')->nullable();
            $table->unsignedSmallInteger('garantia_dias')->default(90);
            $table->string('token', 48)->unique();
            $table->dateTime('enviado_em')->nullable();
            $table->dateTime('aprovado_em')->nullable();
            $table->string('aprovado_por', 120)->nullable();
            $table->string('aprovado_ip', 45)->nullable();
            $table->dateTime('iniciado_em')->nullable();
            $table->dateTime('concluido_em')->nullable();
            $table->dateTime('entregue_em')->nullable();
            $table->dateTime('cancelado_em')->nullable();
            $table->string('motivo_cancelamento', 255)->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['empresa_id', 'numero']);
            $table->index(['empresa_id', 'status']);
        });

        Schema::create('ordem_servico_itens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ordem_servico_id')->constrained('ordens_servico')->cascadeOnDelete();
            $table->string('tipo', 10); // servico | peca
            $table->foreignId('servico_id')->nullable()->constrained('servicos')->nullOnDelete();
            $table->string('descricao', 200);
            $table->decimal('quantidade', 10, 3)->default(1);
            $table->unsignedInteger('valor_unitario_centavos')->default(0);
            $table->unsignedInteger('total_centavos')->default(0);
            $table->string('situacao', 10)->default('pendente'); // pendente | aprovado | recusado
            $table->foreignId('mecanico_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('ordem_servico_fotos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ordem_servico_id')->constrained('ordens_servico')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('caminho');
            $table->string('legenda', 160)->nullable();
            $table->timestamps();
        });

        // Linha do tempo da OS (o que o cliente e a equipe veem acontecer)
        Schema::create('ordem_servico_eventos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ordem_servico_id')->constrained('ordens_servico')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('descricao', 255);
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ordem_servico_eventos');
        Schema::dropIfExists('ordem_servico_fotos');
        Schema::dropIfExists('ordem_servico_itens');
        Schema::dropIfExists('ordens_servico');
        Schema::dropIfExists('servicos');
    }
};
