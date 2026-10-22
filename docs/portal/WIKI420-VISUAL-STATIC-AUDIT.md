# Wiki420 — W420-UX 0.1-A: auditoria visual de código

**Data:** 2026-10-22 · **Branch:** `docs/aculta-forum-0.1.0-dev.1` · **Estado:** auditoria dirigida somente leitura do GitHub; sem Composer/Drupal/browser.

## Evidências inspecionadas

| Fonte real | Observação | Decisão de arquitetura visual |
| --- | --- | --- |
| `aculta_wiki.routing.yml` | Home `/wiki`, busca `/wiki/busca` e preview `/wiki/busca/previa` estão declarados | preservar contratos, auditar as demais rotas antes de sugerir navegação |
| `templates/node--wiki-entry--full.html.twig` | renderiza `aculta420:context-header` com `label` e `wiki-entry` com `show_heading: false` | integração SDC já existe; não criar outro leitor nem H1; testar conflito com page title Core por DOM |
| `components/patterns/wiki-entry/wiki-entry.twig` | oferece abas locais `Ler` + `actions`, H1 condicional, corpo, relacionados, referências, imagem/categorias | futura reutilização de `context-tabs` requer auditar primeiro rotas/actions e semântica de links |
| `components/patterns/wiki-entry/wiki-entry.css` | layout de leitura 78rem, coluna lateral, tipografia/links/foco por tokens | evoluir a partir do CSS presente; medir 320/390/768px, zoom/reflow, light/dark e TOC antes de refatorar |
| `css/components/wiki.css` | personaliza Domain Header para a Wiki, pesquisa, preview, listas e home com tokens | melhorar visual do header existente, sem substituí-lo; preservar Institution Bar |
| `docs/portal/WIKI.md` | descreve preview sob 3 caracteres/debounce, no-JS GET, ACL, sem autoria pública; discussão futura | manter fonte de verdade; não publicar autoria ou simular discussão ativa |
| `docs/portal/GANJALAB420-DASHBOARD-VISUAL.md` e `GANJALAB420-VISUAL-QUALITY-GATES.md` | percurso mobile-first, componentes neutros, estados verificáveis e gates por experiência | transportar método de especificação/gates, não o layout pedagógico |

## Lacunas antes da implementação

1. **Home:** inspecionar `WikiController::home`, View names/displays, títulos gerados e empty/results reais; evitar CTA/categorias inventados.
2. **Busca:** inspecionar `WikiController::search`, `WikiSearchFormBuilder`, acessibilidade do preview, tabindex, aria-live e duplicidade de formulário na home.
3. **Verbete:** conferir se Core page title já está suprimido no DOM real, quais fields são render arrays com cache/access, e se os estados `show_heading` cobrem teaser e article.
4. **Sumário:** levantar headings do conteúdo filtrado e IDs estáveis. Não introduzir TOC dinâmico por JS sem fonte segura e estratégia no-JS.
5. **Categoria:** identificar a rota do termo e templates de Views/Taxonomy existentes; não inventar caminho canônico.
6. **Discussão:** validar provider futuro Comment/Forum por verbete, ações de moderação e permissões; não gerar tópico no Growroom público.
7. **Revisões:** verificar quais operações Core estão realmente autorizadas e se autoria nominal continua protegida.
8. **Responsividade:** medir impacto da busca no Domain Header, menu mobile e sidebar do leitor em 320–390px; não deslocar ou esconder o shell.

## Matriz de reaproveitamento

| Uso Wiki | Base atual | Decisão |
| --- | --- | --- |
| H1 contextual | `aculta420:context-header` | reutilizar; gate H1 único/semântica/cache |
| Verbete e teaser | `aculta420:wiki-entry` | preservar e evoluir |
| Abas por rota | `aculta420:context-tabs` | condicionado a provider de links reais, sem duplicar tabs internas |
| Resultados vazios | `aculta420:empty-state` | usar somente após estado real View |
| Cartões e conteúdo relacionado | `aculta420:card` / `editorial-card` | reuso com slot, sem Twig avulso |
| Figura | `aculta420:figure` | quando consumidor permitir composição sem reprocessar Media |
| Índice/sumário | Core/HTML atual primeiro | SDC novo somente se lacuna e segundo consumidor comprovados |
| Estados editoriais | Form API/Moderation nativos | SDC apenas apresenta, não altera workflow |

## Próximo trabalho executável sem Composer

**W420-UX 0.1-A.2:** mapear controller, View e render arrays de Home e busca, cruzar campos e templates reais; registrar mapa de rotas e ownership de H1, menu, pesquisa e links. **W420-UX 0.1-B:** wireframes detalhados e estados das quatro telas principais, com revisão mobile-first. **B6-S1b:** mapear consumidores compartilhados com a auditoria do tema. Alterações funcionais e testes de Core SDC/UI Patterns/UI Styles permanecem condicionados ao ambiente de desenvolvimento; não marcar PASS.

**Restrições:** trabalhar só na PR #297, evitar arquivos das frentes paralelas, não criar novos plugins ou SDCs até provar necessidade, manter light/dark geometricamente equivalentes.
