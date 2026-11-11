# ADR-054/055 — UI Patterns Views Notícias — 2026-11-11

- **Objetivo:** migrar `aculta_news:block_1` para UI Patterns Views usando o SDC existente `revista-listing`.
- **Estado:** quatro teasers e HTTP 200 preservados; Firefox ESR antes/depois passou 10/10 em cinco larguras e dois modos, foco visível, contraste mínimo 7,16:1, um H1/main e sem overflow. Cache-hit, zoom 200% e revisão visual humana DEFERRED.
- **Decisões e alternativas descartadas:** manter row nativa `entity:node`/`teaser`, access/filter/sort/pager/query e cache; slot `view_rows` do SDC. Removida classe visual genérica só desta View.
- **Cuidados:** a página é `private/no-cache` e dynamic cache `UNCACHEABLE`; dois requests foram MISS. Não inferir variação por cache-hit.
- **Commits e arquivos:** `e69380f39`; View Sync, inventário, runner, browser/evidências, SDC e documentação ADR/roadmap.
- **Verificações:** Views 60, UI Styles 40, SDC schema 1774, render SDC 41, foundations 83 pares, Stylelint, PHP lint e cache rebuild PASS. Browser-index global e token-budget FAIL por pendências preexistentes/worktrees aninhadas.
- **Pendências e bloqueios:** executar zoom e revisão visual; resolver as falhas globais fora do escopo; migrar demais Views e domínios.
- **Próximo passo:** selecionar a próxima View com saída real e repetir snapshot, paridade funcional e A/B visual no Runtime.

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
