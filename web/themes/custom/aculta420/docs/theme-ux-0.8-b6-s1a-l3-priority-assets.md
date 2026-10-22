# THEME-UX 0.8 — B6-S1a-L3: auditoria prioritária de Twig, CSS e stories

**Data:** 2026-10-22 · **PR:** #297 · **Branch:** `docs/aculta-forum-0.1.0-dev.1` · **Escopo:** inspeção estática dirigida, ainda não a verificação exaustiva de todos os assets.

## Baseline confirmado

HEAD na abertura da fase: `1ecff3a455f81fa67580579da215a3d82a441176`. A árvore registra **41 Twig**, **32 CSS** e **65 `.story.yml`** em `components/`. Estes números são inventário de caminhos; **7 assets foram lidos neste lote**: 5 CSS e 3 stories (oito arquivos ao todo). Os Twig do lote anterior B6-S1a seguem como base, sem alegar reinspeção completa.

## Resultado por asset lido

| Caminho relativo a `components/` | Evidência objetiva | Próxima verificação |
| --- | --- | --- |
| `patterns/context-header/context-header.css` | tokens `--aculta-text-*`, foco visível e layout em 48rem; título usa `overflow-wrap: anywhere` | geometria light/dark, reflow 320 px, H1 único e shell no Runtime |
| `patterns/context-tabs/context-tabs.css` | links com `min-height: 2.75rem`, estilos `aria-current=page`, `ul/li` provenientes de render array | foco, navegação real por URL, variação de comprimento e comportamento sem JS |
| `feedback/empty-state/empty-state.css` | tokens semânticos, superfícies e área de ações com quebra | contraste e componentes autorizados em contexto real |
| `feedback/messenger-state/messenger-state.css` | tokens de feedback e variantes error/loading | leitura por tecnologia assistiva com atualização dinâmica |
| `patterns/cart-offcanvas/cart-offcanvas.css` | estrutura de gaveta com rolagem interna, fundo e bordas via tokens | foco, altura mobile, Bootstrap offcanvas, Escape e restauração de foco |
| `patterns/context-header/context-header.default.story.yml` | props `eyebrow/title/subtitle` compatíveis com metadata; slots `back_link/actions` com links `href="#"` | substituir links de demonstração por fixtures válidas ou cenários sem ação ao homologar navegação |
| `patterns/context-tabs/context-tabs.studio.story.yml` | prop `label` e slot `items` existentes; links fragmentados `#revista/#wiki/#cursos` | stories são demonstrativas: **não provam navegação de rotas** |
| `patterns/cart-offcanvas/cart-offcanvas.default.story.yml` | story fornece prop `items`, ausente da metadata do SDC; metadata possui `sites` | **Finding L3-01: incompatibilidade story/contrato**; conferir Twig consumidor antes da correção |

## Findings e precedência

### B6-S1a-L3-01 — P1: story usa prop não declarada

`patterns/cart-offcanvas/cart-offcanvas.default.story.yml` declara `props.items`, enquanto `patterns/cart-offcanvas/cart-offcanvas.component.yml` não a define. A prop pode ser ignorada ou gerar advertência conforme o renderer; isso exige validação, não há prova de quebra de carrinho em produção. **Próximo passo:** ler Twig e comparar `sites` versus `items`; corrigir a fixture para um cenário suportado pelo componente, sem alterar a origem dos dados Commerce ou introduzir contrato paralelo.

### B6-S1a-L3-02 — P2: navegação fictícia em stories

As stories lidas do Context Header e Context Tabs usam `href="#"` ou fragmentos locais como demonstração. **Não utilizar essas stories como prova de links reais, back/forward ou AJAX.** A homologação B6-R deve usar URLs do provider; fixtures de catálogo podem demonstrar apresentação sem alegar funcionalidade.

### B6-S1a-L3-03 — P1: coerência visual depende de Runtime

Os cinco CSS usam tokens semânticos nos trechos inspecionados. Isso não comprova contraste, ausência de overflow, responsividade global, estados Bootstrap, geometria light/dark ou conformidade WCAG. Não criar CSS novo antes de medir problema objetivo.

## Continuação rigorosa

1. **B6-S1a-L3b:** inspecionar conteúdo dos **34 Twig não lidos nos lotes B6-S1a anteriores**, **27 CSS restantes** e **62 stories restantes**, com registro individual e análise das props usadas em stories.
2. **B6-S1b:** mapear renderizadores e consumidores em PHP/Twig/Views/config; verificar access/cache/contexto e papel das ferramentas UI Patterns/UI Styles.
3. **B6-S2:** corrigir `cart-offcanvas` story somente após conferir Twig, e migrar schemas legados de doação com gates; commits pequenos.
4. **B6-R:** validar SDC Devel, UI Patterns Library, telas e domínios reais, A11y, teclado, zoom, AJAX/race, breakpoints e Core Drupal.

## Guardrails

Preservar integralmente Institution Bar + Domain Header em todos os purposes. Reusar somente componentes com base SDC/Core e compatibilidade UI Suite comprovada; não portar Twig solto como componente transversal. Esta fase **não alterou CSS, Twig, PHP nem runtime**, apenas documentação.
