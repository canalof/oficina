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
            self::Gerente => ['clientes.ver', 'clientes.editar', 'clientes.excluir', 'veiculos.ver', 'veiculos.editar', 'veiculos.excluir', 'placa.consultar', 'usuarios.gerenciar', 'auditoria.ver'],
            self::Consultor => ['clientes.ver', 'clientes.editar', 'veiculos.ver', 'veiculos.editar', 'placa.consultar'],
            self::Mecanico => ['clientes.ver', 'veiculos.ver'],
            self::Caixa => ['clientes.ver', 'clientes.editar', 'veiculos.ver'],
            self::Estoquista => ['veiculos.ver'],
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
