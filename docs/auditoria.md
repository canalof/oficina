# Situação frente à especificação "Torque SaaS — Especificação completa para a equipe" (v1.0, 06/11/2026)

Legenda: **Feito** = implementado e coberto por teste automatizado. **Parcial** = existe, com limitação descrita. **Pendente** = não implementado. **Depende** = precisa de credencial, fornecedor ou decisão externa.

## P0

| Item da especificação | Situação | Onde / observação |
|---|---|---|
| Login, recuperação de senha | Feito | `RecuperarSenhaController`, e-mail em português, resposta igual para e-mail inexistente |
| MFA para administradores | Feito | TOTP (RFC 6238, validado com os vetores oficiais), obrigatório para admin da plataforma, opcional para os demais, 8 códigos de recuperação de uso único, segredo criptografado |
| Papéis e políticas, deny-by-default | Feito | `docs/permissoes.md`; toda ação confere permissão no servidor |
| Isolamento entre empresas | Feito | Filtro global por `empresa_id`; testes negativos em telas, downloads, fotos, link público, portal, exportação, agenda e financeiro |
| Auditoria | Parcial | Criação/alteração/exclusão dos cadastros principais; aceites de orçamento com hash de integridade. Falta trilha append-only para todos os eventos sensíveis |
| Plataforma SaaS: onboarding, plano, trial | Feito | `/cadastro` com 14 dias de teste; planos editáveis no painel |
| Assinatura, cobrança, inadimplência, suspensão | Feito / Depende | Asaas (API v3) + modo demonstração. Webhook autenticado e idempotente; atraso → aviso → suspensão após tolerância. **Validar no sandbox do Asaas** |
| Quotas do plano no backend | Feito | Usuários, consultas de placa/mês, WhatsApp automático. Limite de lojas registrado, mas ainda não há tela de várias lojas |
| Suporte | Pendente | Sem módulo de chamados; suporte por fora |
| Recepção, clientes, veículos, histórico, km | Feito | |
| Consentimento do cliente | Parcial | Campo "aceita WhatsApp" respeitado em lembretes e confirmações; falta registro de base legal por finalidade |
| OS: estados, transições, histórico, motivo de cancelamento/reabertura | Feito | Reabertura de OS entregue com motivo (devolve estoque, retira comissão não paga) |
| Itens adicionais exigem nova aprovação; recusados não faturados | Feito | |
| Orçamento público: token de alta entropia, validade, revogação, limite de tentativas | Feito | 40 caracteres aleatórios, validade configurável (padrão 15 dias), "Novo link" revoga o anterior, cancelamento revoga |
| Evidência do aceite vinculada à versão exata | Feito | `orcamento_aceites`: itens, valores, versão, nome, IP, navegador, data e hash HMAC; aceite de versão antiga é recusado |
| Estoque: ledger, reserva, política de negativo, idempotência do XML | Feito | Reserva calculada pelas OS aprovadas; política por empresa; chave da NF-e única |
| Estoque: reversão auditada | Feito | Reabertura gera entradas de estorno no histórico |
| Caixa: abertura, fechamento, sangria, diferença | Feito | |
| Financeiro: estorno rastreável, sem apagar lançamentos | Feito | Estorno com motivo; nada é apagado |
| Conciliação bancária | Pendente | Sem importação de extrato (OFX) |
| Fiscal: homologação/produção, consulta, cancelamento, XML/PDF, rejeições | Feito / Depende | Focus NFe. **Exige homologação real por tipo de nota e município, e validação do contador** |
| Nota de demonstração nunca apresentada como válida | Feito | Selo "Demonstração" em todas as telas |
| Agenda: confirmação, lembretes, prevenção de conflitos | Feito | Capacidade de boxes por empresa, opção de encaixe consciente |
| WhatsApp: consentimento, opt-out, controle de falhas | Parcial | Registro de cada envio, até 3 tentativas. Opt-out é manual (desmarcar no cliente); falta receber "SAIR" pela Evolution |

## P1 e P2

| Item | Situação |
|---|---|
| Pátio (Kanban) | Feito (quadro por etapa) — faltam tempos por etapa, capacidade e retrabalho |
| Inspeção digital configurável | Parcial — checklist fixo de 15 itens com fotos; sem modelos editáveis |
| Autocenter: pneus, DOT, rodízio, alinhamento | Pendente |
| CRM e portal do cliente | Parcial — portal com carros, OS, garantias, revisões e link de aprovação; sem campanhas |
| DRE gerencial | Parcial — margem por OS e margem de contribuição do mês, exportação CSV; sem centros de custo |
| Cotação, frotas, IA | Pendente (P2) |

## Segurança e operação

| Item | Situação |
|---|---|
| Segredos criptografados em repouso | Feito (token fiscal, segredo de 2 etapas) |
| Logs sem tokens e documentos | Feito nos módulos novos (webhooks e WhatsApp não registram corpo nem número completo nos logs) |
| Backup automático | Feito — `torque:backup` diário, guarda 14. **Teste de restauração: ver runbook** |
| CI | Feito — GitHub Actions roda os testes a cada PR. Falta análise estática e varredura de dependências |
| Observabilidade (correlation ID, métricas, alertas) | Pendente |
| Organização em `app/Modules/*` | Não feito de propósito: custo alto sem ganho para o piloto; ver recomendação |

## Decisões que continuam pendentes (seção 13)

Gateway de cobrança definitivo (o código usa Asaas), hospedagem e domínio, várias lojas na primeira versão, contratos de placa/fiscal/WhatsApp, contador responsável pela homologação, oficinas do piloto.
