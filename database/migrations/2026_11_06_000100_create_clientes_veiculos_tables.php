<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clientes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained()->cascadeOnDelete();
            $table->char('tipo_pessoa', 2)->default('PF');
            $table->string('nome');
            $table->string('cpf_cnpj', 14)->nullable();
            $table->string('ie', 20)->nullable();
            $table->string('telefone', 20)->nullable();
            $table->string('whatsapp', 20)->nullable();
            $table->boolean('aceita_whatsapp')->default(true);
            $table->string('email')->nullable();
            $table->string('cep', 8)->nullable();
            $table->string('logradouro')->nullable();
            $table->string('numero', 20)->nullable();
            $table->string('complemento', 80)->nullable();
            $table->string('bairro', 80)->nullable();
            $table->string('cidade', 80)->nullable();
            $table->char('uf', 2)->nullable();
            $table->date('nascimento')->nullable();
            $table->text('observacoes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['empresa_id', 'cpf_cnpj']);
            $table->index(['empresa_id', 'nome']);
        });

        Schema::create('veiculos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained()->cascadeOnDelete();
            $table->foreignId('cliente_id')->nullable()->constrained('clientes')->nullOnDelete();
            $table->string('placa', 7);
            $table->string('marca', 60)->nullable();
            $table->string('modelo', 80)->nullable();
            $table->string('versao', 120)->nullable();
            $table->unsignedSmallInteger('ano_fabricacao')->nullable();
            $table->unsignedSmallInteger('ano_modelo')->nullable();
            $table->string('cor', 30)->nullable();
            $table->string('combustivel', 30)->nullable();
            $table->string('chassi', 17)->nullable();
            $table->unsignedInteger('km_atual')->nullable();
            $table->string('fipe_codigo', 12)->nullable();
            $table->unsignedInteger('fipe_valor_centavos')->nullable();
            $table->text('observacoes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['empresa_id', 'placa']);
        });

        Schema::create('veiculo_donos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained()->cascadeOnDelete();
            $table->foreignId('veiculo_id')->constrained()->cascadeOnDelete();
            $table->foreignId('cliente_id')->constrained('clientes')->cascadeOnDelete();
            $table->date('inicio');
            $table->date('fim')->nullable();
            $table->timestamps();
        });

        Schema::create('placa_consultas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('placa', 7);
            $table->string('provedor', 30);
            $table->string('status', 20);
            $table->json('resposta')->nullable();
            $table->unsignedInteger('custo_centavos')->default(0);
            $table->timestamps();

            $table->index(['empresa_id', 'placa', 'created_at']);
        });

        Schema::create('auditoria', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('acao', 30);
            $table->string('modelo', 60);
            $table->unsignedBigInteger('modelo_id')->nullable();
            $table->json('antes')->nullable();
            $table->json('depois')->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['empresa_id', 'modelo', 'modelo_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('auditoria');
        Schema::dropIfExists('placa_consultas');
        Schema::dropIfExists('veiculo_donos');
        Schema::dropIfExists('veiculos');
        Schema::dropIfExists('clientes');
    }
};
