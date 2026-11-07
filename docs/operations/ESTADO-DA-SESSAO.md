# Estado da sessão

> **Limite de registro:** no máximo 3 entradas. Registrar só ao encerrar uma fase ou o trabalho, não a cada microfase. Entradas mais antigas ficam em `docs/history/operations/ESTADO-DA-SESSAO-ARQUIVO-2026-11-05.md`, sem alteração de texto.

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

# V2 — Fórum, editor e config — 2026-11-06

- **Objetivo:** corrigir overflow e revisar config.
- **Estado:** Fórum corrigido. ESR: botões 360/360, shell 120/120 (10 purposes, dois modos, 320/1440). F3 concluída; F2 aguarda aceite visual. Contraste AA; probe corrigido. Editor bloqueado por 404 vazio após ULI, sem CKEditor.
- **Decisões:** tokens intactos (falso positivo); sem `drush cex`, sem diff seguro.
- **Cuidados:** `config:status` (12): DB-only `block.block.default_admin_{breadcrumbs,content,help,local_actions,messages,page_title,primary_local_tasks,secondary_local_tasks}` + `default_admin.settings`; Sync-only `field.field.node.revista.field_revista_accent`, `field.storage.node.field_revista_accent`; diferente `turnstile.settings` (só chaves de teste divergem; widget seguro igual). Preservados, sem valores.
- **Commits:** `28ab465088`, `ebdecca126`, `b2fd1f3bbe`.
- **Verificações:** foundations PASS (75 pares/6 faixas), diff check PASS. Editor: 5 checks PASS; admin não rodou.
- **Pendências:** host/ULI e CKEditor; aceite F2; config local/legada e chaves fora da exportação.
- **Próximo passo:** restaurar rota autenticada, repetir editor e obter aceite F2.

