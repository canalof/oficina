# Torque

Sistema de gestão para autocenters e oficinas. O carro chega, a placa é lida e a ficha abre na hora.

Este repositório está sendo construído por módulos, um Pull Request por etapa:

| Etapa | Conteúdo | Situação |
|---|---|---|
| 1. Base | Login, empresas, usuários e perfis, clientes, veículos, recepção pela placa com consulta automática | pronta |
| 2. Ordem de serviço | Orçamento, aprovação pelo WhatsApp, checklist com fotos, OS, garantia | pronta |
| 3. Estoque e compras | Produtos, aviso de estoque mínimo, entrada por XML da nota | pronta |
| 4. Caixa e financeiro | Caixa do dia, contas a pagar e receber, comissões | pronta |
| 5. Notas fiscais | NF-e, NFC-e, NFS-e (via Focus NFe) | pronta |
| 6. Agenda e lembretes | Agendamento, lembrete de revisão por km/tempo | pronta |

## O que já funciona

### Etapa 1: base

- **Várias oficinas no mesmo sistema.** Cada empresa só enxerga os próprios dados; isso é garantido no banco por um filtro automático (`empresa_id`), não só na tela.
- **Login e perfis**: dono, gerente, consultor, mecânico, caixa, estoquista. Cada perfil tem suas permissões (ex.: mecânico não cadastra cliente nem gasta consulta de placa).
- **Recepção pela placa**: digita a placa (antiga ou Mercosul).
  - Carro já cadastrado: abre a ficha com o dono, km e contato.
  - Carro novo: consulta marca, modelo, ano, cor e FIPE no serviço de placas e já preenche o cadastro.
  - Avisa se a consulta indicar roubo ou furto.
  - Reconhece a mesma placa nos dois formatos (ABC1234 = ABC1C34).
  - Uma placa consultada é reaproveitada por 30 dias, sem pagar de novo.
- **Clientes**: CPF e CNPJ validados (inclusive o CNPJ com letras que começa em 2026), busca de endereço pelo CEP, link direto para WhatsApp.
- **Veículos**: histórico de donos e transferência de dono.
- **Auditoria**: toda criação, alteração e exclusão fica registrada com quem fez, quando e o que mudou.
- **Painel**: números do mês e gasto com consulta de placa.
- **Celular**: todas as telas funcionam no celular.

### Etapa 2: ordem de serviço

- **Abrir OS** direto da recepção pela placa, com km, nível de combustível, relato do cliente, previsão de entrega, mecânico e checklist de entrada (15 itens: OK, avaria ou não tem).
- **Orçamento**: serviços do catálogo (preço padrão) ou escritos à mão, peças, quantidade, desconto.
- **Aprovação pelo WhatsApp**: um botão abre o WhatsApp com a mensagem e o link do orçamento. O cliente abre no celular, sem senha, vê fotos e itens, marca o que autoriza e confirma com o nome. Fica registrado quem aprovou, quando e de qual IP.
- **Aprovação no balcão ou por telefone**: a equipe marca os itens aprovados na própria OS.
- **Itens adicionais**: o mecânico achou outro problema durante o serviço? O item novo entra como "aguardando aprovação" e a OS não pode ser concluída até o cliente responder.
- **Etapas**: Orçamento → Aguardando aprovação → Aprovada → Em serviço → Pronta → Entregue (ou Cancelada, com motivo). Não dá para pular etapas.
- **Quadro do pátio**: todos os carros da oficina por etapa, com previsão de entrega em vermelho quando atrasa.
- **Fotos** pelo celular (câmera direto), reduzidas automaticamente e visíveis só para a oficina e para o cliente pelo link.
- **Impressão** da OS/orçamento em A4 com checklist, itens recusados, garantia e campo de assinatura.
- **Garantia**: conta a partir da entrega; aparece no histórico do carro.
- **Histórico** na ficha do veículo e do cliente, incluindo itens recusados (para oferecer de novo na próxima visita).
- **Painel**: carros no pátio por etapa, atrasados e entregues no mês.

### Etapa 3: estoque e compras

- **Produtos** com código, código de barras, NCM, unidade, localização na prateleira, custo, preço e estoque mínimo.
- **Aviso de estoque mínimo** no painel e na lista de produtos.
- **Entrada pela nota de compra**: envie o XML que o fornecedor manda por e-mail. O sistema cadastra o fornecedor, reconhece os produtos (pelo código de barras ou pelo código do fornecedor, que ele aprende na primeira nota), dá entrada no estoque, atualiza o custo e lança as parcelas em contas a pagar. Nota repetida é recusada.
- **Peças do estoque na OS**: na entrega do carro, a baixa é automática.
- **Inventário**: entrada manual, saída (perda) e contagem da prateleira, tudo com histórico.

### Etapa 4: caixa e financeiro

