# THEME-UX 0.8 — B6-S1a: achados de contratos SDC (lote prioritário)

**Data:** 2026-10-22 · **PR:** #297 · **Branch:** `docs/aculta-forum-0.1.0-dev.1` · **Estado:** inspeção estática dirigida, não auditoria integral de 41 contratos.

## Recorte e provas

A árvore remota nesta branch contém **41** arquivos `.component.yml` e **65** `.story.yml` sob `web/themes/custom/aculta420/components/` (contagem de caminhos, sem análise do conteúdo das stories). Foram lidos **13 contratos de metadados** e **7 templates Twig** correspondentes. Os demais 28 contratos e os 34 Twig restantes ainda precisam de leitura individual. Nenhuma validação de schema Core/Drupal, SDC Devel, UI Patterns Library ou execução no homelab ocorreu neste lote.

## Matriz do lote inspecionado

| SDC | Evidência em metadata/Twig | Classificação e próximo gate |
| --- | --- | --- |
| `patterns/context-header` | Schema Core declarado; `title` requerido, slots `breadcrumbs/back_link/actions`; Twig emite `<h1>` sempre. | **SDC-PARCIAL — dependência de integração:** conferir supressão seletiva do H1 Core e title ownership em consumidores reais; sem substituir shell. |
| `patterns/context-tabs` | `label` texto e slot `items`; Twig usa `<nav>`, não `role=tab`, e recebe links já preparados. | **SDC-PARCIAL — consumidor:** provar `aria-current`, URLs reais, ACL, fallback no-JS e história AJAX no Studio. |
| `feedback/empty-state` | `compact` booleano; slots `message/actions`; Twig só apresenta conteúdo. | **SDC-PARCIAL — consumer/cache:** adequado como superfície genérica candidata; conferir conteúdo e actions autorizados em Wiki/LMS. |
| `feedback/messenger-state` | `state` enum `empty/error/loading`; Twig atribui `role=alert` em erro, `role=status` nos demais e `aria-busy` em loading. | **SDC-PARCIAL — A11y:** verificar anúncios reais e se a região é atualizada sem anunciar mensagens obsoletas. Não substituir automaticamente por empty-state. |
| `patterns/card` | `status: stable`; variantes `default/horizontal/overlay`, slots `image/header/content/footer`; Twig usa Bootstrap classes e `add_class` em mídia. | **SDC-PARCIAL — revisão semântica:** validar render arrays de mídia e layouts em telas reais; status documental stable não constitui aprovação Runtime. |
| `patterns/product-tabs` | Bootstrap `data-bs-toggle=tab`, `role=tablist`, painéis vinculados pelo `id` prop. | **NÃO REUTILIZAR COMO NAVEGAÇÃO DE ROTAS**; verificar unicidade de `id` em múltiplas instâncias. |
| `patterns/content-section` | Metadados documentam título de seção h2/subtítulo h3. | **NÃO REUTILIZAR COMO CONTEXT HEADER H1**; preservar papel editorial. Twig não reinspecionado neste lote; evidência anterior em M0-S. |
| `patterns/wiki-entry` | `show_heading` booleano com default true; Twig de artigo imprime H1 somente quando `show_heading` não é false. | **SDC-PARCIAL — integração:** conferir que o provider usa `show_heading=false` quando context-header é dono do título. |
| `patterns/alerts-trigger` | `href` requerido; contadores tipados e URL de rodapé; descrição declara Bootstrap/AJAX. | **SDC-PARCIAL — overlay:** checar race, autenticação, Escape, focus return e origem no B5-R. Twig não reinspecionado. |
| `patterns/messenger-trigger` | `href` requerido; contador e `all_href`; descrição declara AJAX. | **SDC-PARCIAL — overlay:** conferir o mesmo contrato e avisos de leitura; Twig não reinspecionado. |
| `patterns/cart-offcanvas` | `sites` é array sem `items` schema interno; descrição informal enumera campos; outras props tipadas. | **FINDING B6-S1a-01 (P2):** revisar `sites.items`/objetos aninhados e necessidade de schema explícito antes de exposição em UI Patterns; preservar dados do Portal. Twig não reinspecionado. |
| `content/qr-preview` | `src` e `alt` obrigatórios; `caption` opcional; schema Core declarado. | **SDC-PARCIAL — mídia:** revisar URL, alt e story em consumidor real; Twig não reinspecionado. |
| `content/qr-download` | `formats` array de objetos com `label/url` obrigatórios nos items; `title` obrigatório. | **SDC-PARCIAL — ações:** validar URLs seguras e títulos localizados nos providers; Twig não reinspecionado. |

## Findings acionáveis

- **B6-S1a-01 / P2 / `patterns/cart-offcanvas/cart-offcanvas.component.yml`:** o array `sites` carece de declaração estrutural detalhada dos itens, apesar do formato esperado estar descrito em prosa. Verificar o suporte do schema Core e UI Patterns para `items.properties`; propor correção mínima com fixtures, sem mudar os dados da Commerce ou a renderização. Nenhuma alteração de código efetuada nesta auditoria.
- **B6-S1a-02 / P1 / `patterns/context-header/context-header.twig` + `patterns/wiki-entry/wiki-entry.twig`:** ambos podem emitir H1 dependendo das props. Contrato depende de controle coordenado pelo provider. Mapear os consumidores antes de modificar o SDC; jamais esconder H1 redundante por CSS.
- **B6-S1a-03 / P1 / `patterns/context-tabs` e `patterns/product-tabs`:** tipologias distintas. `context-tabs` recebe links de rota; `product-tabs` comanda painéis de conteúdo Bootstrap. Não reaproveitar um para cumprir papel do outro.

## Continuidade (microfases)

1. **B6-S1a-L2:** ler e registrar os 28 metadados não cobertos, inspecionar Twig/CSS/stories dos 41 e executar checks estáticos em checkout controlado. Anotar caminhos, linhas e severidades por finding, sem estimar PASS.
2. **B6-S1b:** localizar consumidores efetivos em PHP/Twig/Views/config e trilha dos render arrays/access/cache.
3. **B6-S2:** corrigir somente achados reproduzidos, cada correção com story, documentação e validação isolada; não substituir implementação existente sem motivo.
4. **B6-R:** executar homelab e navegador; preservar Institution Bar e Domain Header em todas as rotas/domínios; validar foco, H1 único, 320–1440px, zoom, light/dark, UI Patterns/UI Styles, ACL/cache.

**Política de reuso:** somente SDC Core + UI Suite compatível. Twig solto não é base de compartilhamento; não criar SDC duplicado nem transportar comportamento de módulos ao tema.

**Referências:** [Inventário B6-S1](theme-ux-0.8-b6-s1-contract-audit.md) · [Consolidação B6](theme-ux-0.8-b6-consolidation.md) · [Shell](shell.md).
