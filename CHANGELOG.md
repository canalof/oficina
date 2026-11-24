# Changelog

## 1.7.0 — balanço de estoque e troca de servidor

- **Balanço de estoque** (Estoque › Balanço de estoque, ou botão "Balanço" em Produtos): conta vários produtos de uma vez e ajusta só o que estiver diferente. Em branco não mexe.
  - Filtro por busca, categoria e local da prateleira; diferença aparece na hora, com resumo de quantos foram contados e quantos mudam.
  - **Leitor de código de barras**: bipa o produto e soma 1 (ou leva o cursor para digitar a quantidade).
  - **Por planilha**: baixa a lista, conta no papel ou no Excel, preenche a coluna "contado" e envia. As quantidades aparecem na tela para conferir; só mudam ao clicar em "Aplicar balanço".
  - Cada balanço fica registrado (nº, quem fez, contados, ajustados e diferença pelo custo) e cada ajuste entra no histórico do produto com origem "Balanço de estoque". Produto que teve venda ou entrada entre a contagem e a aplicação aparece marcado.
  - Permissões: aplicar exige `estoque.editar`; ver os balanços, `estoque.ver`.
- **Troca de servidor**: `scripts/servidor/exportar.sh` e `scripts/servidor/importar.sh` (Ubuntu 24.04) e o passo a passo em `docs/troca-de-servidor.md`.
- Leitura de planilha CSV passou para `App\Support\PlanilhaCsv` (importação de cadastros e balanço usam a mesma).
- 7 testes novos (131 no total).

## 1.6.0 — menu enxuto e módulos

- **Menu:** 6 itens fixos (Início, Recepção, Ordens de serviço, Agenda, Clientes, Veículos) e grupos recolhidos (Estoque, Dinheiro, Condutores e locação, Ajustes) que abrem só quando você está dentro deles. Tutorial virou "Ajuda" no rodapé; Lembretes de revisão abrem pela Agenda.
- **Módulos por empresa** em Ajustes › Empresa e módulos: Condutores/CNH/retirada, Locação, Bancos e Pix, Comissões e Notas fiscais. Oficina nova começa só com o básico. Desligado, some do menu, das telas e das permissões; os dados ficam guardados.
- Empresas existentes ficam com os módulos que já usam (dados cadastrados, comissão configurada, token fiscal). Locação liga junto Condutores e CNH.
- Sem Notas fiscais, os dados fiscais saem da página da empresa (e continuam guardados).
- 6 testes novos (124 no total). Migração conferida em MariaDB.

## 1.5.1 — leitura da VIO preenche o condutor

- Depois da leitura pela VIO API, o Torque preenche no cadastro do condutor a validade, a categoria e o registro da CNH que estiverem vazios. O que já estava preenchido e diverge da leitura não é trocado: aparece como aviso.
- O registro da CNH lido não vai para o histórico da consulta.

## 1.5.0 — VIO API privada (vio.api.br)

- Conector da **VIO API privada** em Integrações de CNH: a oficina cola a API Key dela, testa (o teste não gasta crédito) e ativa.
- Na tela do condutor, "Consultar CNH com credenciais da oficina" aceita foto ou PDF da CNH: o arquivo vai para a VIO API, que lê o QR Code VIO e devolve validade e categoria. O Torque não guarda o arquivo (a VIO API guarda por 30 dias, conforme a política dela).
- Leitura documental: a consulta fica no histórico do condutor e mostra validade e categoria, mas não substitui a conferência registrada pela equipe nem a consulta oficial da SENATRAN.
- Avisos claros para chave recusada, saldo insuficiente e arquivo sem QR Code VIO. 5 testes novos, com isolamento entre empresas.

## 1.4.1 — leitura de CNH em PDF

- "Ler documento neste aparelho" (condutor) aceita PDF além de foto: a 1ª página é desenhada no próprio aparelho (pdf.js) e passa pelo mesmo OCR. Nada é enviado ao servidor.
- Validade da CNH: passa a ser a data mais recente do documento. Antes pegava a primeira data depois de "VALIDADE", que no layout da CNH é a data de emissão.
- No celular, o campo deixa de abrir direto a câmera e permite escolher foto ou arquivo.

## 1.4.0 — Locação separada da oficina

- **Locação** vira um módulo próprio, ligado por empresa em **Empresa → Usar o módulo de Locação**. Desligado, menu, botões e permissões de Locação somem (os dados ficam guardados). Empresas que já tinham frota ou reservas ficam com o módulo ligado.
- Duas modalidades: **carro reserva** (ligado a uma OS em andamento; o cliente vem da OS; frota própria ou locadora parceira; cobrado ou cortesia) e **aluguel** (só cliente, sem OS, sempre com carro da frota).
- Tela nova em **Locação → Reservas e frota** (`/locacao`): resumo da frota, filtros por modalidade e situação, cadastro da frota, saída, devolução, prorrogação, fotos e contratos. Valores digitados em reais.
- **Condutores e retirada** (`/mobilidade`) fica só com o que é da oficina: condutores, CNH e retirada do carro consertado.
- Na OS, os botões passam a ser **Retirada** e **Carro reserva**; na ficha do cliente, **Locações do cliente**.
- Títulos gerados na devolução entram em Contas a receber com `categoria = locacao`; o Resumo do mês mostra quanto veio da oficina e quanto da locação.
- Permissões novas `locacao.ver`, `locacao.editar`, `locacao.excecao` (mesmos perfis de antes), que dependem do módulo ligado.
- 8 testes novos (112 no total). Migration conferida em MariaDB: sobe, desfaz e sobe de novo.

## 1.3.0 — código do servidor de 09/11/2026

Traz para o repositório o que foi feito direto no servidor (torque.softmaxbr.com.br) depois da 1.2.0, para o deploy automático partir da versão que está no ar. Sem novos testes para os módulos novos.

### Novos módulos
- Bancos: contas bancárias, importação de extrato OFX e sugestões de conciliação.
- Pix: cobrança Pix (estática e manual) com webhook idempotente.
- CNH: conferência e consulta de CNH (Senatran com chave própria da oficina), com prazo de retenção por oficina.
- Mobilidade: frota, condutores, reservas, retiradas, fotos e documentos.
- OS: inspeção de entrada, laudo com medições, garantias, conversa com o cliente, pedido de peça, terceirizados e troca de óleo; modelos de OS e catálogo de serviços por categoria.
- Veículos: inspeção de pneus, serviços de autocenter e histórico de manutenção; extrato de frota no cliente.
- Aplicativo instalável (PWA), leitor de código de barras e leitura de placa pela câmera; tutorial e página de ajuda.

### Mudanças de comportamento
- Cadastro pelo site fica **pendente** até o admin liberar em /plataforma; o teste grátis passou para **7 dias** e começa na liberação.
- Mudar a **descrição** de item aprovado também volta para aprovação; remover item aprovado exige gerente ou dono.
- Lembrete aberto no WhatsApp manual só conta como enviado depois de "Confirmar envio".
- Entrega bloqueada por estoque desfaz também o recebimento; estoque negativo só com a opção ligada na oficina.
- `PertenceAEmpresa` bloqueia alterar, excluir e restaurar registro de outra empresa, e não aceita `empresa_id` diferente da empresa atual.

### Testes
- 4 testes ajustados ao comportamento novo; 104 testes aprovados.


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
