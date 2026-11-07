# Runbook de operação

## Implantação (primeira vez)

1. Servidor com PHP 8.3+ (extensões pdo_mysql, mbstring, xml, curl, zip, bcmath, intl, gd, exif), MySQL 8, Nginx/Apache apontando para `public/`, HTTPS.
2. Copie o código, `cp .env.example .env` e preencha: `APP_URL`, `APP_ENV=production`, `APP_DEBUG=false`, banco, e-mail (SMTP), `COBRANCA_*`/`ASAAS_*`, `EVOLUTION_*` (opcional), `TRUSTED_PROXIES` se houver proxy.
3. `php artisan key:generate` (uma única vez; **guarde a APP_KEY**: sem ela os tokens fiscais e segredos de 2 etapas não abrem).
4. `php artisan migrate --force`
5. `php artisan torque:admin seu@email.com` (anote a senha; no primeiro acesso ative as 2 etapas).
6. `php artisan config:cache && php artisan route:cache && php artisan view:cache`
7. Cron: `* * * * * cd /caminho && php artisan schedule:run >> /dev/null 2>&1`
8. `chown -R www-data:www-data storage bootstrap/cache`

## Rotinas automáticas (agendador)

| Comando | Quando | O que faz |
|---|---|---|
| `torque:backup` | 02:30 | Backup do banco em `storage/app/backups` (14 últimos) |
| `torque:assinaturas` | 03:10 | Suspende teste vencido e atraso além da tolerância |
| `torque:lembretes` | 09:00 | WhatsApp automático: revisões, confirmação de agenda, novas tentativas |
| `torque:notas` | a cada 5 min | Consulta notas fiscais em processamento |

## Atualização (nova versão)

1. `php artisan down`
2. `php artisan torque:backup`
3. Atualize o código (git pull ou novo pacote)
4. `composer install --no-dev --optimize-autoloader` (se veio sem vendor) e `npm ci && npm run build` (se veio sem public/build)
5. `php artisan migrate --force`
6. `php artisan config:cache && php artisan route:cache && php artisan view:cache`
7. `php artisan up`

## Voltar versão (rollback)

1. `php artisan down`
2. Restaure o código anterior.
3. Se a versão nova criou tabelas/colunas: `php artisan migrate:rollback --step=N` (N = migrations da versão; todas têm `down`). Em caso de dúvida, restaure o backup feito no passo 2 da atualização.
4. Limpe e refaça os caches, `php artisan up`.

## Restaurar backup (testar pelo menos 1 vez por mês em outro servidor)

```bash
gunzip -c storage/app/backups/torque-AAAA-MM-DD-HHMMSS.sql.gz | mysql -u USUARIO -p BANCO
```

Restaure também `storage/app/private` (fotos das OS e XML das compras) e use **a mesma APP_KEY**. Depois entre no sistema e confira uma OS, uma foto e uma nota.

## Integrações: ativar em produção

| Integração | Antes de ligar |
|---|---|
| Asaas | Testar no sandbox: assinar, pagar, atrasar, estornar. Cadastrar o webhook `https://SEU_DOMINIO/webhooks/asaas` com o mesmo `ASAAS_WEBHOOK_TOKEN` do .env |
| Focus NFe | Por oficina: certificado A1 na Focus, dados fiscais conferidos pelo contador, notas de teste em homologação para cada tipo (NFS-e/NFC-e/NF-e) |
| Evolution API | Instância conectada, mensagem de teste, conferir custos/termos e consentimento dos clientes |
| Consulta de placa | Contrato, saldo, custo por consulta |

## Problemas comuns

- **Oficina diz que não entra**: em /plataforma veja a situação (teste vencido / suspensa) e o acesso liberado.
- **Nota fiscal "processando" há muito tempo**: botão "Atualizar situação" na OS; veja a mensagem da SEFAZ/prefeitura.
- **Pagamento do Asaas não liberou**: /plataforma → Avisos do gateway; confira se o webhook chegou e se deu erro.
- **Perdeu o celular das 2 etapas**: use um código de recuperação. Sem códigos: `php artisan torque:desativar-2-etapas email@do.usuario` (fica registrado no log).
