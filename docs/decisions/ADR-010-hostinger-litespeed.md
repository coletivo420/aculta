# ADR-010: LiteSpeed na produção Hostinger

Status: Accepted

Data: 2026-10-15

## Contexto

O ACULTA usa Apache no Homelab. O responsável confirmou que a produção está no
plano Hostinger Cloud Startup. Uma requisição HTTPS pública a `aculta.org`
respondeu `platform: hostinger` e `x-turbo-charged-by: LiteSpeed`; o cabeçalho
`server: cloudflare` mostra que Cloudflare está à frente e não identifica a
origem. A documentação oficial da Hostinger também informa que Web/Cloud usa
LiteSpeed e que symlinks são desabilitados por padrão nesses planos.

## Decisão

- Homelab permanece Debian + Apache + PHP-FPM + MariaDB.
- Produção permanece Hostinger Cloud Startup + LiteSpeed + PHP + MariaDB.
- O projeto preserva o `.htaccess` padrão do Drupal. Reescrita, headers, handler
  PHP e proteção de arquivos devem ser validados separadamente nos dois
  servidores; diretivas Apache não são copiadas para a Hostinger.
- O `aculta_deployer` registra a plataforma observada e verifica os resultados
  HTTP públicos, mas não tenta configurar o servidor Hostinger.
- Não há mudança a aplicar ao servidor de produção: os cabeçalhos públicos já
  confirmam LiteSpeed. Qualquer ajuste de symlink/document root é uma operação
  independente e requer primeiro validar o plano, permissões e rollback.

## Consequências

- Apache continua baseline de desenvolvimento e validação no Homelab, não uma
  suposição sobre produção.
- O runbook de produção documenta LiteSpeed, preserva `.htaccess` e evita
  recomendar `mod_rewrite`, `mod_headers` ou mudanças em VirtualHost que o plano
  gerenciado não disponibiliza.
- Os headers públicos são evidência observada em 2026-10-15; devem ser
  rechecados se plano, CDN ou hospedagem mudarem.

## Referências

- [Hostinger: opções de acesso e servidores Web/Cloud](https://www.hostinger.com/support/which-file-transfer-and-server-access-options-are-supported-at-hostinger/)
- [Hostinger: criar symlink](https://www.hostinger.com/support/1583694-how-to-create-a-symlink-at-hostinger/)
- [Drupal: relocated web root em shared hosting](https://www.drupal.org/docs/getting-started/system-requirements/relocated-web-root-on-shared-hosting)
