# ADR-054/055 — Cutover focal UI Icons — 2026-11-11

- **Objetivo:** alinhar seletor UI Patterns ao Picker da Sync sem desativar submódulos Runtime.
- **Estado:** import parcial só de `ui_icons_patterns.settings` concluído; Runtime/Sync usam `icon_picker`, Drush confirma o element. Menus main/account mantêm packs Phosphor. Submódulos opcionais ainda divergem.
- **Decisões:** snapshot antes do import; não importar `core.extension` nem remover módulos. Hash anterior `63c2503c7e86e95a1c3f47a63ecb26fca524cee5583171b30050efd682b5fa26`.
- **Cuidados:** não alegar aceite visual/acessibilidade; sem consumidores SDC de prop identificados.
- **Commits e arquivos:** pendente; ADR-055, inventário Runtime/CSV, status e script.
- **Verificações:** import parcial e cache rebuild PASS; element Picker disponível; `UiIconsPackTest` 2/47, SDC 82/1774, UI Skins 134 PASS. Token-budget global FAIL (7 itens preexistentes).
- **Pendências:** validar formulário/menu com browser e reconciliar submódulos por delta revisado.
- **Próximo passo:** homologar Picker em interface administrativa real sem criar atribuições de ícone.

# ADR-054/055 — Views editoriais da Revista — 2026-11-11

- **Objetivo:** identificar visuais de Views que já compõem SDCs e evitar migração duplicada.
- **Estado:** seis páginas de `revista_sessoes` herdam row nativa `entity:node`/`teaser`; template ACULTA passa render arrays como slots ao SDC `revista-teaser`. Sync/template confirmados; smoke anônimo da home: quatro cards, um H1/main e sem overflow em cinco larguras. `/revista` redireciona para `/`, sem atribuir cards ao View.
- **Decisões:** preservar row/View nativas porque o adapter já reutiliza o SDC e mantém cache/render arrays.
- **Cuidados:** não declarar saída/access/pager/cache como Runtime verificados.
- **Commits e arquivos:** pendente; inventário CSV/script, legacy inventory, ADR-054, TODO/changelog.
- **Verificações:** inventário 383 linhas e php -l PASS; SDC 82/1774 PASS; runtime/browser N/A, não houve mudança de configuração.
- **Pendências e bloqueios:** inspecionar saídas, acesso, pager/cache e rotas públicas da Revista; orçamento global falha em sete itens preexistentes.
- **Próximo passo:** validar visualmente uma rota pública com teste de navegador não destrutivo.

# ADR-054/055 — Views style do catálogo de Eventos — 2026-11-11

- **Objetivo:** compor a View pública de Eventos com UI Patterns preservando a fonte funcional de rows.
- **Estado:** `eventos_publicos:block_1` usa `layout-grid`; Runtime anônimo manteve HTTP 200 e dois resultados. Firefox ESR verificou dois `card-cultural`, grid, foco visível e zero overflow em 320/1440 claro/escuro. Detalhe testado separadamente.
- **Decisões e cuidados:** custom row `aculta_events_catalogue_card` continua dono dos IDs ACL-filtrados, labels e ordem. Import parcial foi isolado a um YAML após snapshot SHA-256 `37a007b412a411f33d6b39c130ee98083aa3dbe8f836a3f9dd284dcc1c139d23`. Um import parcial amplo desabilitou três módulos opcionais; eles foram reabilitados imediatamente, sem mudança de dados. Não repetir import parcial com a pasta sync inteira.
- **Commits e arquivos:** pendente; View Sync, script/inventário UI Suite, teste browser, tema e ADR-055.
- **Verificações:** Views cutover 60 checks, Theme Settings 16, SDC 41; navegador antes/depois PASS. Browser-index FAIL preexistente em worktrees aninhadas/referências obsoletas. Token-budget FAIL por oito pendências preexistentes, incluindo handoff que foi corrigido nesta entrega.
- **Pendências e bloqueios:** zoom 200%, cache-hit, revisão visual humana e demais displays/domínios ainda não homologados.
- **Próximo passo:** migrar a próxima View com comparação isolada de Runtime e expandir viewport/zoom/cache na homologação focal.
