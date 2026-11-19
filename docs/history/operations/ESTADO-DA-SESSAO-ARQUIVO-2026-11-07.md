

# V2 — formulário Revista — 2026-11-06

- **Objetivo:** resolver 404 e validar CKEditor no Runtime.
- **Estado:** causa: formulário administrativo foi solicitado no host Revista, mas a rota existe no host principal. `node.add` existe; UID 15 tem `create revista content`; ULI cria cookie compartilhado entre subdomínios. Suíte completa PASS: 8 grupos/32 checks, 12 viewports 1280/390/320, zero overflow. CKEditor usa `revista_editorial`, sem `full_html`; casos admin passaram.
- **Decisões:** ULI no host conta; formulário no principal. Sem alteração do editor/config. Modal de aceite aparece na captura; não foi submetido.
- **Cuidados:** espera estabilização do Gin após resize; `aculta_adm` testa formulário, CKEditor/toolbar em `page`.
- **Commits e arquivos:** Runtime testado sobre `bf9c6e0af0`; suíte, README, captures/manifest, TODO, roadmap, layouts e handoff.
- **Verificações:** Firefox ESR visível, `ALL PASS`; Revista em `tests/browser/capturas/aculta-editor/2026-11-06/after/`.
- **Pendências e bloqueios:** nenhum.
- **Próximo passo:** nenhum.