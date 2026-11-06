# Estado da sessão

> **Limite de registro:** no máximo 3 entradas. Registrar só ao encerrar uma fase ou o trabalho, não a cada microfase. Entradas mais antigas ficam em `docs/history/operations/ESTADO-DA-SESSAO-ARQUIVO-2026-11-05.md`, sem alteração de texto.

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

# Runtime ACULTA420 V2 — Revista, PHPUnit, purpose e botões — 2026-11-06

- **Objetivo:** reconciliar configuração de teste, validar accent global da Revista, PHPUnit, palette/botões e registrar a evidência do shell.
- **Estado:** origem `aculta420_v2.settings`; `preprocess_node` adiciona `revista-teaser--accent-{green|yellow|red}` ao wrapper do node e ao SDC, com dependência de cache. Form UI Styles submetido; quatro teasers passaram no Firefox ESR (claro/escuro, 320/1440, sem overflow). Accent global restaurado vazio; banco: 5 nós Revista, 0 accents próprios; node 602 vazio. Campo legado fica fora do fluxo e preservado sem remoção destrutiva.
- **Configuração ativa:** CAPTCHA global `0`; divergências de captcha display e `core.extension` reconciliadas; `aculta420_v2.settings` criada. `config:status` ainda lista blocos/config local do tema administrativo, Turnstile sensível e definições legadas de field apenas no Sync.
- **Commits publicados antes deste handoff:** `c8e9f37f9e` (config/reconciliação e shell), `42ca2af7e9` (matriz palette/botões), `80aae13a1c` (wrapper/cache/teste Revista), `33561c8743` (manifest Revista), `77904cc2ea` (suíte de botões).
- **Verificações:** UI Styles 24 PASS; foundations 73 pares texto + 6 faixas PASS; SDC schema 55/764 PASS; SDC render 41 PASS; PHPUnit tema 12 testes/56 assertions PASS. Bootstrap criou diretório de saída a partir de ausência e a execução não mostrou aviso. Firefox: palette/botões 358/360; shell 108/120; os PNGs estão ignorados e só manifests versionados.
- **Pendências:** F3 permanece aberta pelos dois overflows do Fórum a 320 px. F2 permanece para aceitação visual do responsável; a matriz também detectou contraste insuficiente da navegação em claro a 1440 px nos dez purposes. A suíte ampla `aculta-editor/access.py`, após corrigir seu import sob `-I`, parou por ausência de CKEditor no formulário de Revista para `content_editor`; o formulário administrativo de UID 1 foi validado separadamente. Não alterar o shell nesta fase.
- **Próximo passo:** tratar o overflow do Fórum sem alterar o shell e repetir a suíte de botões; aguardar aceitação visual de F2 e verificar o fluxo editor quando o CKEditor do Runtime estiver disponível.


