# Gates do tema V2

Gates são scripts de validação do repositório. Rode os que se aplicam à mudança e cole a saída na entrega. Quando um gate não puder rodar no ambiente, registre como `DEFERRED` com o motivo.

Os caminhos são relativos à raiz do repositório. Gates "Drush" rodam com `php:script` do Drush, que exige `vendor/` instalado e o Drupal inicializado.

## Gates do tema

| Gate | Como rodar | O que verifica | Quando |
| --- | --- | --- | --- |
| `validate-aculta420-sdc-schemas.php` | `php scripts/validate-aculta420-sdc-schemas.php` | schemas dos componentes SDC | toda mudança em `components/` |
| `validate-aculta420-design-foundations.php` | `php scripts/validate-aculta420-design-foundations.php` | fundações de design do tema | mudança em tokens, base ou layout |
| `validate-browser-validators.php` | `php scripts/validate-browser-validators.php` | validadores de navegador | mudança em JavaScript ou em formulários |
| `validate-aculta420-v2-foundations.php` | `php scripts/validate-aculta420-v2-foundations.php` | tokens e pares de contraste da V2, incluindo o modo escuro (33 pares de texto acima de 4,5:1; 4 de foco e 4 de borda acima de 3:1) | mudança em tokens ou cores |
| `validate-aculta420-v2-sdc.php` | `php vendor/drush/drush.php php:script scripts/validate-aculta420-v2-sdc.php` | descoberta, assets e renderização isolada dos componentes V2; usa descoberta com backend nulo para incluir SDCs novos sem limpar cache persistente | toda mudança em componente |
| Configurações de superfície | `php vendor/drush/drush.php config:status` e verificação da disponibilidade de `ThemeSettingsProvider` | consistência do Config Sync e serviço usado por `SurfaceSettingsHooks` | alteração das opções administrativas do tema |
| `validate-aculta420-shell-contract.php` | `php vendor/drush/drush/drush.php php:script scripts/validate-aculta420-shell-contract.php` | contrato do shell: barra, navegação e regiões | mudança em `page.html.twig`, barra ou navegação |
| `validate-aculta420-foundation.php` | `php vendor/drush/drush/drush.php php:script scripts/validate-aculta420-foundation.php` | gate legado da V1 | **falha com a V2 ativa**, por exigir a V1 como tema público e Bootstrap 5 como base. Não use como sinal de aprovação da V2 |
| `validate-aculta420-studio-identity.php` | ver o cabeçalho do script | identidade do estúdio | não está na lista de gates do ADR-046; confirme o escopo antes de usar |

Os scripts `audit-aculta420-*.php` e `audit-aculta420-responsive-inventory.py` fazem inventário (consumidores de biblioteca, SDC assets, responsividade). Não são gates: a saída informa, não aprova.

## Gates do Portal que tocam o tema

| Gate | Quando |
| --- | --- |
| `php scripts/validate-aculta-user-photo.php` | mudança de avatar (regra: foto → acervo, sem inicial) |
| `php scripts/validate-admin-routes-main-domain.php` | qualquer ação administrativa na barra |
| `php scripts/validate-aculta-portal-drupal11.php` | mudança no Portal, incluindo contrato de variáveis |

## Verificação visual e de navegador

Não é um script de gate, mas faz parte da entrega de mudança visual (ver [AGENTS.md](../AGENTS.md)):

- Firefox ESR visível (`DISPLAY=:0`, geckodriver). Sem Chromium e sem headless.
- Larguras: 320, 390, 768, 1024 e 1440 CSS px, no mínimo.
- Modos claro e escuro. Zoom de 200%. Teclado (Tab, Shift+Tab, Enter, Escape).
- Movimento reduzido: `prefers-reduced-motion: reduce`.
- Estado anônimo e estado autenticado, em separado.
- Capturas em `$SUITE_OUT`, fora do repositório.

## Estado atual

- Os gates da V2 passaram nas entregas anteriores, conforme o [CHANGELOG.md](../CHANGELOG.md).
- **Account-menu:** o render isolado do componente apontou `summary_label` vazio quando a prop não era fornecida. A story anônima agora declara o rótulo e o Twig oferece fallback explícito. SDC schema, design foundations e shell passaram nesta rodada; o render Drupal foi repetido após a correção.
- **C4 — revisão estrutural do shell e micropágina Links:** schema SDC (55 componentes/764 checks), design foundations (2.468 checks), foundations V2 (55 pares), browser validators (21 checks), shell contract (13 checks), render SDC Drupal (24 componentes), `php -l` e `git diff --check` PASS. Capturas responsivas, tema claro/escuro, zoom 200% e teclado: DEFERRED, `DISPLAY` indisponível. O adapter de conteúdo Links permanece no inventário G3.
- **E1/P1 — layout-page:** schema SDC (55 componentes/764 checks), design foundations (2.468), foundations V2 (55 pares), shell contract (13 checks), render SDC Drupal (25 componentes), `php -l` e `git diff --check` PASS. Capturas 320/390/768/959/960/1440, claro/escuro, zoom 200%/400%, landmarks e teclado: DEFERRED, `DISPLAY` indisponível. PHPCS do validador SDC: PASS.
- **E2/P2 — mapeamento de variantes:** schema SDC (55/764), design foundations (2.468), shell contract (13), render SDC Drupal (25), PHP lint, PHPCS `Drupal,DrupalPractice` em `PagePresentationHooks.php` e `git diff --check` PASS. Firefox visual DEFERRED (`DISPLAY` indisponível); catálogo/produto de loja não executados, aguardando autorização explícita.
