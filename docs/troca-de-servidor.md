# Troca de servidor

Leva o Torque inteiro (código, `.env` com a mesma `APP_KEY`, fotos das OS, XML das compras e banco) para um servidor novo, com uma parada curta só no corte final.

Scripts: `scripts/servidor/exportar.sh` (servidor antigo) e `scripts/servidor/importar.sh` (servidor novo). O importar chama o `scripts/deploy/preparar-servidor.sh`, então o deploy automático já fica pronto.

## Antes (de véspera)

- [ ] **Baixar o TTL do DNS** de `torque.softmaxbr.com.br` para 300 segundos (5 min). Assim a troca de IP pega rápido no dia.
- [ ] Servidor novo: **Ubuntu 24.04**, 2 GB de RAM ou mais, datacenter em São Paulo. Acesso root por SSH.
- [ ] Combinar com a oficina um horário de pouco movimento para o corte (uns 30 minutos sem usar o sistema).
- [ ] Anotar se alguma integração libera acesso por IP do servidor (consulta de placa, VIO, Evolution API). Se sim, incluir o IP novo antes do corte.

## Ensaio (sem parar o sistema)

1. No servidor antigo: `sudo bash scripts/servidor/exportar.sh`
2. Copie o pacote: `scp /root/torque-migracao-*.tar.gz root@IP_NOVO:/root/`
3. No servidor novo, copie a pasta `scripts/` do repositório (WinSCP ou `git clone` com token) e rode:
   `sudo bash scripts/servidor/importar.sh /root/torque-migracao-....tar.gz`
4. No seu computador, aponte o domínio para o IP novo no arquivo `hosts` (o script mostra a linha) e teste em `http://`: login, uma OS com foto, uma nota fiscal, o balanço de estoque, o caixa.
5. Deu certo? No servidor novo, apague o ensaio para o corte: `mv /var/www/torque /var/www/torque.ensaio` e `mariadb -e "DROP DATABASE torque"` (use o nome do `DB_DATABASE`). Tire a linha do `hosts`.

## Corte

1. Avise a oficina. No servidor antigo: `sudo bash scripts/servidor/exportar.sh --manutencao` (o sistema antigo fica em manutenção, ninguém grava mais nada nele).
2. Copie o pacote para o servidor novo e rode o `importar.sh` como no ensaio. O sistema novo nasce em manutenção também.
3. Teste pelo `hosts` e libere: `sudo -u deploy php /var/www/torque/artisan up`
4. **DNS:** registro A do domínio para o IP novo.
5. Quando o domínio já abrir no servidor novo (`ping torque.softmaxbr.com.br` mostra o IP novo): `certbot --nginx -d torque.softmaxbr.com.br --redirect -m SEU_EMAIL --agree-tos -n`
6. **GitHub** › Settings › Secrets › Actions: troque `DEPLOY_HOST`, `DEPLOY_PORT`, `DEPLOY_KNOWN_HOSTS` e `DEPLOY_SSH_KEY` pelos que o `importar.sh` mostrou. Rode **Actions › Deploy › Run workflow** para conferir.
7. Asaas (se usar a cobrança): o webhook usa o domínio, não precisa mudar. Confira em `/plataforma` › Avisos do gateway se chegou o próximo aviso.
8. Apague o pacote nos dois servidores: `shred -u /root/torque-migracao-*.tar.gz`

## Depois

- Deixe o servidor antigo **desligado, mas não apagado**, por 7 dias. Para voltar atrás nesse período: DNS de volta para o IP antigo, `php artisan up` no antigo (o que foi lançado no novo nesse meio-tempo teria de ser refeito).
- No dia seguinte, confira se o backup das 02:30 rodou: `ls -lh /var/www/torque/storage/app/backups`.
- Continue copiando os backups para fora do servidor.

## Se algo der errado no importar

- O script para no primeiro erro e diz o motivo. Nada é sobrescrito: se `/var/www/torque` ou o banco já existirem, ele recusa.
- Para recomeçar do zero no servidor novo: `mv /var/www/torque /var/www/torque.falhou` e `mariadb -e "DROP DATABASE nome_do_banco"`.
