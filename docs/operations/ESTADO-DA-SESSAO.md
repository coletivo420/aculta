# Estado da sessão

> **Limite de registro:** no máximo 3 entradas. Registrar só ao encerrar uma fase ou o trabalho, não a cada microfase. Entradas mais antigas ficam em `docs/history/operations/ESTADO-DA-SESSAO-ARQUIVO-2026-11-05.md`, sem alteração de texto.

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

# HTMX F1 — busca do shell e cadastro popup — 2026-11-05

- **Objetivo:** concluir as pendências apontadas da microfase HTMX F1: migrar a busca do shell e o cadastro popup para HTMX, validar com sessão/Form API reais e executar a matriz visual.
- **Concluído:** `1efae40765` (Portal) migra `SearchPopupForm`, `RegisterPopupForm`, `SearchUtility` e `search-preview.js`; `31a061ef75` corrige a pendência documental dos filtros. No tema, `multidomain-bar.js` abre a busca por `hx-get`, sem Fetch nem `DOMParser`; `multidomain-bar.twig` ganhou `id="aculta-search-content"`, que faltava e impedia o alvo HTMX. Nova suíte `tests/browser/aculta-htmx-f1/`. Validação real encontrou e este commit corrige `data-hx-post=""` no login e no cadastro, que enviava o POST à página atual em vez da rota do popup; agora a URL é explícita.
- **Verificações:** no Runtime de desenvolvimento, login real da conta de teste autenticou e o `HX-Redirect` navegou; POST inválido do cadastro devolveu o fragmento com erro e não criou conta; busca retornou `data-hx-*` com `HX-Request` e manteve o script legado sem o cabeçalho. Suíte Firefox ESR visível: 43/43 checks (busca, login e gaveta de avisos autenticada em 320/390/768/959/960/1440 px, claro/escuro, prévia HTMX, foco visível, zoom real 200%, Escape com retorno de foco e fallback sem JavaScript). `validate-browser-validators` (21), `validate-aculta420-sdc-schemas` (55/764) e shell contract (13) PASS; PHP lint e `git diff --check` PASS.
- **Gates com falha preexistente:** `validate-aculta-portal-drupal11` e `validate-project-status-index` (versões divergentes de nove módulos), `validate-portal-popup-rule` (9 ocorrências em Events/Paths) e `validate-aculta420-v2-sdc` (`src/styles/buttons.css`). Nenhum arquivo dessas falhas foi alterado nesta entrega.
- **Decisões de 2026-11-05 (responsável):** (1) gavetas de mensagens/avisos mantêm a exceção documentada — dependem de JavaScript e não têm link nativo para a página completa, porque o Portal não fornece `all_href`; (2) o gatilho do cadastro no checkout de doação foi autorizado e migrado para o diálogo HTMX do shell, com `href` como fallback. Ambas registradas no ADR-050, no AGENTS/architecture do tema e no CHANGELOG de `aculta_donation`.
- **Pendências:** captura do diálogo de cadastro em pedido de doação real; restante do checkout/pagamento na F5. F1 está coberta; F2 pode ser retomada.
- **Próximo passo:** retomar F2 ou a F3 (o Fórum usa diálogos do CKEditor e precisa de decisão separada).
