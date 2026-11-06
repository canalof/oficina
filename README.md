# Torque

Sistema de gestão para autocenters e oficinas. O carro chega, a placa é lida e a ficha abre na hora.

Este repositório está sendo construído por módulos, um Pull Request por etapa:

| Etapa | Conteúdo | Situação |
|---|---|---|
| 1. Base | Login, empresas, usuários e perfis, clientes, veículos, recepção pela placa com consulta automática | **este PR** |
| 2. Ordem de serviço | Orçamento, aprovação pelo WhatsApp, checklist com fotos, OS, garantia | próximo |
| 3. Estoque e compras | Produtos, aviso de estoque mínimo, entrada por XML da nota | |
| 4. Caixa e financeiro | Caixa do dia, contas a pagar e receber, comissões | |
| 5. Notas fiscais | NF-e, NFC-e, NFS-e (via emissor homologado) | |
| 6. Agenda e lembretes | Agendamento, lembrete de revisão por km/tempo | |

## O que já funciona (etapa 1)

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

### Criar a primeira oficina

```bash
php artisan torque:empresa "Autocenter Silva" dono@autocentersilva.com.br --cnpj=11222333000181 --nome="José Silva"
```

O comando mostra a senha gerada. O dono entra, troca a senha e cadastra a equipe em **Usuários**.

### Fila e agendador

Por enquanto não há tarefas agendadas. Quando as próximas etapas entrarem (lembretes, notas), será preciso adicionar ao cron:

```
* * * * * cd /caminho/torque && php artisan schedule:run >> /dev/null 2>&1
```

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
Também existem `recepcao@demo.com.br` (consultor) e `mecanico@demo.com.br` (mecânico), mesma senha.

No modo demonstração (`PLACA_PROVEDOR=demonstracao`) a consulta de placa devolve dados fictícios e não gasta nada. Placas começando com `ZZZ` simulam "não encontrada".

## Testes

```bash
vendor/bin/phpunit
```

Cobrem: separação entre empresas, login e permissões, validação de placa, CPF e CNPJ, cadastro e transferência de veículos, auditoria e a consulta de placa (com a API simulada, incluindo erro de saldo, placa não encontrada e alerta de roubo).

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
