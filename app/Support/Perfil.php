<?php

namespace App\Support;

/**
 * Perfis de usuário e o que cada um pode fazer.
 * As permissões viram Gates em AppServiceProvider (ex.: @can('clientes.editar')).
 */
enum Perfil: string
{
    case Dono = 'dono';
    case Gerente = 'gerente';
    case Consultor = 'consultor';
    case Mecanico = 'mecanico';
    case Caixa = 'caixa';
    case Estoquista = 'estoquista';

    public function rotulo(): string
    {
        return match ($this) {
            self::Dono => 'Dono',
            self::Gerente => 'Gerente',
            self::Consultor => 'Consultor / recepção',
            self::Mecanico => 'Mecânico',
            self::Caixa => 'Caixa',
            self::Estoquista => 'Estoquista',
        };
    }

    /** @return list<string> */
    public function permissoes(): array
    {
        return match ($this) {
            self::Dono => ['*'],
            self::Gerente => ['clientes.ver', 'clientes.editar', 'clientes.excluir', 'veiculos.ver', 'veiculos.editar', 'veiculos.excluir', 'placa.consultar', 'os.ver', 'os.editar', 'os.executar', 'os.cancelar', 'servicos.gerenciar', 'usuarios.gerenciar', 'auditoria.ver', 'estoque.ver', 'estoque.editar', 'compras.lancar', 'caixa.operar', 'financeiro.ver', 'financeiro.editar', 'comissoes.ver', 'notas.emitir', 'agenda.ver', 'agenda.editar'],
            self::Consultor => ['clientes.ver', 'clientes.editar', 'veiculos.ver', 'veiculos.editar', 'placa.consultar', 'os.ver', 'os.editar', 'os.executar', 'estoque.ver', 'caixa.operar', 'notas.emitir', 'agenda.ver', 'agenda.editar'],
            self::Mecanico => ['clientes.ver', 'veiculos.ver', 'os.ver', 'os.executar', 'estoque.ver', 'agenda.ver'],
            self::Caixa => ['clientes.ver', 'clientes.editar', 'veiculos.ver', 'os.ver', 'caixa.operar', 'financeiro.ver', 'financeiro.editar', 'notas.emitir', 'agenda.ver', 'agenda.editar'],
            self::Estoquista => ['veiculos.ver', 'os.ver', 'estoque.ver', 'estoque.editar', 'compras.lancar'],
        };
    }

    public function pode(string $permissao): bool
    {
        $p = $this->permissoes();

        return in_array('*', $p, true) || in_array($permissao, $p, true);
    }

    /** Todas as permissões usadas no sistema (para registrar os Gates). */
    public static function todasPermissoes(): array
    {
        return [
            'clientes.ver', 'clientes.editar', 'clientes.excluir',
            'veiculos.ver', 'veiculos.editar', 'veiculos.excluir',
            'placa.consultar', 'usuarios.gerenciar', 'empresa.configurar', 'auditoria.ver',
            'os.ver', 'os.editar', 'os.executar', 'os.cancelar', 'servicos.gerenciar',
            'estoque.ver', 'estoque.editar', 'compras.lancar', 'caixa.operar', 'financeiro.ver', 'financeiro.editar',
            'comissoes.ver', 'notas.emitir', 'agenda.ver', 'agenda.editar',
        ];
    }

    public static function opcoes(): array
    {
        $o = [];
        foreach (self::cases() as $c) {
            $o[$c->value] = $c->rotulo();
        }

        return $o;
    }
}
