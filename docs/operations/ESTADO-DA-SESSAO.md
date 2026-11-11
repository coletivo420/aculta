# ADR-054/055 — revalidação UI Skins e catálogo de componentes — 2026-11-11

- **Objetivo:** corrigir o gate de apresentação standalone Links e revalidar o catálogo administrativo reportado.
- **Estado:** Links passou Firefox ESR 30/30, `ruderalis`, cinco larguras, claro/escuro, contraste ≥7,16:1 e sem overflow. Catálogo autenticado: 16 grupos, 84 componentes e story carregada; visitante anônimo recebe 403 esperado. Drawer Claro reportado separadamente do conteúdo.
- **Decisões e alternativas descartadas:** não alterar CSP por mensagens `report-only`; preservar shell Links sem `.page__header`, conforme contrato do módulo.
- **Cuidados:** 320 px pode mostrar itens do drawer Claro fora da viewport sem rolagem documental; sem evidência de falha do conteúdo SDC. Console JS 400 não reapareceu nesta captura.
- **Commits e arquivos:** pendente nesta entrada; runners, ADRs, inventário, TODO/status e manifests de captura.
- **Verificações:** UI Skins 134, Layouts 42, Layout Builder 276, UI Library 25, foundations 83 pares, UI Styles 40, status index, py_compile e git diff check PASS. Browser index FAIL em worktrees existentes/scripts ausentes; token budget FAIL por 7 violações globais. Zoom/teclado admin DEFERRED.
- **Pendências e bloqueios:** aceite dos nove domínios restantes, cache-hit, zoom, teclado administrativo e causa do 400 se voltar.
- **Próximo passo:** publicar esta microfase e seguir a próxima View/formulário do inventário.

# ADR-054/055 — categorias Wiki em UI Patterns Views — 2026-11-11

- **Objetivo:** migrar o wrapper visual de `wiki_categories:block_1` para o SDC reutilizável `layout-grid`.
- **Estado:** 15 termos e links preservados; consulta, vocabulário, acesso, peso e pager permanecem nativos. Sync/Runtime parseados coincidem; import parcial só da View após snapshot.
- **Verificações:** Firefox ESR 247/247 em home/listagem/artigo, 320/390/767/768/1440, claro/escuro; 15 categorias, grid 1/2 colunas, overflow e foco visível PASS. Views gate 95; Stylelint focal PASS. Manifesto e hashes em `tests/browser/capturas/ui-patterns-wiki-categories/2026-11-11/manifest.json`.
- **Visual:** amostras home 320 claro e 1440 escuro comparadas antes/depois sem mudança aparente. Zoom 200%, cache-hit, editor/admin, tecnologia assistiva e aceite do responsável DEFERRED. ADRs abertas.
- **Próximo passo:** validar gates documentais/status, publicar a microfase e seguir ao próximo consumidor com SDC existente.

# ADR-054/055 — lista de transparência em UI Patterns — 2026-11-11

- **Objetivo:** substituir o teaser visual genérico da View pública de documentos pelo SDC `document-card` e UI Patterns `layout-grid`.
- **Estado:** `aculta_documents:block_1` importada no Runtime após snapshot; 3 rows anônimas, H1/main únicos e cache contexts presentes. Sync/Runtime parseada coincide. Links de arquivo não comprovados: fixtures têm descrições vazias. Captura visual/teclado, escuro, zoom 200%, perfis editor/admin e cache-hit pendentes.
- **Decisões e alternativas descartadas:** preservar row nativa `entity:node` teaser e passar slots como render arrays; não reproduzir marcação da Field API em Twig.
- **Cuidados:** browser `aculta-document-viewer` testa mídia incorporada, não esta lista. Import alterou apenas a config da View.
- **Commits e arquivos:** `42295a4d3` publicado; componente `document-card`, template teaser, View Sync, inventário, ADRs e docs do tema.
- **Verificações:** schema SDC PASS (83/1786); render SDC Drupal PASS; Views PASS (60); PHPUnit 3/19 PASS com depreciação PHPUnit; PHPStan/PHPCS PASS; índice status PASS; `git diff --check` PASS. Stylelint DEFERRED (binário ausente). Token budget FAIL por 7 violações globais preexistentes.
- **Pendências e bloqueios:** browser, link com dados reais, perfis, zoom/cache e homologação ampla; ADRs permanecem abertas.
- **Próximo passo:** executar captura focal de `/transparencia` com browser disponível, revisar e publicar esta entrega isolada.
