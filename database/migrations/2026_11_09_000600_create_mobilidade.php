<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('empresas', function (Blueprint $t) {
            $t->boolean('mobilidade_exige_cnh')->default(false);
            $t->boolean('mobilidade_exige_oficial')->default(false);
            $t->boolean('mobilidade_bloqueia_divergencia')->default(true);
        });
        Schema::create('mobilidade_condutores', function (Blueprint $t) {
            $t->id();
            $t->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $t->string('nome', 120);
            $t->text('cpf')->nullable();
            $t->text('registro_cnh')->nullable();
            $t->text('contato')->nullable();
            $t->string('categoria', 4)->nullable();
            $t->date('validade')->nullable();
            $t->softDeletes();
            $t->timestamps();
        });
        Schema::table('conferencias_cnh', function (Blueprint $t) {
            $t->foreignId('condutor_id')->nullable()->constrained('mobilidade_condutores')->nullOnDelete();
            $t->json('evidencias')->nullable();
        });
        Schema::create('mobilidade_retiradas', function (Blueprint $t) {
            $t->id();
            $t->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $t->foreignId('ordem_servico_id')->constrained('ordens_servico')->cascadeOnDelete();
            $t->foreignId('condutor_id')->constrained('mobilidade_condutores')->restrictOnDelete();
            $t->foreignId('conferencia_id')->nullable()->constrained('conferencias_cnh')->nullOnDelete();
            $t->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->boolean('terceiro')->default(false);
            $t->text('autorizacao')->nullable();
            $t->text('justificativa')->nullable();
            $t->string('aceite_nome', 120);
            $t->text('checklist')->nullable();
            $t->timestamp('entregue_em')->nullable();
            $t->timestamps();
            $t->index(['ordem_servico_id', 'id']);
        });
        Schema::create('mobilidade_frota', function (Blueprint $t) {
            $t->id();
            $t->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $t->string('placa', 7);
            $t->string('descricao', 120);
            $t->unsignedInteger('km')->default(0);
            $t->string('situacao', 16)->default('disponivel');
            $t->text('manutencao')->nullable();
            $t->timestamps();
            $t->unique(['empresa_id', 'placa']);
        });
        Schema::create('mobilidade_reservas', function (Blueprint $t) {
            $t->id();
            $t->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $t->foreignId('ordem_servico_id')->constrained('ordens_servico')->restrictOnDelete();
            $t->foreignId('frota_id')->nullable()->constrained('mobilidade_frota')->restrictOnDelete();
            $t->foreignId('condutor_id')->constrained('mobilidade_condutores')->restrictOnDelete();
            $t->foreignId('conta_receber_id')->nullable()->constrained('contas_receber')->nullOnDelete();
            $t->string('tipo', 12);
            $t->string('parceira', 120)->nullable();
            $t->string('contrato_externo', 120)->nullable();
            $t->dateTime('inicio');
            $t->dateTime('fim');
            $t->string('status', 12)->default('reservada');
            $t->unsignedInteger('diaria_centavos')->default(0);
            $t->unsignedInteger('taxas_centavos')->default(0);
            $t->unsignedInteger('caucao_centavos')->default(0);
            $t->json('entrega')->nullable();
            $t->json('devolucao')->nullable();
            $t->text('ocorrencias')->nullable();
            $t->timestamps();
            $t->index(['frota_id', 'status', 'inicio']);
        });
        Schema::create('mobilidade_modelos', function (Blueprint $t) {
            $t->id();
            $t->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $t->string('tipo', 24);
            $t->text('texto');
            $t->timestamps();
            $t->unique(['empresa_id', 'tipo']);
        });
        Schema::create('mobilidade_documentos', function (Blueprint $t) {
            $t->id();
            $t->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $t->foreignId('reserva_id')->nullable()->constrained('mobilidade_reservas')->cascadeOnDelete();
            $t->foreignId('retirada_id')->nullable()->constrained('mobilidade_retiradas')->cascadeOnDelete();
            $t->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->string('tipo', 24);
            $t->text('texto');
            $t->string('aceite_nome', 120);
            $t->timestamps();
        });
        Schema::create('mobilidade_fotos', function (Blueprint $t) {
            $t->id();
            $t->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $t->foreignId('reserva_id')->nullable()->constrained('mobilidade_reservas')->cascadeOnDelete();
            $t->foreignId('retirada_id')->nullable()->constrained('mobilidade_retiradas')->cascadeOnDelete();
            $t->string('etapa', 12);
            $t->string('caminho');
            $t->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['mobilidade_fotos', 'mobilidade_documentos', 'mobilidade_modelos', 'mobilidade_reservas', 'mobilidade_frota', 'mobilidade_retiradas'] as $t) {
            Schema::dropIfExists($t);
        }
        Schema::table('conferencias_cnh', function (Blueprint $t) {
            $t->dropConstrainedForeignId('condutor_id');
            $t->dropColumn('evidencias');
        });
        Schema::dropIfExists('mobilidade_condutores');
        Schema::table('empresas', fn (Blueprint $t) => $t->dropColumn(['mobilidade_exige_cnh', 'mobilidade_exige_oficial', 'mobilidade_bloqueia_divergencia']));
    }
};
