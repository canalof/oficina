# Torque: convenções do projeto

Sistema de gestão para autocenters (Laravel 13, PHP 8.3, Blade + Tailwind v4, MySQL em produção, SQLite nos testes).

## Regras que não podem ser quebradas

- **Toda tabela de dados de oficina tem `empresa_id`** e o model usa o trait `PertenceAEmpresa`. Sem empresa definida (`Tenant::id()` nulo), consultas não retornam nada e gravações lançam exceção. Nunca use `withoutGlobalScopes()` em código de tela. Exceções documentadas: busca por token público (`OrdemServico::porToken`, portal do cliente), que em seguida fixa a empresa com `Tenant::usar`.
- Validações de `exists`/`unique` em formulários precisam do filtro `->where('empresa_id', Tenant::id())`.
- Models com dados de negócio usam `Auditavel`.
- Permissões ficam em `App\Support\Perfil::permissoes()` e viram Gates (`can:clientes.editar`). Controller chama `$this->authorize(...)`.
- Placa sempre passa por `Placa::normalizar()`; busca por placa usa `Placa::equivalentes()` (antiga e Mercosul).
- CPF/CNPJ são guardados só com números/letras (`Documento::normalizar`).
- Chaves de API só no `.env`, nunca no front. Exceção: o token fiscal de cada oficina fica em `empresas.fiscal_token` com cast `encrypted` e nunca volta para a tela.
- Valores em dinheiro em centavos (inteiro), colunas terminadas em `_centavos`.

## Idioma

Código de domínio, telas, mensagens e testes em português. Textos de tela curtos, verbo no infinitivo nos botões ("Salvar cliente"), sem jargão técnico.

## Testes

`vendor/bin/phpunit`. Helpers em `tests/TestCase.php`: `novaEmpresa()` e `usuarioNaMesmaEmpresa()`. Toda funcionalidade nova precisa de teste de isolamento entre empresas.

## Visual

Tokens em `resources/css/app.css` (`asfalto`, `sinal`, `placa`...). Amarelo `sinal` só para o item ativo, foco e a ação principal da recepção. Componente `<x-placa>` para mostrar placas.

## Módulos

- Estoque: `Produto::movimentar()` é o único jeito de mexer no saldo (grava o histórico). Notas de compra: `Services/Estoque`.
- Dinheiro: `Services/Financeiro/Financeiro` (receber OS, receber/pagar contas, caixa).
- Entrega da OS: `Services/Os/AoEntregar` (baixa estoque, comissão, lembretes).
- Notas fiscais: `Services/Fiscal` (Focus NFe ou demonstração).
- WhatsApp automático: `Services/WhatsApp` (Evolution API, opcional).
- Agendador: `routes/console.php` (`torque:notas` a cada 5 min, `torque:lembretes` às 9h).
- Em Blade, use sempre `@php ... @endphp` (não misture com `@php(...)` no mesmo arquivo).
- Plataforma SaaS: `Services/Cobranca` (Asaas ou demonstração), middleware `VerificarAssinatura`, painel `/plataforma` (gate `plataforma` = admin + 2 etapas). O painel não abre dados internos das oficinas.
- Orçamento: toda mudança de itens/desconto chama `OrdemServico::alterouOrcamento()` (versão). Aceite fica em `orcamento_aceites` (imutável, com hash).
- Documentação de operação em `docs/` (permissões, testes, runbook, auditoria). Atualize-a junto com o código.
