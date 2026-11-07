# Estado da sessão

> **Limite de registro:** no máximo 3 entradas. Registrar só ao encerrar uma fase ou o trabalho, não a cada microfase. Entradas mais antigas ficam em `docs/history/operations/ESTADO-DA-SESSAO-ARQUIVO-2026-11-05.md`, sem alteração de texto.

# V2 — UI Patterns Library — 2026-11-07

- **Objetivo:** organizar visualmente `/painel-administrativo/aparencia/ui/components`, corrigir a barra institucional e definir container próprio centralizado.
- **Estado:** commits `ed04d341e4`, `4987d69102` e `c57fd98d68` publicados; o commit desta entrada contém esta rodada. Ajustes locais desta rodada resolvem stories PHP no preprocess do overview/single; Menu Studio usa provider atual, Pagination tem story de props, Dialog slots renderizam como HTML e biblioteca de diálogo é dependência explícita; Barra institucional passa a fluir no bloco.
- **Decisões:** stories/pré-visualizações aparecem antes dos metadados recolhidos. CSS contém overflow e elementos posicionados por componente. Nada de colunas artificiais.
- **Cuidados:** preview Chromium com CSS local injetado confirmou 108 blocos e sem overflow em 320/1440. Drupal respondeu 200 após corrigir declaração duplicada que causava 500. Cache foi reconstruído após a implementação; aceite visual autenticado permanece pendente.
- **Commits e arquivos:** `ed04d341e4`, `4987d69102`, `c57fd98d68` e o commit desta entrada. Tema `aculta420_v2` (hooks, stories, layout, templates, CSS, testes, TODO, CHANGELOG e docs).
- **Verificações:** renderer Drupal 4/4 (Dialog, Studio menu, Barra, Pagination); dependência `utility-drawers` confirmada nas bibliotecas Dialog/Menu/Barra; stories dinâmicas customizadas e hook testados. Schema SDC 55/764, PHP lint e Stylelint do CSS alterado PASS. `npm run lint:css` encontra violações preexistentes em `src/styles/forum.css`; gate V2 SDC encontra falso positivo `__add-btn` em `studio-calendar.twig`. Token budget e `git diff --check` PASS.
- **Pendências e bloqueios:** cache Drupal reconstruído no Runtime. `updatedb:status` e `config:status` ainda mostram pendências preexistentes, preservadas sem execução/import. Aceite visual autenticado e teste de clique no browser permanecem pendentes.
- **Próximo passo:** confirmar visualmente e clicar nas prévias no overview autenticado; não importar configuração nem executar updates sem escopo explícito.

# V2 — Stylelint CSS — 2026-11-07

- **Objetivo:** corrigir as violações Stylelint no CSS da V2 e fazer a configuração compatível com os contratos locais.
- **Estado:** CSS normalizado pelo preset Drupal; nenhuma mudança intencional de apresentação. Stylelint e gate de fundações PASS.
- **Decisões e alternativas descartadas:** mantidas regras compactas usadas pelo tema; exceções para `dvh`, `@container`, `@starting-style`, `.75rem` e nomes Inter/Oswald, porque o preset legado conflita com recursos e contratos validados da V2.
- **Cuidados:** auto-fix reordena declarações e normaliza valores equivalentes; `color-scheme` permanece no grupo claro. Não substituir o preset sem repetir os gates de fundações.
- **Commits e arquivos:** `ecdb0a84f8`; `.stylelintrc.json`, CSS em `web/themes/custom/aculta420_v2`, TODO, CHANGELOG e roadmap.
- **Verificações:** `npm run lint:css`, `php scripts/validate-aculta420-v2-foundations.php`, `php scripts/validate-agent-token-budget.php` e `git diff --check` PASS. Captura visual não repetida: CSS passou lint e gate de tokens; sem alteração de comportamento pretendida.
- **Pendências e bloqueios:** nenhuma desta entrega; validação visual de fases anteriores mantém seu status próprio.
- **Próximo passo:** seguir com o próximo item do roadmap V2.

# V2 — formulário Revista — 2026-11-06

- **Objetivo:** resolver 404 e validar CKEditor no Runtime.
- **Estado:** causa: formulário administrativo foi solicitado no host Revista, mas a rota existe no host principal. `node.add` existe; UID 15 tem `create revista content`; ULI cria cookie compartilhado entre subdomínios. Suíte completa PASS: 8 grupos/32 checks, 12 viewports 1280/390/320, zero overflow. CKEditor usa `revista_editorial`, sem `full_html`; casos admin passaram.
- **Decisões:** ULI no host conta; formulário no principal. Sem alteração do editor/config. Modal de aceite aparece na captura; não foi submetido.
- **Cuidados:** espera estabilização do Gin após resize; `aculta_adm` testa formulário, CKEditor/toolbar em `page`.
- **Commits e arquivos:** Runtime testado sobre `bf9c6e0af0`; suíte, README, captures/manifest, TODO, roadmap, layouts e handoff.
- **Verificações:** Firefox ESR visível, `ALL PASS`; Revista em `tests/browser/capturas/aculta-editor/2026-11-06/after/`.
- **Pendências e bloqueios:** nenhum.
- **Próximo passo:** nenhum.
