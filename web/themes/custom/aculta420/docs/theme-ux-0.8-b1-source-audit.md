# THEME-UX 0.8-B1 — Auditoria de origem: breadcrumb, hierarquia e Context Header

**Data:** 2026-10-22 · **Escopo:** inspeção de arquivos committed; **não** é homologação no Runtime.
**Continuidade:** [M2/B1](theme-ux-0.8-m2-b1-context-header.md), [shell](shell.md), [roadmap visual](visual-cross-product-roadmap.md).

## Evidências diretas

| Arquivo | Constatação no código | Efeito na decisão |
| --- | --- | --- |
| `templates/navigation/breadcrumb.html.twig` | gera `nav` + `ol.breadcrumb`; links a partir da variável `breadcrumb`; elemento final opcional com `aria-current=page` | **não** duplicar breadcrumbs em um novo componente; renderizar o breadcrumb Core existente como slot se necessário |
| `css/components/breadcrumb.css` | já estiliza `aculta-breadcrumb`, foco visível, separadores e tokens light/dark | reaproveitar tokens e classes após verificar wrapper efetivo e posicionamento DOM |
| `components/patterns/content-section/content-section.component.yml` | schema SDC, `title`/ `heading`, slot `body`, variante editorial | candidato apenas para seção de conteúdo, não para H1 |
| `components/patterns/content-section/content-section.twig` | sempre produz `section`, `h2` para título e `h3` para heading | **incompatível** com context-header H1 sem alterar semântica existente; não modificar silenciosamente |
| `components/patterns/product-tabs/product-tabs.twig` | usa buttons com `data-bs-toggle=tab` e `role=tablist` | adequado a painéis internos Commerce, **não** a navegação de rotas GanjaLab |
| `components/patterns/card/card.component.yml` | SDC estável com variantes e slots | não é equivalente ao title/breadcrumb Core |

### Pontos a inspecionar no Runtime antes de codificar
1. Local real da Institution Bar, Domain Header, breadcrumb e page-title nas páginas de Conta, GanjaLab, GanjaLab420, Revista e Wiki.
2. Se breadcrumb tem wrapper `.aculta-breadcrumb`: CSS depende dele, e o template de breadcrumb usa `.aculta-breadcrumb__nav`; a integração pode ocorrer fora do template, **não afirmar bug** sem DOM.
3. `breadcrumb.html.twig` só emite `nav` quando `breadcrumb` não está vazio; validar cenários em que somente o item atual esteja disponível.
4. Quem produz o H1 efetivo e se algum template de artigo/curso já o possui.
5. Controles de voltar reais, cache/access, retorno AJAX e navigation fallback sem JS.

## Decisão de desenho: sem SDC duplicando o shell

**Fatia B1-01, proposta (não implementada):** um SDC **contextual** com slots de `breadcrumbs` e `actions` e conteúdo `eyebrow`/`subtitle`; título pode ser um **slot renderizável de page title** para preservar attachments/cache. O componente não inclui Institution Bar, Domain Header, menu de purpose nem lógica de URL.

A estrutura SDC seria `header` (região interna do `main`) com uma classe de layout tokenizada. `h1` só é emitido se o Core/owner ceder essa responsabilidade de maneira explícita; fallback preferencial é manter o H1 do Core fora do SDC e renderizar nele apenas contexto e ações, evitando duplicação. Nomes finais de props e variante dependerão do primeiro consumidor real.

### Dois consumidores mínimos para justificar criação
- Educação: Sala de Aprendizagem GanjaLab420 realmente existente no Runtime, ou outra tela efetiva de curso; confirmar rota, provider e data/access.
- Editorial: Wiki **ou** Revista com página realmente publicada; confirmar route, HTML, H1 e acessibilidade.
- O novo SDC só será implementado após provar que composição de breadcrumb/page title existente não resolve o cenário de ambas as telas.

## Matriz de decisão para o executor
| Verificação | Se passar | Se falhar |
| --- | --- | --- |
| Core breadcrumb + page title atendem ambos | manter apresentação Core; sem SDC novo | propor composição contextual SDC |
| `content-section` atende sem mudar semântica? | variante SDC tipada + story | usar novo padrão SDC independente |
| Ao menos dois consumidores reais? | implementar fatia mínima | manter somente planejamento |
| UI Patterns Library renderiza fixtures? | confirmar apresentação | ajustar metadados/stories |
| UI Styles e tokens são adequados? | reaproveitar | atualizar estilos semânticos no tema, não CSS por módulo |
| Shell multidomínio persiste nos purposes? | continuar QA | bloquear integração |

## Critérios de QA não executados
320/390/768/1280/1440px, keyboard/focus, 200–400% reflow, no-JS/AJAX, WCAG 2.2 AA, dark/light com geometria idêntica, único H1 por página, acesso/cache e integridade do shell. **Todos PENDENTES.**

## Próxima fatia possível fora do homelab
Preparar as fixtures e o contrato do eventual SDC como documentação, sem criar componentes prematuros; cruzar árvore de templates/presenters com rotas confirmadas por código. A integração funcional B1-02 aguarda consumidores reais e inspeção de DOM.

## Decisão posterior que prevalece — H1 sob responsabilidade do Context Header

A diretriz anterior de preservar preferencialmente o H1 renderizado pelo Core foi **revogada**. A decisão vigente é permitir ao SDC contextual emitir o H1 principal, usando título fornecido pelo Drupal via provider e desativando **somente naquele consumer** a renderização duplicada de page title. O Core mantém semântica e origem dos dados, mas sua **apresentação nativa não é obrigatória**. Ver [contrato M2/B1](theme-ux-0.8-m2-b1-context-header.md) e o plano de execução B1-02. Não usar `display:none` como estratégia de deduplicação, não mexer no shell global.
