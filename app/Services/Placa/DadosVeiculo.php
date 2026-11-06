<?php

namespace App\Services\Placa;

/** Dados do veículo devolvidos por um provedor de consulta de placa. */
final class DadosVeiculo
{
    public function __construct(
        public readonly string $placa,
        public readonly ?string $marca = null,
        public readonly ?string $modelo = null,
        public readonly ?string $versao = null,
        public readonly ?int $anoFabricacao = null,
        public readonly ?int $anoModelo = null,
        public readonly ?string $cor = null,
        public readonly ?string $combustivel = null,
        public readonly ?string $fipeCodigo = null,
        public readonly ?int $fipeValorCentavos = null,
        public readonly ?string $municipio = null,
        public readonly ?string $uf = null,
        public readonly bool $rouboFurto = false,
    ) {}

    /** Campos prontos para preencher um Veiculo. */
    public function paraVeiculo(): array
    {
        return array_filter([
            'marca' => $this->marca,
            'modelo' => $this->modelo,
            'versao' => $this->versao,
            'ano_fabricacao' => $this->anoFabricacao,
            'ano_modelo' => $this->anoModelo,
            'cor' => $this->cor,
            'combustivel' => $this->combustivel,
            'fipe_codigo' => $this->fipeCodigo,
            'fipe_valor_centavos' => $this->fipeValorCentavos,
        ], fn ($v) => $v !== null && $v !== '');
    }

    public function toArray(): array
    {
        return get_object_vars($this);
    }

    public static function fromArray(array $a): self
    {
        return new self(
            placa: $a['placa'],
            marca: $a['marca'] ?? null,
            modelo: $a['modelo'] ?? null,
            versao: $a['versao'] ?? null,
            anoFabricacao: $a['anoFabricacao'] ?? null,
            anoModelo: $a['anoModelo'] ?? null,
            cor: $a['cor'] ?? null,
            combustivel: $a['combustivel'] ?? null,
            fipeCodigo: $a['fipeCodigo'] ?? null,
            fipeValorCentavos: $a['fipeValorCentavos'] ?? null,
            municipio: $a['municipio'] ?? null,
            uf: $a['uf'] ?? null,
            rouboFurto: (bool) ($a['rouboFurto'] ?? false),
        );
    }
}
