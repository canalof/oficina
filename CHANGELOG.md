# Changelog

## 1.2.0 — 06/11/2026

Fase 2 do escopo "Torque 2.0" (cadastros, estoque e OS). Situação item a item em `docs/auditoria.md`.

### Cadastros
- Botão **Ver histórico** em clientes, veículos, produtos, fornecedores, serviços, usuários e OS: quem mudou, quando, IP e cada campo antes → depois.
- Veículo com **Renavam** (dígito conferido) e **motor**.
- **Categorias** e **fabricantes** de produtos, com tela própria; marcas antigas viraram fabricantes automaticamente.
- **Referência do fabricante** no produto e lista fixa de **unidades** (UN, PC, JG, PAR, KIT, CX, L, ML, KG, G, M).
- **Aviso de cadastro duplicado** em clientes (nome ou telefone), produtos (código de barras, referência ou nome) e fornecedores; dá para confirmar e cadastrar mesmo assim.
- **Importar** clientes e produtos de planilha (CSV do Excel, com `;` ou `,`, acentos do Excel brasileiro). Atualiza quem já existe pelo CPF/CNPJ ou código, aponta a linha de cada erro.
- **Exportar** clientes, veículos, produtos e fornecedores em CSV que abre no Excel, protegido contra fórmulas maliciosas.
- Aviso ao sair de um formulário com alterações não salvas.

### Estoque
- Toda movimentação guarda **saldo anterior e saldo novo**.
- **Motivo obrigatório** em entrada manual, perda, devolução e contagem.
- **Devolução de cliente** (volta ao estoque) e **devolução ao fornecedor** (sai do estoque).
- **Custo médio ponderado** recalculado a cada entrada com custo (manual ou nota de compra), com "última compra" e **histórico de preço e custo** no produto.

### Ordem de serviço
- **Corrigir item já lançado** (descrição, quantidade, valor, mecânico). Mudar valor ou quantidade de item aprovado manda de novo para aprovação, gera nova versão do orçamento e fica no histórico da OS.

### Correções
- Importação: contadores de criados/atualizados se perdiam dentro da transação (pego pelo teste).


## 1.1.0 — 07/11/2026

Atende os itens P0 da especificação "Torque SaaS — Especificação completa para a equipe". Situação item a item em `docs/auditoria.md`.

### Plataforma SaaS
- Cadastro da oficina pelo site com 14 dias de teste grátis.
- Planos (Essencial, Profissional, Rede) editáveis no painel, com limites aplicados no servidor: usuários, consultas de placa por mês, WhatsApp automático.
- Assinatura recorrente pelo Asaas (ou modo demonstração), faturas na tela da oficina, webhook autenticado e idempotente, suspensão automática por teste vencido ou atraso.
- Painel do administrador da plataforma: oficinas, receita mensal, situação, uso, planos e avisos do gateway, sem acesso aos dados internos das oficinas.

### Segurança
- Recuperação de senha por e-mail.
- Verificação em duas etapas (aplicativo autenticador), obrigatória para o admin da plataforma, com códigos de recuperação.
- Orçamento público com validade, revogação ("Novo link"), versão e registro do aceite com hash de integridade.
- Webhooks fora da proteção CSRF apenas com token.

### Operação
- Reserva de peças em OS aprovadas e política de estoque negativo por oficina.
- Reabertura de OS entregue (devolve peças, retira comissão não paga).
- Estorno de recebimento com motivo, refletido no caixa.
- Agenda com capacidade de boxes e opção de encaixe.
- Registro de mensagens de WhatsApp com novas tentativas; respeita "não aceita WhatsApp" também na confirmação de agenda.
- Portal do cliente (carros, OS, garantias, revisões, link para aprovar).
- Margem por OS e margem de contribuição do mês; exportação CSV de OS, contas a receber e a pagar.
- Backup diário (`torque:backup`).

### Documentação
- `docs/permissoes.md`, `docs/relatorio-testes.md`, `docs/runbook.md`, `docs/auditoria.md`.

### Testes
- 92 testes automatizados (15 novos), todos aprovados.

## 1.0.0 — 06/11/2026

Etapas 1 a 6: base multiempresa, recepção por placa, ordem de serviço com aprovação pelo WhatsApp, estoque e compras por XML, caixa e financeiro, notas fiscais (Focus NFe), agenda e lembretes.
