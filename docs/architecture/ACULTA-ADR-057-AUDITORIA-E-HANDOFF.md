# ADR-057 - Auditoria inicial e handoff homelab

Data: 2026-11-17. Baseline Git antes do início: 5c496047509f43831de2a6996cbc6ba95d867749, branch dev.

Método: leitura da árvore GitHub completa sem truncamento (42.664 entradas), dos ADRs 054/055/056, tema, Config Sync e runner Playwright. Não foi executado Drupal Runtime nesta sessão.

| Inventário Git | Quantidade |
| --- | ---: |
| Manifests SDC | 150 |
| Stories | 244 |
| Twig em templates/ | 17 |
| CSS do tema | 161 arquivos / 433.593 bytes |
| Views no Sync | 66 |
| Displays de entidade no Sync | 95 |
| Versão do tema | 1.5.0 |
| Regiões do shell | 5 |

Esses números são evidência de Git, não de renderização, performance ou Runtime ativo. A biblioteca global carrega Google Fonts externamente (Inter e Oswald); avaliar custo CWV com medição real antes de alterar fontes. Field Group continua BLOCKED_UPSTREAM; UI Patterns CKEditor 5 continua excluído.

## Comandos F0/F1, somente leitura

~~~bash
git fetch origin dev
git status --short
git rev-parse HEAD
npm run theme:adr057:test
npm run theme:adr057:audit
node scripts/audit-aculta420-adr057.mjs --check --json
php scripts/validate-aculta420-sdc-library-contract.php
php scripts/validate-aculta420-shell-regions.php
php scripts/validate-aculta420-ui-skins.php
npm run lint:css
~~~

Playwright: consultar os arquivos routes.json e configurar as variáveis origin_env de cada frente; executar com ACULTA_VISUAL_REQUIRE_ORIGINS=1 para impedir que ausência de URL seja tratada como SKIP. Destinar SUITE_OUT a diretório externo ao Git. Nunca atualizar snapshots sem revisão humana. O teste existente cobre rotas públicas; para homologação final, adicionar perfis autenticados, cache-hit, A11y, zoom 200% e os dez domínios.

## Registro de gates

- PASS_STATIC somente quando scanner e validators forem realmente executados com saída zero.
- DEFERRED nesta sessão: npm/Node no checkout completo, PHPUnit, Drush, Config Sync vs Runtime, Firefox/Playwright, CSP, leitores de tela, CWV, perfis, acesso/cache cross-domain e rollback.
- BLOCKED_UPSTREAM: adoção adicional de UI Patterns Field Group sem verificação de segurança e compatibilidade.
- NÃO AUTORIZADO: drush cim global, updatedb, pm:uninstall, DROP, remoção de dados e implantação em produção.

## Atualização 2026-11-17 — execução F0/F1

O estado acima descreve a auditoria inicial. Nesta continuação, foi executado
o scanner no checkout completo, os validadores estáticos e o render isolado dos
150 SDCs. Resultados atuais, limites da matriz visual e próximos passos estão em
[`ADR-057-F0-F1-2026-11-17.md`](../operations/testing/ADR-057-F0-F1-2026-11-17.md).

A branch de trabalho `feat/adr057-f1-route-snapshots` deriva de `origin/dev`.
F0 estático PASS; F1 permanece em andamento. O doctor do Runtime retorna
BLOCKED/RX por 17 divergências de Config Sync. Não fazer import, updatedb,
limpeza de cache ou qualquer escrita para ultrapassar esse bloqueio. Repetir
browser depois de uma reconciliação autorizada e baseline visual consistente;
continuar F2–F5 em commits focados e preservar ADR-056 encerrada.
