# ADR-054/055 — UI Styles Entity Status — 2026-11-11

- **Objetivo:** integrar UI Styles Entity Status e retomar a falha reportada no catálogo de SDCs.
- **Estado:** Entity Status Sync/Runtime habilitados; classe `aculta-content-status--unpublished`. UI Patterns Library abriu em Firefox autenticado: 82 componentes, 16 grupos, 82 prévias, uma story carregada; sem 500, `maxScrollX=0` em 320/1280. Erros exatos de console/CSP ainda não correlacionados.
- **Decisões/cuidados:** import parcial do settings após snapshot; o plugin apenas marca visualmente conteúdo não publicado e seções LB.
- **Commits/arquivos:** pendente; configuração, tema, inventário e ADR-055.
- **Verificações:** UI Styles 40, foundations 83 pares, Skins 134, boundary 7872, Stylelint, índice e browser Library PASS. PHPUnit 1/7 com 54 deprecações. Token-budget DEFERRED: script tem SyntaxError na linha 40.
- **Pendências:** Entity Status visual/perfis/cache; capturar console e conferir JS agregado 400 observado pelo responsável. 320 tem elementos administrativos excedentes sem scroll efetivo.
- **Próximo passo:** inspecionar resposta e console do agregado JS da Library e reproduzir mensagens CSP na rota autenticada.

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

# ADR-054/055 — Piloto Views row/style/display — 2026-11-10

- **Objetivo:** integrar row/style/display na View Álbuns sem perder funções nativas.
- **Estado:** `aculta_albuns:page_1` usa `revista-album-card`, `layout-grid`, `views-display`; 3 resultados, 200 e acesso anônimo antes/depois. Firefox 1280/320 claro/escuro: 3 cartões, sem overflow ou alvo <44 px.
- **Decisão:** sources preservam field handlers; display SDC declara header, filtros, rows, empty, pager, more, footer e anexos.
- **Cuidados:** pager multi-página (só 3 resultados), cache-hit, teclado e zoom DEFERRED. Suíte transversal parou no probe independente da Flag; checks focados de View passaram.
- **Commits/arquivos:** base `9ef4ad36e`; config View, dois SDCs, gate, runner, inventário, ADRs, P8/plano, TODO/status e capturas.
- **Verificações:** contrato 40; SDC 82/1774; UI Library 25; Foundations 83 pares; browser/Runtime PASS no escopo focal.
- **Pendências:** demais Views/domínios, P8/P9, Page/Entity Status, Icons e RN-00–05.
- **Próximo passo:** migrar a próxima View com comparação de render, acesso e cache.
