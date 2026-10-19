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

No servidor de testes, `/home/aculta` é um symlink para o checkout
`/home/piradopirata/projetos/aculta-site`; não há uma subpasta `aculta-site`
dentro dele. O acesso público usa `/home/aculta/public_html -> web` (symlink
relativo), e o VirtualHost aponta para `/home/aculta/public_html`. A raiz serve
o `.htaccess` original do Drupal e permite as diretivas necessárias. Os dados
do Virtualmin e os arquivos privados da conta permanecem fora do checkout e
são acessados por symlinks no topo do projeto, fora da árvore pública.

## Diretório de trabalho (Homelab)

- Diretório de trabalho do Homelab e dos agentes: `/home/aculta/`. É a raiz da instalação
  Drupal (Composer, `vendor/`, `web/`, `config/sync/`, `secrets/`, `var/deployer/`, `private/`)
  e `public_html -> web` é o acesso público do Apache.
- O repositório git aparece em `/home/aculta/aculta-site`. Até a migração completa, esse
  caminho é um symlink para o checkout de origem em `/home/piradopirata/projetos/aculta-site`,
  com o mesmo branch e o mesmo histórico. Não há duas cópias de trabalho: edite pelo caminho
  canônico e confirme com `git status`.
- Drush e operações de runtime devem ser executados a partir de `/home/aculta/`
  (`php vendor/drush/drush/drush.php status`), porque o bootstrap lê
  `web/sites/default/settings.php` e `settings.local.php`.
- Permissão: esses dois arquivos são `aculta:aculta` com modo `0640`, por isso o usuário de
  trabalho precisa estar no grupo `aculta` para o Drush conectar ao runtime. Sem essa
  permissão, o `drush status` não mostra banco nem site URI. Não relaxe o modo dos arquivos
  de configuração nem copie segredos para fora da instalação para contornar isso.
- Estado verificado em 2026-10-19: o checkout aberto em `/home/piradopirata/projetos/aculta-site`
  está no servidor de testes (`hostname: toca`) e o Drush alcança o Runtime de teste
  `aculta_runtime`. Neste servidor, `Site URI: http://default` é uma saída esperada quando o Drush
  roda sem `--uri`; não indica produção nem conexão errada por si só. Para comandos que dependem do
  purpose/domínio, informe `--uri` com um host de teste listado em
  `docs/architecture/multidomain.md`. Confirme sempre hostname, checkout, branch, banco e efeitos
  antes de qualquer operação persistente. Não fixe uma URI nas configurações locais.

## Servidor de testes

`*.aculta.toca.net.br` é o **servidor de testes** do projeto. Neste documento ele
aparece como Homelab; os dois termos designam o mesmo ambiente. Os hosts e seus
purposes estão em `docs/architecture/multidomain.md`; não há uma URI única que
represente todos os sites/purposes do Runtime.
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
