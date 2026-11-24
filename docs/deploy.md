# Deploy automático (GitHub Actions)

Todo push na `main` roda os **Testes**. Se passarem, o workflow **Deploy** (`.github/workflows/deploy.yml`):

1. monta o pacote no GitHub: `composer install --no-dev --optimize-autoloader`, `npm ci && npm run build`;
2. entra no servidor por SSH como o usuário `deploy`;
3. faz o **backup do banco** (`php artisan torque:backup`, em `storage/app/backups`). Se falhar, para aqui e nada muda;
4. `php artisan down`;
5. envia os arquivos com `rsync` (só o que mudou; apaga arquivos que saíram do código). **Nunca toca** em `.env`, `storage/`, `public/storage` e `bootstrap/cache/`;
6. `migrate --force`, `package:discover`, caches de config/rotas/telas/eventos, recarrega o PHP-FPM e `php artisan up`;
7. confere se `APP_URL/entrar` responde 200.

O `.env` e os dados das oficinas nunca passam pelo GitHub. A versão no ar fica no arquivo `VERSAO` na pasta do sistema.

Dá para rodar na mão: GitHub › **Actions** › **Deploy** › **Run workflow**.

## Configuração (uma vez)

No servidor, como root:

```bash
cd /tmp && curl -fsSLO https://raw.githubusercontent.com/canalof/oficina/main/scripts/deploy/preparar-servidor.sh
sudo bash preparar-servidor.sh /var/www/torque torque.softmaxbr.com.br
```

(Com o repositório privado o `curl` não funciona: copie o arquivo pelo WinSCP ou cole o conteúdo com `nano`.)

O script cria o usuário `deploy` (sem senha), passa a pasta para ele (o PHP lê pelo grupo `www-data`), libera `storage` e `bootstrap/cache` para os dois, permite ao `deploy` só `systemctl reload php8.3-fpm` como root e gera a chave SSH. No fim ele mostra os 4 segredos para criar em **Settings › Secrets and variables › Actions**:

| Segredo | Conteúdo |
|---|---|
| `DEPLOY_HOST` | domínio ou IP do servidor |
| `DEPLOY_PORT` | porta do SSH |
| `DEPLOY_KNOWN_HOSTS` | impressão digital do servidor (evita entrar num servidor falso) |
| `DEPLOY_SSH_KEY` | chave privada do `deploy` |

Opcionais: `DEPLOY_USER` (padrão `deploy`) e `DEPLOY_PATH` (padrão `/var/www/torque`).

Sem os segredos o workflow só avisa e não envia nada.

Se o SSH do servidor usa `AllowUsers`/`AllowGroups` no `/etc/ssh/sshd_config`, inclua o `deploy` e rode `systemctl reload ssh`.

## Se o deploy falhar

O GitHub manda e-mail. Veja em **Actions** em qual passo parou:

- **Antes de "Modo manutenção"** (SSH, backup): nada mudou no servidor.
- **Depois**: o sistema fica **em manutenção de propósito**, porque o código novo pode não combinar com o banco. Opções:
  - corrigir e enviar de novo (o próximo deploy termina com `up`);
  - voltar a versão: GitHub › reverter o commit na `main` (o deploy roda sozinho). Se a migration nova já rodou, `php artisan migrate:rollback --step=N` antes, como no `docs/runbook.md`;
  - em último caso, restaurar o backup feito no passo 3 (`docs/runbook.md`, "Restaurar backup") e `php artisan up`.

## Trocar de servidor

Veja `docs/troca-de-servidor.md` (scripts em `scripts/servidor/`).

## Cortar o acesso do GitHub

No servidor: `rm /home/deploy/.ssh/authorized_keys`. Para voltar, rode o script de novo e troque o segredo `DEPLOY_SSH_KEY`.

## Regras

- Mudança no servidor **só pelo GitHub**. Arquivo editado direto no servidor é sobrescrito ou apagado no próximo deploy.
- Migration nova sempre com `down()` e sem apagar dados sem backup.
- O backup antes do deploy e o backup diário das 02:30 dividem a mesma pasta e guardam os 30 mais recentes. Continue copiando backups para fora do servidor.
