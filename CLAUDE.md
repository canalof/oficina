# Torque: convenções do projeto

Sistema de gestão para autocenters (Laravel 13, PHP 8.3, Blade + Tailwind v4, MySQL em produção, SQLite nos testes).

## Regras que não podem ser quebradas

- **Toda tabela de dados de oficina tem `empresa_id`** e o model usa o trait `PertenceAEmpresa`. Sem empresa definida (`Tenant::id()` nulo), consultas não retornam nada e gravações lançam exceção. Nunca use `withoutGlobalScopes()` em código de tela.
- Validações de `exists`/`unique` em formulários precisam do filtro `->where('empresa_id', Tenant::id())`.
- Models com dados de negócio usam `Auditavel`.
- Permissões ficam em `App\Support\Perfil::permissoes()` e viram Gates (`can:clientes.editar`). Controller chama `$this->authorize(...)`.
- Placa sempre passa por `Placa::normalizar()`; busca por placa usa `Placa::equivalentes()` (antiga e Mercosul).
- CPF/CNPJ são guardados só com números/letras (`Documento::normalizar`).
- Chaves de API só no `.env`, nunca no front.
- Valores em dinheiro em centavos (inteiro), colunas terminadas em `_centavos`.

## Idioma

Código de domínio, telas, mensagens e testes em português. Textos de tela curtos, verbo no infinitivo nos botões ("Salvar cliente"), sem jargão técnico.

## Testes

`vendor/bin/phpunit`. Helpers em `tests/TestCase.php`: `novaEmpresa()` e `usuarioNaMesmaEmpresa()`. Toda funcionalidade nova precisa de teste de isolamento entre empresas.

## Visual

Tokens em `resources/css/app.css` (`asfalto`, `sinal`, `placa`...). Amarelo `sinal` só para o item ativo, foco e a ação principal da recepção. Componente `<x-placa>` para mostrar placas.
