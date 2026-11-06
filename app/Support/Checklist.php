<?php

namespace App\Support;

/** Itens conferidos na entrada do carro. */
class Checklist
{
    public const ITENS = [
        'parachoque_dianteiro' => 'Para-choque dianteiro',
        'parachoque_traseiro' => 'Para-choque traseiro',
        'lateral_esquerda' => 'Lateral esquerda',
        'lateral_direita' => 'Lateral direita',
        'capo_teto' => 'Capô e teto',
        'vidros' => 'Vidros e para-brisa',
        'farois' => 'Faróis e lanternas',
        'retrovisores' => 'Retrovisores',
        'rodas' => 'Rodas e calotas',
        'pneus' => 'Pneus',
        'estepe' => 'Estepe',
        'ferramentas' => 'Macaco, chave de roda e triângulo',
        'tapetes' => 'Tapetes',
        'multimidia' => 'Som / multimídia',
        'painel' => 'Luzes de alerta no painel',
    ];

    public const ESTADOS = ['ok' => 'OK', 'avaria' => 'Avaria', 'nao_tem' => 'Não tem'];

    public const COMBUSTIVEL = [0 => 'Reserva', 1 => '1/4', 2 => '1/2', 3 => '3/4', 4 => 'Cheio'];

    /** Normaliza o que veio do formulário: ['vidros' => ['estado' => 'avaria', 'obs' => 'trinca'], ...] */
    public static function limpar(?array $entrada): array
    {
        $saida = [];
        foreach (self::ITENS as $chave => $rotulo) {
            $e = $entrada[$chave] ?? [];
            $estado = $e['estado'] ?? null;
            if (! isset(self::ESTADOS[$estado])) {
                continue;
            }
            $obs = trim((string) ($e['obs'] ?? ''));
            $saida[$chave] = ['estado' => $estado, 'obs' => mb_substr($obs, 0, 160) ?: null];
        }

        return $saida;
    }

    /** @return list<array{rotulo:string,obs:?string}> */
    public static function avarias(?array $checklist): array
    {
        $r = [];
        foreach ($checklist ?? [] as $chave => $e) {
            if (($e['estado'] ?? null) === 'avaria') {
                $r[] = ['rotulo' => self::ITENS[$chave] ?? $chave, 'obs' => $e['obs'] ?? null];
            }
        }

        return $r;
    }
}
