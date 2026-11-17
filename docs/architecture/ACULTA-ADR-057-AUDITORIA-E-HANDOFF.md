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

Continuar F1 com evidências reais e F2 em microcommits na dev, sempre verificando HEAD e concorrência. Não encerrar ADR-054/055/057 enquanto houver gates obrigatórios DEFERRED.
