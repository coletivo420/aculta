# Ambientes e deploy

## Desenvolvimento / Homelab

Debian, Apache, PHP-FPM, MariaDB, aliases `*.aculta.toca.net.br` e noindex.

O Homelab foi migrado de Nginx para Apache em 2026-10-06. Documentos
históricos de fases anteriores podem mencionar Nginx ao descrever o ambiente
existente no momento daqueles testes; eles não definem mais a arquitetura
atual.

Apache é o servidor web do Homelab. O ambiente de teste valida `.htaccess`,
rewrite, headers e PHP-FPM neste servidor; produção usa LiteSpeed e precisa de
validação específica, sem manter uma camada de compatibilidade com Nginx.

O VirtualHost do Homelab deve apontar o DocumentRoot para `web/`, preservar o
`.htaccess` fornecido pelo Drupal e permitir as diretivas necessárias ao
Drupal.

## Servidor de testes

`*.aculta.toca.net.br` é o **servidor de testes** do projeto. Neste documento ele
aparece como Homelab; os dois termos designam o mesmo ambiente.

- Hosts: `aculta.toca.net.br` (MAIN), `apoio.aculta.toca.net.br` (SUPPORT) e os
  demais purposes conforme `docs/architecture/multidomain.md`.
- `links.aculta.toca.net.br` é o host de teste da página pública de links para
  redes sociais e projetos ACULTA. Seu CNAME Cloudflare aponta para
  `aculta.toca.net.br`, com proxy desabilitado.
- Não é produção: nenhum dado de produção deve ser usado nele sem decisão do
  responsável.

Testes locais em máquina de desenvolvimento (por exemplo, navegador headless
apontado para `127.0.0.1` com resolução de host própria) simulam o servidor de
testes, mas não são o servidor de testes. Resultados desses testes devem ser
registrados como tal.

## Produção

Hostinger Cloud Startup, LiteSpeed, PHP, MariaDB e hosts `*.aculta.org`.
Em 2026-10-15, a resposta HTTPS pública de `aculta.org` retornou
`platform: hostinger` e `x-turbo-charged-by: LiteSpeed`; o cabeçalho `server`
identifica Cloudflare.

A produção e o Homelab usam servidores web diferentes. O Drupal mantém o mesmo
`web/.htaccess`, mas diretivas, headers, handlers PHP e controles de acesso
precisam ser validados separadamente em cada ambiente.

## Regra

Apache é usado no Homelab; LiteSpeed, na produção Hostinger Cloud Startup.
Ver [ADR-010](../decisions/ADR-010-hostinger-litespeed.md).
Configuração Nginx legada não deve orientar novos recursos, testes ou
documentação normativa. Referências a Nginx em relatórios antigos são
evidência histórica da migração e não constituem suporte atual.

Preservar o `.htaccess` Drupal e validar por ambiente:

- VirtualHosts e aliases;
- rewrite;
- headers;
- política de leitura e processamento do `.htaccess` (`AllowOverride` no
  Apache do Homelab; comportamento LiteSpeed na Hostinger);
- integração PHP (`proxy_fcgi` no Homelab; handler fornecido pela Hostinger na
  produção);
- proteção de public/private files;
- redirects e headers;
- certificados e canonical hosts.

O código da aplicação e o tema não devem depender de comportamento exclusivo
da configuração local. VirtualHosts, caminhos, usuários, certificados e
configuração Virtualmin/Hostinger não devem ser copiados literalmente entre os
ambientes. Regras de noindex pertencem somente ao Homelab.

Ordem lógica: dependências lockadas, settings/segredos do ambiente, updates
necessários, Configuration Sync, provisionamento idempotente aprovado, cache e
smoke tests.