- **Caixa do dia**: abre com troco, recebe, faz retirada (sangria) e reforço, fecha conferindo o dinheiro da gaveta e mostra a diferença.
- **Receber a OS**: Pix, dinheiro, débito, crédito (parcelado), boleto ou a prazo, dividindo em até 3 formas. Boleto e a prazo viram contas a receber com parcelas mensais.
- **Contas a receber**: vencidas em destaque, botão "Cobrar" que abre o WhatsApp com a mensagem pronta.
- **Contas a pagar**: despesas por categoria, contas fixas repetidas por meses, parcelas das notas de compra.
- **Comissões**: percentual por mecânico, calculado na entrega sobre os serviços (com o desconto proporcional).
- **Resumo do mês**: carros entregues, ticket médio, entradas por forma, despesas por categoria, serviços e peças que mais faturaram, valor recusado pelos clientes.

### Etapa 5: notas fiscais

- **NFS-e** (serviços), **NFC-e** (peças para consumidor) e **NF-e** (peças para empresa), emitidas de dentro da OS depois do pagamento.
- Integração com a **Focus NFe** (API v2). Cada oficina tem seu token, guardado criptografado. Situação atualizada sozinha a cada 5 minutos, PDF e XML, cancelamento com justificativa.
- **Sem token, o sistema emite em modo demonstração** (nota sem valor fiscal), para treinar a equipe.
- Os padrões fiscais (CFOP, CSOSN, item da lista de serviço, alíquota de ISS, CST de PIS/COFINS) ficam na tela Empresa e no `.env`. **Confira com o contador e teste em homologação antes de ir para produção.**

### Etapa 6: agenda e lembretes

- **Agenda da semana** (segunda a sábado): cliente, placa, serviço, duração; confirmar, marcar chegada (já abre a OS) ou falta; botão de WhatsApp para confirmar o horário.
- **Lembretes de revisão**: no catálogo, cada serviço pode ter prazo (ex.: troca de óleo a cada 6 meses ou 10.000 km). Na entrega do carro, o lembrete é criado sozinho.
- **WhatsApp automático (opcional)** com a Evolution API: todo dia às 9h envia os lembretes dos próximos 7 dias e confirma os agendamentos do dia seguinte. Sem a Evolution, os botões abrem o WhatsApp com a mensagem pronta.

### Versão 1.2: cadastros e estoque

- **Ver histórico** em cada cadastro e na OS (quem mudou o quê, antes → depois).
- **Corrigir item já lançado** na OS (volta para aprovação se o valor mudar).
- Veículo com **Renavam** e **motor**; produtos com **categoria**, **fabricante**, **referência** e **unidade**.
- Estoque com **motivo obrigatório**, **saldo anterior e novo**, **perdas e devoluções**, **custo médio** e histórico de preço.
- **Importar e exportar** cadastros em planilha; **aviso de cadastro duplicado**.

### Versão 1.1: plataforma SaaS e segurança

- **Cadastro pelo site** com 14 dias de teste grátis, **planos** com limites, **assinatura recorrente** pelo Asaas, faturas, suspensão automática por atraso e painel do administrador da plataforma (`/plataforma`).
- **Esqueci a senha** e **verificação em duas etapas** (aplicativo autenticador).
- **Orçamento** com validade, troca de link e registro exato do que o cliente aprovou.
- **Reserva de peças**, política de estoque negativo, **reabertura de OS**, **estorno** de recebimento, **capacidade da agenda**, registro e novas tentativas do WhatsApp.
- **Portal do cliente**, **margem por OS**, **planilhas** para o contador e **backup** diário.

Detalhes em `CHANGELOG.md`; situação frente à especificação da equipe em `docs/auditoria.md`; operação em `docs/runbook.md`.

## Requisitos do servidor

