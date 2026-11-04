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
| `validate-aculta420-v2-sdc.php` | `php vendor/drush/drush/drush.php php:script scripts/validate-aculta420-v2-sdc.php` | descoberta, assets e renderização isolada dos componentes V2 | toda mudança em componente |
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
- Páginas do Portal também com o tema Olivero (CLAUDE.md).
- Capturas em `$SUITE_OUT`, fora do repositório.

## Estado atual

- Os gates da V2 passaram nas entregas anteriores, conforme o [CHANGELOG.md](../CHANGELOG.md).
- **Account-menu (`1.2.0-dev.3` a `dev.11`):** a validação de renderização precisa ser refeita após a correção do `1.2.0-dev.5`, que o próprio changelog registra como pendente. Este ambiente não tem `vendor/`, então nenhum gate foi executado aqui. Execução fica para o Homelab (`DEFERRED`).
