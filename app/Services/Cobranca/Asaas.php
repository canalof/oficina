<?php

namespace App\Services\Cobranca;

use App\Models\Empresa;
use App\Models\Plano;
use DomainException;
use Illuminate\Support\Facades\Http;

/** Assinatura recorrente no Asaas (API v3). */
class Asaas implements Gateway
{
    public function nome(): string
    {
        return 'asaas';
    }

    private function http()
    {
        $chave = config('cobranca.asaas.chave');
        if (! $chave) {
            throw new DomainException('Cobrança não configurada (ASAAS_API_KEY).');
        }

        return Http::baseUrl(rtrim(config('cobranca.asaas.url'), '/'))
            ->withHeaders(['access_token' => $chave, 'User-Agent' => 'Torque'])
            ->acceptJson()->asJson()->timeout(30);
    }

    public function assinar(Empresa $empresa, Plano $plano, string $email): array
    {
        $cliente = $empresa->cobranca_cliente_id;
        if (! $cliente) {
            $r = $this->http()->post('/customers', array_filter([
                'name' => $empresa->razao_social ?: $empresa->nome_fantasia,
                'cpfCnpj' => $empresa->cnpj,
                'email' => $email,
                'mobilePhone' => preg_replace('/\D/', '', (string) $empresa->telefone) ?: null,
                'externalReference' => 'empresa:'.$empresa->id,
            ]));
            $this->falhou($r, 'cadastrar o cliente');
            $cliente = $r->json('id');
        }

        $dados = [
            'customer' => $cliente,
            'billingType' => config('cobranca.asaas.forma'),
            'value' => round($plano->preco_centavos / 100, 2),
            'cycle' => 'MONTHLY',
            'description' => 'Torque - plano '.$plano->nome,
            'externalReference' => 'empresa:'.$empresa->id,
        ];
        if ($empresa->cobranca_assinatura_id) {
            // Troca de plano: atualiza o valor das próximas cobranças
            $r = $this->http()->post('/subscriptions/'.$empresa->cobranca_assinatura_id, $dados + ['updatePendingPayments' => true]);
            $this->falhou($r, 'trocar o plano');

            return ['cliente_id' => $cliente, 'assinatura_id' => $empresa->cobranca_assinatura_id];
        }
        $vencimento = $empresa->trial_ate && $empresa->trial_ate->isFuture() ? $empresa->trial_ate : today()->addDay();
        $r = $this->http()->post('/subscriptions', $dados + ['nextDueDate' => $vencimento->toDateString()]);
        $this->falhou($r, 'criar a assinatura');

        return ['cliente_id' => $cliente, 'assinatura_id' => $r->json('id')];
    }

    public function cancelar(Empresa $empresa): void
    {
        if ($empresa->cobranca_assinatura_id) {
            $this->falhou($this->http()->delete('/subscriptions/'.$empresa->cobranca_assinatura_id), 'cancelar a assinatura');
        }
    }

    private function falhou($r, string $acao): void
    {
        if (! $r->successful()) {
            $msg = collect($r->json('errors') ?? [])->pluck('description')->join(' ');
            throw new DomainException('Não foi possível '.$acao.' no Asaas'.($msg ? ': '.$msg : ' (HTTP '.$r->status().').'));
        }
    }
}
