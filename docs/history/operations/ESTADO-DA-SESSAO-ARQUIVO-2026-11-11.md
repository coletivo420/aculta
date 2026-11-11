# Arquivo — handoffs removidos do limite ativo em 2026-11-11

ADR-054/055 — Views editoriais da Revista — 2026-11-11

- **Objetivo:** identificar visuais de Views que já compõem SDCs e evitar migração duplicada.
- **Estado:** seis páginas de `revista_sessoes` herdam row nativa `entity:node`/`teaser`; template ACULTA passa render arrays como slots ao SDC `revista-teaser`. Sync/template confirmados; smoke anônimo da home: quatro cards, um H1/main e sem overflow em cinco larguras. `/revista` redireciona para `/`, sem atribuir cards ao View.
- **Decisões:** preservar row/View nativas porque o adapter já reutiliza o SDC e mantém cache/render arrays.
- **Cuidados:** não declarar saída/access/pager/cache como Runtime verificados.
- **Commits e arquivos:** pendente; inventário CSV/script, legacy inventory, ADR-054, TODO/changelog.
- **Verificações:** inventário 383 linhas e php -l PASS; SDC 82/1774 PASS; runtime/browser N/A, não houve mudança de configuração.
- **Pendências e bloqueios:** inspecionar saídas, acesso, pager/cache e rotas públicas da Revista; orçamento global falha em sete itens preexistentes.
- **Próximo passo:** validar visualmente uma rota pública com teste de navegador não destrutivo.

# ADR-054/055 — revalidação UI Skins e catálogo de componentes — 2026-11-11

- **Objetivo:** homologar UI Skins nos dez domínios e revalidar o catálogo administrativo reportado.
- **Estado:** UI Skins passou Firefox ESR 300/300 nos dez domínios, cinco larguras e dois modos, contraste ≥7,16:1 e sem overflow. Catálogo autenticado: 16 grupos, 84 componentes e story carregada; visitante anônimo recebe 403 esperado. Drawer Claro reportado separadamente. Busca de código/Sync/Portal confirma zero motor visual legado; cinco itens são `LEGACY_OBSOLETE`, sem fallback. 40 capturas e hashes estão no manifest versionado.
- **Decisões e alternativas descartadas:** não alterar CSP por mensagens `report-only`; preservar shell Links sem `.page__header`, conforme contrato do módulo.
- **Cuidados:** 320 px pode mostrar itens do drawer Claro fora da viewport sem rolagem documental; sem evidência de falha do conteúdo SDC. Console JS 400 não reapareceu nesta captura.
- **Commits e arquivos:** `ed60f6a36`, `e1a5b4ffb`, `9e9549b29` publicados; runners, ADRs, inventário, TODO/status e manifests de biblioteca e skins.
- **Verificações:** UI Skins 134, Layouts 42, Layout Builder 276, UI Library 25, foundations 83 pares, UI Styles 40, status index, py_compile, busca zero consumidores e git diff check PASS. Browser index FAIL em worktrees existentes/scripts ausentes; token budget FAIL por 7 violações globais. Zoom/teclado admin DEFERRED.
- **Pendências e bloqueios:** cache-hit, zoom 200%, teclado, perfis editor/admin, tecnologia assistiva e aceite visual humano.
- **Próximo passo:** migrar o próximo consumidor elegível do inventário, começando por Views públicas que preservem acesso e query.
