# Estado da sessão

> **Limite de registro:** no máximo 3 entradas. Registrar só ao encerrar uma fase ou o trabalho, não a cada microfase. Entradas mais antigas ficam em `docs/history/operations/ESTADO-DA-SESSAO-ARQUIVO-2026-11-05.md`, sem alteração de texto.


# F2 — Studio, conta e liberação HTMX em modais de perfil — 2026-11-05

- **Objetivo:** avançar os itens da Fase 2 (F2) do ADR-050 no Studio e Minha Conta.
- **Concluído:**
  - `d74e79a17e`: migra `studio-navigation.js` e `account-navigation.js` para HTMX (F2 item 1).
  - `bd834c58de`: `StudioProfileController` com raiz `#aculta-studio-profile-editor`, foco acessível `data-aculta-focus`, `tabindex="-1"` e bibliotecas HTMX (F2 item 2).
  - `0ffb7718a9`: alinha teste unitário `DomainPresentationContractTest` ao contrato de 4 níveis com `domain_sites` (352/352 PASS).
  - `99f3f4ee5d`: desbloqueia requisições HTMX nos modais de perfil (`AccountModalAccess` passa a reconhecer `HX-Request`, eliminando o 403 Forbidden que bloqueava o item 3).
- **Verificações:** Unit de `aculta_user` (154/154 PASS), Unit de `aculta_studio` (69/69 PASS), Unit de `aculta_portal` (352/352 PASS); `validate-aculta-user-photo.php` PASS (INV-FOTO-1 a INV-FOTO-11); `validate-account-address.php` PASS; `validate-aculta420-v2-sdc.php` (40/40 PASS via Drush); `validate-aculta420-v2-foundations.php` PASS; `validate-browser-validators.php` PASS; `validate-agent-token-budget.php` PASS.
- **Pendências:** migração final dos componentes de edição modal de bio e redes sociais para HTMX puro no tema V2 sem `use-ajax` (itens 3 e 4), e popup de foto de perfil (item 5).
- **Próximo passo:** implementar os diálogos HTMX nos cards de bio e redes, preservando invariantes de foto e regras de país único no endereço.

# Dívida de estilo resolvida — 2026-11-05

- **Objetivo:** eliminar as 189 classes mapeadas sem CSS e os nomes Bootstrap do mapa do V2, com prevenção por gate.
- **Concluído:** `92a5c3abde` cria `src/styles/portal-ui-map.css` com estilo mínimo por componente; remove 14 nomes Bootstrap (`bi`, `bi-*`, `breadcrumb`, `breadcrumb-item`, `btn`, `btn-link`, `form-control`, `btn-primary`, `btn-outline-primary`, `btn-outline-secondary`, `btn-sm`) do mapa; adiciona o gate `scripts/validate-aculta420-mapped-classes.php`; troca as classes de design do diálogo de cadastro do checkout por tokens `data-portal-ui` mapeados no tema. `47dcbee7e1` remove `.btn`, `.btn-link` e `.form-control` dos CSS portados e faz `validate-aculta420-v2-sdc.php` passar.
- **Verificações:** gate de classes PASS (455 classes, 0 exceções, 0 nomes Bootstrap); `validate-aculta420-v2-sdc.php` PASS (35 componentes); `validate-portal-ui-docs` PASS (501 tokens); `validate-module-theme-boundary` PASS (4253 verificações); suítes `aculta-htmx-f1` 43/43 e `aculta420-v2-footer` 6/6 PASS; `validate-aculta420-sdc-schemas` 55/764, browser validators 21 e foundations PASS; `updatedb:status` e `config:status` limpos.
- **Gates com falha preexistente/alheia:** `validate-agent-token-budget.php` (AGENTS.md raiz com 6501 bytes por alteração paralela do graphify), `validate-aculta-portal-drupal12.php` (versões divergentes) e `validate-portal-popup-rule.php` (9 ocorrências em Events/Paths).
- **Pendências:** o estilo mínimo não é desenho final por componente; a evolução visual continua como trabalho de produto. Migração das suítes para Playwright segue com o agente paralelo.
- **Próximo passo:** retomar F2 (navegação do Studio) ou a integração dos consumers dos SDCs.

# Fase 3 — purpose neutro e auditoria do catálogo — 2026-11-05

- **Objetivo:** continuar o roadmap do tema V2 pelas fases pendentes (3, 4 e 5).
- **Concluído:** `0c2d56e65b` documenta `breadcrumb`, `purpose-showcase` e `event-page-layout` e confirma 39 componentes com entrada/schema/story; verifica `ui_styles` 1.22.0 (`^10.3 || ^11 || ^12`). O commit desta entrada expõe `aculta_purpose` no Portal, aplica `data-purpose` no `<html>` do tema, corrige o gate de contrato de apresentação para `domain_sites` e registra a Fase 3 no roadmap/architecture.
- **Verificações:** `data-purpose` conferido no Runtime em Main (`main`), Conta (`account`), Eventos (`eventos`) e Wiki (`wiki`); `validate-domain-presentation-contract.php` PASS (259 checks, showcase DEFERRED por falta de request MAIN front-page no Drush); shell contract 13 checks PASS; SDC schemas 55/764 PASS; PHP lint e `git diff --check` PASS.
- **Gates com falha preexistente/alheia:** `validate-agent-token-budget.php` (AGENTS.md raiz com 6501 bytes por alteração paralela do graphify), `validate-aculta-portal-drupal12.php` (versões divergentes de nove módulos) e `validate-portal-popup-rule.php` (9 ocorrências em Events/Paths).
- **Pendências:** ajuste de tokens de destaque por purpose depende de `domain_presentation.accent` (D1); consumers reais dos SDCs G1; Fase 5 além do catálogo.
- **Próximo passo:** retomar F2 (navegação do Studio) ou integrar consumers dos SDCs com dados reais.