- PHP 8.3 ou mais novo, com as extensões `pdo_mysql`, `mbstring`, `xml`, `curl`, `zip`, `bcmath`, `intl`
- MySQL 8 (ou MariaDB 10.6+)
- Composer 2
- Node 20+ (só para gerar os arquivos de tela; não precisa ficar rodando)
- Nginx ou Apache apontando para a pasta `public/`
- HTTPS (certificado Let's Encrypt serve)

Uma VPS de 2 GB de RAM atende tranquilamente as primeiras dezenas de oficinas.

## Instalação

```bash
git clone https://github.com/canalof/oficina.git torque
cd torque

composer install --no-dev --optimize-autoloader
cp .env.example .env
php artisan key:generate
```

Edite o `.env`:

```ini
APP_NAME=Torque
APP_ENV=production
APP_DEBUG=false
APP_URL=https://seu-dominio.com.br

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=torque
DB_USERNAME=torque
DB_PASSWORD=coloque-uma-senha-forte

# Consulta de placa (consultadeplaca.net). Sem chave, roda em modo demonstração.
PLACA_PROVEDOR=consultadeplaca
PLACA_API_KEY=sua-chave-aqui
```

Depois:

```bash
php artisan migrate --force
npm ci && npm run build
php artisan config:cache && php artisan route:cache && php artisan view:cache
```

Permissões das pastas (usuário do servidor web, normalmente `www-data`):

```bash
chown -R www-data:www-data storage bootstrap/cache
```

### Criar o administrador da plataforma

```bash
php artisan torque:admin seu@email.com --nome="Seu Nome"
```

### Criar a primeira oficina (ou deixe a oficina se cadastrar em /cadastro)

```bash
php artisan torque:empresa "Autocenter Silva" dono@autocentersilva.com.br --cnpj=11222333000181 --nome="José Silva"
```

O comando mostra a senha gerada. O dono entra, troca a senha e cadastra a equipe em **Usuários**.

### Fila e agendador

As fotos das OS e os XML das notas de compra ficam em `storage/app/private/`. Inclua essa pasta no backup.

Adicione ao cron do servidor (atualiza as notas fiscais e envia os lembretes):

```
* * * * * cd /caminho/torque && php artisan schedule:run >> /dev/null 2>&1
```

### Notas fiscais (Focus NFe)

1. Crie a conta na Focus NFe e cadastre a empresa da oficina lá (com o certificado digital A1).
2. No Torque, em **Empresa → Dados fiscais**, preencha inscrições, código IBGE do município, regime, CFOP, CSOSN, item da lista de serviço e alíquota de ISS, e cole o **token**.
3. Deixe o ambiente em **Homologação**, emita algumas notas de teste e só depois mude para **Produção**.

### WhatsApp automático (opcional)

Com a Evolution API instalada, coloque no `.env` `EVOLUTION_URL` e `EVOLUTION_KEY` e, em **Empresa**, o nome da instância conectada ao WhatsApp da oficina.

## Ver online sem instalar nada (GitHub Codespaces)

No GitHub, botão verde **Code** → aba **Codespaces** → **Create codespace on sistema-completo** (a versão mais nova; depois de aprovar os PRs, use `main`).
Na primeira vez leva uns 5 minutos para preparar; depois o sistema abre sozinho numa aba nova.
Login: **dono@demo.com.br / senha12345**. Se a aba não abrir, vá em **Ports** e clique no globo da porta 8000.

## Rodar no computador (para desenvolvimento)

```bash
composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite   # e DB_CONNECTION=sqlite no .env
php artisan migrate --seed
npm install && npm run build
php artisan serve
```

Acesse http://127.0.0.1:8000 e entre com **dono@demo.com.br / senha12345**.
Painel da plataforma: **admin@demo.com.br / senha12345** (pede para ativar a verificação em duas etapas no primeiro acesso).
Para testar a entrada de nota de compra, use o arquivo `tests/Fixtures/nfe-compra.xml`.
Também existem `recepcao@demo.com.br` (consultor) e `mecanico@demo.com.br` (mecânico), mesma senha.

No modo demonstração (`PLACA_PROVEDOR=demonstracao`) a consulta de placa devolve dados fictícios e não gasta nada. Placas começando com `ZZZ` simulam "não encontrada".

## Testes

```bash
vendor/bin/phpunit
```

São 92 testes (relatório em `docs/relatorio-testes.md`). Cobrem: separação entre empresas, login e permissões, placa, CPF e CNPJ, veículos, auditoria, consulta de placa (API simulada), a ordem de serviço inteira, importação de XML de compra (inclusive aprendizado do código do fornecedor), inventário, pagamento dividido e parcelado, caixa (sangria, fechamento com diferença), comissões, lembretes, notas fiscais (Focus NFe simulada: dados enviados, consulta, erro da SEFAZ, cancelamento) e WhatsApp pela Evolution API (simulada).

## Consulta de placa: custo

O provedor padrão é o consultadeplaca.net (R$ 0,07 por consulta na data deste documento). O sistema:

- só consulta quando o carro **não** está cadastrado;
- reaproveita a consulta da mesma placa por 30 dias (`PLACA_REUSO_DIAS`);
- registra toda consulta com o custo, e o painel mostra o gasto do mês;
- só deixa consultar quem tem permissão (dono, gerente, consultor).

A chave da API fica só no `.env` do servidor e nunca vai para o navegador.

## Backup

Mínimo recomendado: dump diário do MySQL guardado fora do servidor.

```bash
mysqldump --single-transaction torque | gzip > /backup/torque-$(date +%F).sql.gz
```

## Estrutura

```
app/
  Support/          Tenant (empresa atual), Placa, Documento (CPF/CNPJ), Perfil (permissões)
  Models/Concerns/  PertenceAEmpresa (filtro por empresa), Auditavel
  Services/Placa/   ConsultaPlaca (cache + registro) e os provedores
  Http/Controllers/ uma por área
resources/views/    telas (Blade + Tailwind)
tests/              testes automatizados
```
