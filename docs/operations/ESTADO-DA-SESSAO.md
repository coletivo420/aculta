# Estado da sessão

> **Limite de registro:** no máximo 3 entradas. Registrar só ao encerrar uma fase ou o trabalho, não a cada microfase. Entradas mais antigas ficam em `docs/history/operations/ESTADO-DA-SESSAO-ARQUIVO-2026-11-05.md`, sem alteração de texto.

# V2 — UI Patterns Library — 2026-11-07

- **Objetivo:** organizar visualmente `/painel-administrativo/aparencia/ui/components`, corrigir a barra institucional e definir container próprio centralizado.
- **Estado:** commits `ed04d341e4` e `4987d69102` publicados; `f28ecce9fa` contém os ajustes desta etapa. Rotas UI Patterns excluídas do container por purpose. Overview usa largura máxima 1200px, sem caixa externa; cada componente em bloco vertical, preservando layouts nativos internos. Ajustes mais recentes mantêm previews dinâmicos, normalizam posição de Calendário/Conexões e convertem slots Perfil/Navegação para render arrays Drupal.
- **Decisões:** stories/pré-visualizações aparecem antes dos metadados recolhidos. CSS contém overflow e elementos posicionados por componente. Nada de colunas artificiais.
- **Cuidados:** preview Chromium com CSS local injetado confirmou 108 blocos e sem overflow em 320/1440. Drupal respondeu 200 após corrigir declaração duplicada que causava 500. Cache Twig/agregados no Runtime não foi reconstruído; aceite visual com assets servidos permanece pendente.
- **Commits e arquivos:** `ed04d341e4` layout inicial; ajuste de wrapper/containment a publicar. Tema `aculta420_v2` (hooks, layout, templates, CSS, testes, TODO, CHANGELOG e docs).
- **Verificações:** Stylelint, schema SDC (55/764), token budget e `git diff --check` PASS. Renderer Drupal confirmou saída de 4/4 componentes. Gate `validate-aculta420-v2-sdc.php` DEFERRED/FAIL anterior ao render: falso positivo em `studio-calendar.twig` devido ao sufixo de classe `__add-btn` ser confundido com `.btn`.
- **Pendências e bloqueios:** cache Drupal reconstruído no Runtime; `updatedb:status` e `config:status` ainda mostram pendências preexistentes, preservadas sem execução/import. Aceite visual autenticado na rota após a mudança permanece pendente.
- **Próximo passo:** confirmar visualmente a rota autenticada no Runtime; não importar configuração nem executar updates sem escopo explícito.

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
