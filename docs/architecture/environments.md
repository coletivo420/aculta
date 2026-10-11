# Ambientes e deploy

## Desenvolvimento / Homelab

Debian, Apache, PHP-FPM, MariaDB, aliases `*.aculta.toca.net.br` e noindex.

O Homelab foi migrado de Nginx para Apache em 2026-10-06. Documentos
históricos de fases anteriores podem mencionar Nginx ao descrever o ambiente
existente no momento daqueles testes; eles não definem mais a arquitetura
atual.

Apache é o baseline definitivo do servidor web de desenvolvimento. O Homelab
deve exercitar o mesmo modelo de `.htaccess`, rewrite, headers e PHP-FPM que a
aplicação espera em produção, sem manter uma camada paralela de compatibilidade
com Nginx.

O VirtualHost do Homelab deve apontar o DocumentRoot para `web/`, preservar o
`.htaccess` fornecido pelo Drupal e permitir as diretivas necessárias ao
Drupal.

## Servidor de testes

`*.aculta.toca.net.br` é o **servidor de testes** do projeto. Neste documento ele
aparece como Homelab; os dois termos designam o mesmo ambiente.

- Hosts: `aculta.toca.net.br` (MAIN), `apoio.aculta.toca.net.br` (SUPPORT) e os
  demais purposes conforme `docs/architecture/multidomain.md`.
- Não é produção: nenhum dado de produção deve ser usado nele sem decisão do
  responsável.

Testes locais em máquina de desenvolvimento (por exemplo, navegador headless
apontado para `127.0.0.1` com resolução de host própria) simulam o servidor de
testes, mas não são o servidor de testes. Resultados desses testes devem ser
registrados como tal.

## Produção

Hostinger, Apache, PHP, MariaDB e hosts `*.aculta.org`.

Produção compartilha a família de servidor web com o Homelab, mas configuração
não é cópia cega entre os ambientes.

## Regra

Apache é o único baseline de servidor web mantido pelo projeto. Configuração
Nginx legada não deve orientar novos recursos, testes ou documentação
normativa. Referências a Nginx em relatórios antigos são evidência histórica da
migração e não constituem suporte atual.

Preservar o `.htaccess` Drupal e validar por ambiente:

- VirtualHosts e aliases;
- `mod_rewrite`;
- `mod_headers`;
- política de `AllowOverride`;
- PHP-FPM/integração PHP, incluindo `proxy_fcgi` no Homelab;
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
