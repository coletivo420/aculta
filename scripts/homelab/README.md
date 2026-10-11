# Homelab ACULTA

O Homelab é o ambiente de desenvolvimento canônico: Debian + Apache + PHP-FPM
+ MariaDB. Apache é o baseline definitivo de servidor web do projeto; Nginx não
é requisito de compatibilidade.

O banco normal é o Runtime MariaDB (`aculta_runtime`), carregado a partir de `estados/`;
produção continua MariaDB. Mantenha o diretório de arquivos privados e uploads fora do
banco. Nunca implante um Estado em produção.

Fluxo de preparação:

```sh
git clone git@github.com:coletivo420/aculta-site.git
cd aculta-site
composer install
cp web/sites/default/settings.homelab.php.example web/sites/default/settings.homelab.php
export ACULTA_ENV=homelab
bash scripts/homelab/verify-aculta-homelab.sh
```

O `settings.php` local deve incluir `settings.homelab.php` depois da
configuração base de banco. Mantenha esse loader local/ignorado; o bootstrap
recusa continuar se o Drupal não estiver usando o Runtime MariaDB `aculta_runtime`.
O `settings.homelab.php` permanece local/ignorado. Secrets podem vir do
environment nativo do processo ou do adapter bootstrap seguro descrito em
[`docs/operations/SECRETS.md`](../../docs/operations/SECRETS.md); nunca grave
credenciais no banco para simplificar o desenvolvimento.

O VirtualHost Apache deve apontar o DocumentRoot para `web/`, preservar
`web/.htaccess` e usar PHP-FPM. O verificador do Homelab exige configuração
Apache válida, `mod_rewrite`, `mod_headers` e `proxy_fcgi`, e falha se
encontrar um processo Nginx ativo.

Estados são backups privados dos servidores: não são versionados no Git
(a pasta `estados/` no `.gitignore`) e não são sanitizados. Não adicione deliberadamente credenciais
externas ou de produção ao Runtime/Estado; nenhum Estado que contenha secret
persistido pode ser versionado ou publicado. O banco e os settings de produção
continuam ativos separados em MariaDB.

