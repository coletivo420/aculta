# ADR-054/055 — Piloto UI Styles Block — 2026-11-11

- **Objetivo:** aplicar UI Styles Block a uma instância pública sem alterar a saída funcional da View.
- **Estado:** `aculta420_v2_aculta_documents` usa escolhas `raised` e `normal`; Runtime anônimo manteve três rows. Firefox ESR: 10/10 combinações de cinco larguras e dois modos, foco visível, contraste mínimo 12.39:1, padding responsivo, H1/main únicos e sem overflow.
- **Decisões e alternativas descartadas:** config importada isoladamente após snapshot SHA-256 `5760883484d6b9c30031ea5cfb427d8de09d1f1a1760a26e34c65e8b0e0b7745`; Page API aplica classe por região global e não permite seleção por Domain ID.
- **Cuidados:** Page não serve para skins de domínio; não repetir imports parciais a partir da pasta Sync inteira.
- **Commits e arquivos:** `b3337bcfc`; config do bloco, inventário CSV/JSON, gerador, browser e docs de ADR/tema/roadmap.
- **Verificações:** UI Styles 40, Views 60, tema-settings 16 e status-index 44 PASS; navegador 10/10; `drush cr` PASS. Token-budget FAIL por sete limites globais preexistentes. Browser-index geral FAIL por referências/worktrees preexistentes.
- **Pendências e bloqueios:** zoom 200%, variação cache-hit, revisão visual humana e demais instâncias de bloco pendentes.
- **Próximo passo:** migrar a próxima instância transversal com comparação isolada Runtime e revisar suporte de UI Styles Page por domínio.

ADR-054/055 — Views editoriais da Revista — 2026-11-11

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
- **Commits e arquivos:** `2d7d4dd62`; View Sync, script/inventário UI Suite, teste browser, tema e ADR-055.
- **Verificações:** Views cutover 60 checks, Theme Settings 16, SDC 41; navegador antes/depois PASS. Browser-index FAIL preexistente em worktrees aninhadas/referências obsoletas. Token-budget FAIL por oito pendências preexistentes, incluindo handoff que foi corrigido nesta entrega.
- **Pendências e bloqueios:** zoom 200%, cache-hit, revisão visual humana e demais displays/domínios ainda não homologados.
- **Próximo passo:** migrar a próxima View com comparação isolada de Runtime e expandir viewport/zoom/cache na homologação focal.
