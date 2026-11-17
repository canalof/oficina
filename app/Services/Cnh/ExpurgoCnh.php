<?php

namespace App\Services\Cnh;

use App\Models\Auditoria;
use App\Models\CnhConsulta;
use App\Models\Empresa;
use App\Support\Tenant;
use Illuminate\Database\Eloquent\Builder;

/**
 * TORQUE-CNH-249: expurgo de evidências de consulta de CNH por empresa.
 *
 * Regras:
 * - simulação é o padrão; só `aplicar = true` exclui linhas;
 * - o prazo efetivo vem de PoliticaRetencaoCnh (empresa -> instalação -> técnico);
 * - empresa sem política definida é ignorada, salvo autorização explícita para
 *   usar o limite técnico;
 * - nunca abaixo do mínimo legal de 30 dias;
 * - linhas antigas vinculadas a conferência oficial (evidencias.conferencia_id)
 *   ou a ordem de serviço são preservadas, porque instruem outro registro;
 * - exclusão em lotes por id, sempre restrita à empresa, com trilha em auditoria.
 */
class ExpurgoCnh
{
    public function __construct(private PoliticaRetencaoCnh $politica) {}

    /**
     * @return array{modo:string, empresas:list<array<string,mixed>>, removidos:int, elegiveis:int}
     */
    public function executar(bool $aplicar = false, ?int $empresaId = null, bool $permitirLimiteTecnico = false): array
    {
        $empresas = Empresa::query()
            ->select(['id', 'nome_fantasia', 'cnh_retencao_dias'])
            ->when($empresaId !== null, fn (Builder $consulta) => $consulta->whereKey($empresaId))
            ->orderBy('id')
            ->get();

        $saida = [
            'modo' => $aplicar ? 'aplicar' : 'simulacao',
            'empresas' => [],
            'removidos' => 0,
            'elegiveis' => 0,
        ];

        foreach ($empresas as $empresa) {
            $politica = $this->politica->efetiva($empresa);
            $linha = [
                'empresa_id' => (int) $empresa->id,
                'nome' => (string) $empresa->nome_fantasia,
                'dias' => (int) $politica['dias'],
                'origem' => $politica['origem'],
                'definida' => (bool) $politica['definida'],
                'limite' => null,
                'antigos' => 0,
                'elegiveis' => 0,
                'preservados' => 0,
                'recentes' => 0,
                'removidos' => 0,
                'situacao' => 'ignorada',
                'motivo' => null,
            ];

            if (! $politica['definida'] && ! $permitirLimiteTecnico) {
                $linha['motivo'] = 'politica_nao_definida';
                $saida['empresas'][] = $linha;

                continue;
            }

            $dias = max((int) $politica['dias'], (int) config('cnh.expurgo_minimo_dias', 30));
            $limite = now()->subDays($dias);
            $linha['dias'] = $dias;
            $linha['limite'] = $limite->toDateTimeString();
            $linha['situacao'] = $aplicar ? 'aplicado' : 'simulado';

            Tenant::com($empresa->id, function () use ($empresa, $limite, $aplicar, &$linha) {
                $antigos = fn (): Builder => CnhConsulta::query()
                    ->where('empresa_id', $empresa->id)
                    ->where('created_at', '<', $limite);

                $linha['recentes'] = CnhConsulta::query()
                    ->where('empresa_id', $empresa->id)
                    ->where('created_at', '>=', $limite)
                    ->count();
                $linha['antigos'] = $antigos()->count();
                $linha['elegiveis'] = $this->elegiveis($antigos())->count();
                $linha['preservados'] = $linha['antigos'] - $linha['elegiveis'];

                if ($aplicar && $linha['elegiveis'] > 0) {
                    $linha['removidos'] = $this->remover($empresa->id, $limite);

                    if ($linha['removidos'] > 0) {
                        Auditoria::create([
                            'empresa_id' => $empresa->id,
                            'user_id' => null,
                            'acao' => 'expurgou_consultas_cnh',
                            'modelo' => 'CnhConsulta',
                            'modelo_id' => null,
                            'antes' => null,
                            'depois' => [
                                'limite' => $limite->toDateTimeString(),
                                'dias' => $linha['dias'],
                                'origem' => $linha['origem'],
                                'removidos' => $linha['removidos'],
                                'preservados' => $linha['preservados'],
                                'modo' => 'aplicar',
                            ],
                            'ip' => null,
                        ]);
                    }
                }
            });

            $saida['empresas'][] = $linha;
            $saida['removidos'] += $linha['removidos'];
            $saida['elegiveis'] += $linha['elegiveis'];
        }

        return $saida;
    }

    /**
     * Restringe a consulta às linhas que podem ser expurgadas: já limitada por
     * empresa e por data, sem vínculo com conferência oficial nem com OS.
     */
    private function elegiveis(Builder $consulta): Builder
    {
        $regras = (array) config('cnh.expurgo_preservar', []);

        if (filter_var($regras['conferencia'] ?? true, FILTER_VALIDATE_BOOL)) {
            // JSON_VALID evita erro em linhas legadas com evidências malformadas.
            $consulta->whereRaw(
                "CASE WHEN JSON_VALID(evidencias) THEN JSON_EXTRACT(evidencias, '$.conferencia_id') END IS NULL"
            );
        }

        if (filter_var($regras['ordem_servico'] ?? true, FILTER_VALIDATE_BOOL)) {
            $consulta->whereNull('ordem_servico_id');
        }

        return $consulta;
    }

    /**
     * Exclui em lotes de id. O corte por data é reaplicado em cada lote para que
     * uma linha alterada durante a execução nunca seja atingida por engano.
     */
    private function remover(int $empresaId, \DateTimeInterface $limite): int
    {
        $lote = max(1, (int) config('cnh.expurgo_lote', 500));
        $removidos = 0;

        do {
            $ids = $this->elegiveis(
                CnhConsulta::query()
                    ->where('empresa_id', $empresaId)
                    ->where('created_at', '<', $limite)
            )->orderBy('id')->limit($lote)->pluck('id');

            if ($ids->isEmpty()) {
                break;
            }

            $removidos += CnhConsulta::query()
                ->where('empresa_id', $empresaId)
                ->where('created_at', '<', $limite)
                ->whereIn('id', $ids)
                ->delete();
        } while ($ids->count() === $lote);

        return $removidos;
    }
}
