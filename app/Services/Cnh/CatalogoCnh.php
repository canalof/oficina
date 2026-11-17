<?php

namespace App\Services\Cnh;

class CatalogoCnh
{
    public const FORNECEDORES = [
        'senatran' => ['SENATRAN / SERPRO', 'RENACH. Requer credenciamento e certificado mTLS.', 'https://centraldeajuda.serpro.gov.br/consultasenatran/comofunciona/'],
        'minha_cnh' => ['Minha CNH / Doutor Multas', 'Diagnóstico de CNH por API REST assíncrona (relatórios com webhook ou polling). Sandbox autoatendível; produção pré-paga por consulta. Sem conector nesta instalação.', 'https://minhacnh.doutormultas.com.br/docs/comecar/contratar'],
        'vio_serpro' => ['SERPRO / Vio Decoder', 'Decodificação documental. O teste de conexão valida apenas a autenticação OAuth.', 'https://centraldeajuda.serpro.gov.br/vio/comocontratar/'],
        'vio_privado' => ['VIO API privada', 'Processamento documental externo por imagem ou PDF. Requer validar autorização e tratamento dos arquivos.', 'https://vio.api.br/'],
    ];

    /** Fornecedores com conector implementado nesta instalação. */
    public const IMPLEMENTADOS = ['senatran' => SenatranByok::class, 'vio_serpro' => VioByok::class];

    /** Fornecedores que existem na tela de configuração mas não possuem conector. */
    public const PENDENTES = [
        'minha_cnh' => 'Sem conector: a contratação é autoatendível (sandbox e produção pré-paga), mas a integração ainda não foi implementada nem homologada.',
        'vio_privado' => 'Sem conector: depende de contrato e definição do tratamento dos arquivos enviados.',
    ];

    /** O que cada fornecedor efetivamente devolve, por dimensão. */
    public const DADOS = [
        'senatran' => ['autenticidade', 'validade', 'categoria', 'situacao', 'restricoes'],
        'vio_serpro' => ['autenticidade'],
        'minha_cnh' => [],
        'vio_privado' => [],
    ];

    public function existe(string $provedor): bool
    {
        return array_key_exists($provedor, self::FORNECEDORES);
    }

    public function implementado(string $provedor): bool
    {
        return array_key_exists($provedor, self::IMPLEMENTADOS);
    }

    public function nome(string $provedor): string
    {
        return self::FORNECEDORES[$provedor][0] ?? $provedor;
    }

    public function adaptador(string $provedor): ?ProvedorCnh
    {
        $classe = self::IMPLEMENTADOS[$provedor] ?? null;

        return $classe ? app($classe) : null;
    }

    /**
     * Situação do ambiente para o fornecedor: qual endereço será usado e se ele
     * está configurado. Não lança exceção, para poder alimentar a tela.
     *
     * @return array{ambiente: string, configurado: bool, endereco: ?string, mensagem: ?string}
     */
    public function ambiente(string $provedor): array
    {
        $ambiente = (string) config('cnh.ambiente', 'homologacao') === 'producao' ? 'producao' : 'homologacao';

        if (! $this->implementado($provedor)) {
            return ['ambiente' => $ambiente, 'configurado' => false, 'endereco' => null, 'mensagem' => 'Conector não implementado.'];
        }

        try {
            $endereco = $provedor === 'senatran'
                ? app(SenatranProvider::class)->endpoint($ambiente)
                : app(VioByok::class)->baseUrl($ambiente);

            return ['ambiente' => $ambiente, 'configurado' => true, 'endereco' => $endereco, 'mensagem' => null];
        } catch (\DomainException $erro) {
            return ['ambiente' => $ambiente, 'configurado' => false, 'endereco' => null, 'mensagem' => $erro->getMessage()];
        }
    }

    /** @return list<string> */
    public function dadosDisponiveis(string $provedor): array
    {
        return self::DADOS[$provedor] ?? [];
    }

    /**
     * Autenticidade declarada pelo adaptador, nunca pelo nome do fornecedor.
     * Fornecedor sem conector (minha_cnh, vio_privado) é sempre documental.
     */
    public function fonte(string $provedor): string
    {
        $adaptador = $this->adaptador($provedor);
        if (! $adaptador) {
            return 'documental';
        }

        $declarada = $adaptador->autenticidade();

        return $declarada === 'oficial' && $this->provedorOficial($provedor) ? 'oficial' : 'documental';
    }

    public function provedorOficial(string $provedor): bool
    {
        return in_array($provedor, AvaliacaoCnh::FONTES_OFICIAIS, true);
    }
}
