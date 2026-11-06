<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Etapa 3: produtos, fornecedores, movimentos de estoque e entrada de notas de compra. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fornecedores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained()->cascadeOnDelete();
            $table->string('nome', 150);
            $table->string('cpf_cnpj', 14)->nullable();
            $table->string('telefone', 20)->nullable();
            $table->string('email')->nullable();
            $table->string('contato', 80)->nullable();
            $table->string('cidade', 80)->nullable();
            $table->char('uf', 2)->nullable();
            $table->text('observacoes')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['empresa_id', 'cpf_cnpj']);
        });

        Schema::create('produtos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained()->cascadeOnDelete();
            $table->string('codigo', 30)->nullable();
            $table->string('codigo_barras', 14)->nullable();
            $table->string('nome', 150);
            $table->string('marca', 60)->nullable();
            $table->string('unidade', 6)->default('UN');
            $table->string('ncm', 8)->nullable();
            $table->string('localizacao', 40)->nullable();
            $table->unsignedInteger('custo_centavos')->default(0);
            $table->unsignedInteger('preco_centavos')->default(0);
            $table->decimal('estoque', 12, 3)->default(0);
            $table->decimal('estoque_minimo', 12, 3)->default(0);
            $table->boolean('ativo')->default(true);
            $table->timestamps();
            $table->softDeletes();
            $table->index(['empresa_id', 'nome']);
            $table->index(['empresa_id', 'codigo_barras']);
            $table->index(['empresa_id', 'codigo']);
        });

        // "Este código do fornecedor X é o nosso produto Y" (aprende na primeira nota)
        Schema::create('produto_fornecedor_codigos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained()->cascadeOnDelete();
            $table->foreignId('fornecedor_id')->constrained('fornecedores')->cascadeOnDelete();
            $table->foreignId('produto_id')->constrained('produtos')->cascadeOnDelete();
            $table->string('codigo', 60);
            $table->timestamps();
            $table->unique(['empresa_id', 'fornecedor_id', 'codigo']);
        });

        Schema::create('estoque_movimentos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained()->cascadeOnDelete();
            $table->foreignId('produto_id')->constrained('produtos')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('tipo', 10); // entrada | saida | ajuste
            $table->decimal('quantidade', 12, 3); // positivo entra, negativo sai
            $table->decimal('saldo', 12, 3);
            $table->unsignedInteger('custo_unitario_centavos')->nullable();
            $table->string('origem', 20); // compra | os | ajuste | manual
            $table->unsignedBigInteger('origem_id')->nullable();
            $table->string('observacao', 255)->nullable();
            $table->timestamp('created_at')->nullable();
            $table->index(['empresa_id', 'produto_id']);
        });

        Schema::create('compras', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained()->cascadeOnDelete();
            $table->foreignId('fornecedor_id')->nullable()->constrained('fornecedores')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('numero', 20)->nullable();
            $table->string('serie', 5)->nullable();
            $table->string('chave', 44)->nullable();
            $table->date('emissao')->nullable();
            $table->unsignedInteger('total_centavos')->default(0);
            $table->json('duplicatas')->nullable();
            $table->string('status', 12)->default('rascunho'); // rascunho | lancada
            $table->string('xml_caminho')->nullable();
            $table->dateTime('lancada_em')->nullable();
            $table->text('observacoes')->nullable();
            $table->timestamps();
            $table->unique(['empresa_id', 'chave']);
        });

        Schema::create('compra_itens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained()->cascadeOnDelete();
            $table->foreignId('compra_id')->constrained('compras')->cascadeOnDelete();
            $table->foreignId('produto_id')->nullable()->constrained('produtos')->nullOnDelete();
            $table->string('codigo_fornecedor', 60)->nullable();
            $table->string('codigo_barras', 14)->nullable();
            $table->string('descricao', 200);
            $table->string('ncm', 8)->nullable();
            $table->string('unidade', 6)->nullable();
            $table->decimal('quantidade', 12, 3);
            $table->unsignedInteger('custo_unitario_centavos');
            $table->unsignedInteger('total_centavos');
            $table->timestamps();
        });

        Schema::table('ordem_servico_itens', function (Blueprint $table) {
            $table->foreignId('produto_id')->nullable()->after('servico_id')->constrained('produtos')->nullOnDelete();
            $table->boolean('estoque_baixado')->default(false)->after('situacao');
        });
    }

    public function down(): void
    {
        Schema::table('ordem_servico_itens', function (Blueprint $table) {
            $table->dropConstrainedForeignId('produto_id');
            $table->dropColumn('estoque_baixado');
        });
        Schema::dropIfExists('compra_itens');
        Schema::dropIfExists('compras');
        Schema::dropIfExists('estoque_movimentos');
        Schema::dropIfExists('produto_fornecedor_codigos');
        Schema::dropIfExists('produtos');
        Schema::dropIfExists('fornecedores');
    }
};
