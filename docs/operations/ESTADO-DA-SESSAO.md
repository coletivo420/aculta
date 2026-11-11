# ADR-054/055 — homologação residual, galerias e correções — 2026-11-11

- **Objetivo:** corrigir inconsistências das ADRs, publicar a migração de `/galleries` e fechar as pendências de zoom, teclado e cache.
- **Estado:** `media_galleries:page_1` usa row `revista-album-card` e style `layout-grid`, marcada `UI_SUITE_NATIVE` no inventário. Homologação residual 70/70 em 320/768/1440 nos dez domínios (teclado com `:focus-visible`, zoom 200% sem overflow, cache observado). Teste autenticado pelo `auth-login.mjs` confirmou login de administrador e corrigiu H1 duplicado no dashboard do Studio (`studio-menu`). ADRs corrigidas: `ui_patterns_ckeditor5` fora do projeto, Fluent Emoji tem 66 SVGs (mínimo 48 no teste) e UI Styles Page está habilitado sem mapeamento de regiões.
- **Decisões e alternativas descartadas:** zoom emulado por `layout.css.devPixelsPerPx=2` (atalhos de teclado não chegam ao chrome via WebDriver); não duplicar o `auth-login.mjs` em Python; corrigir o gate de índice para ignorar `.claude/worktrees`.
- **Cuidados:** rotas públicas respondem `private/no-cache` e `UNCACHEABLE`, portanto cache-hit não é mensurável. O login autentica e grava a sessão, mas a requisição seguinte com o cookie emitido responde anônima; a causa não foi isolada e bloqueia perfis autenticados.
- **Commits e arquivos:** `28509465b`, `e8b7efc09`, `eea9b4129`, `0f8ae8f2d`, `2f0b198f2`, `1c6f98d0c`, `0210e5c86` publicados; runner de aceitação, manifest, ADRs, inventários, status, READMEs, gate de índice e SDC `studio-menu`.
- **Verificações:** acceptance 70/70; browser index 148 PASS; UI Patterns Views cutover 95 PASS; SDC 1801, UI Skins 134, UI Styles 40, boundary 7888 PASS; Playwright login PASS; py_compile e JSON OK.
- **Pendências e bloqueios:** aceite visual humano; sessão autenticada (configuração `SSESS`/`SESS`); cache-hit enquanto a política for private/uncacheable; RN-00–05 e demais consumidores do inventário.
- **Próximo passo:** corrigir a configuração de sessão do ambiente de desenvolvimento, concluir RN-05 com a sessão de renomeação e seguir ao próximo consumidor público.

# ADR-054/055 — revalidação UI Skins e catálogo de componentes — 2026-11-11

- **Objetivo:** homologar UI Skins nos dez domínios e revalidar o catálogo administrativo reportado.
- **Estado:** UI Skins passou Firefox ESR 300/300 nos dez domínios, cinco larguras e dois modos, contraste ≥7,16:1 e sem overflow. Catálogo autenticado: 16 grupos, 84 componentes e story carregada; visitante anônimo recebe 403 esperado. Drawer Claro reportado separadamente. Busca de código/Sync/Portal confirma zero motor visual legado; cinco itens são `LEGACY_OBSOLETE`, sem fallback. 40 capturas e hashes estão no manifest versionado.
- **Decisões e alternativas descartadas:** não alterar CSP por mensagens `report-only`; preservar shell Links sem `.page__header`, conforme contrato do módulo.
- **Cuidados:** 320 px pode mostrar itens do drawer Claro fora da viewport sem rolagem documental; sem evidência de falha do conteúdo SDC. Console JS 400 não reapareceu nesta captura.
- **Commits e arquivos:** `ed60f6a36`, `e1a5b4ffb`, `9e9549b29` publicados; runners, ADRs, inventário, TODO/status e manifests de biblioteca e skins.
- **Verificações:** UI Skins 134, Layouts 42, Layout Builder 276, UI Library 25, foundations 83 pares, UI Styles 40, status index, py_compile, busca zero consumidores e git diff check PASS. Browser index FAIL em worktrees existentes/scripts ausentes; token budget FAIL por 7 violações globais. Zoom/teclado admin DEFERRED.
- **Pendências e bloqueios:** cache-hit, zoom 200%, teclado, perfis editor/admin, tecnologia assistiva e aceite visual humano.
- **Próximo passo:** migrar o próximo consumidor elegível do inventário, começando por Views públicas que preservem acesso e query.

# ADR-054/055 — categorias Wiki em UI Patterns Views — 2026-11-11

- **Objetivo:** migrar o wrapper visual de `wiki_categories:block_1` para o SDC reutilizável `layout-grid`.
- **Estado:** 15 termos e links preservados; consulta, vocabulário, acesso, peso e pager permanecem nativos. Sync/Runtime parseados coincidem; import parcial só da View após snapshot.
- **Verificações:** Firefox ESR 247/247 em home/listagem/artigo, 320/390/767/768/1440, claro/escuro; 15 categorias, grid 1/2 colunas, overflow e foco visível PASS. Views gate 95; Stylelint focal PASS. Manifesto e hashes em `tests/browser/capturas/ui-patterns-wiki-categories/2026-11-11/manifest.json`.
- **Visual:** amostras home 320 claro e 1440 escuro comparadas antes/depois sem mudança aparente. Zoom 200%, cache-hit, editor/admin, tecnologia assistiva e aceite do responsável DEFERRED. ADRs abertas.
- **Próximo passo:** validar gates documentais/status, publicar a microfase e seguir ao próximo consumidor com SDC existente.
