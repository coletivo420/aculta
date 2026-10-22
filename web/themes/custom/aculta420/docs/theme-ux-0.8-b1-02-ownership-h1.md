# THEME-UX 0.8 — B1-02: Consumidores reais e ownership do H1

**Data:** 2026-10-22 · **Estado:** planejamento executor; Runtime e implementação pendentes.
**Decisão:** o `Context Header` pode ser dono do **único H1 da página**; não preservar apresentação nativa do H1 por obrigação. Drupal conserva a fonte de dados do título. Manter **Institution Bar + Domain Header** em todos os purposes.

## B1-02.1 — Matriz de integração obrigatória
| Consumer candidato | Inspecionar | Decisão a registrar |
| --- | --- | --- |
| GanjaLab420 / aula ou curso real | rota, controller/page builder, region page_title, dados LMS/Group, cache, shell | o provider entrega título ao SDC; remove o bloco de page title redundante somente na rota adotante |
| Wiki ou Revista real | route, entity view builder, Twig, page title, breadcrumb, cache/access, shell | mesmo contrato de H1; sem condicionais por hostname dentro do tema |

Não afirmar que qualquer consumer foi verificado sem renderização Runtime, controller e DOM examinados.

## B1-02.2 — Contrato proposto
- `heading_mode`: `primary`/ `none`; `primary` autoriza o `<h1>` nativo do SDC; `none` deixa a região contextual sem título.
- `title`: texto renderizável preparado pelo presenter, **ou** `page_title` slot que contém apenas conteúdo *inline* do título, **nunca H1 completo**.
- `eyebrow`, `subtitle`: textos semânticos opcionais.
- `breadcrumbs`, `back_link`, `actions`: slots renderizáveis autorizados, com ACL/cache geridos nos providers.
- Não criar URLs, buscar entidades, avaliar Group, domain ou autenticação no Twig. Não codificar breadcrumbs em segunda implementação.
- Preferir schema Core SDC, UI Patterns Library stories e UI Styles semânticos; usar render array para preservar attachments e cache.

## B1-02.3 — Estratégia de migração do H1
1. Identificar título nativo existente e ponto de renderização real por consumer.
2. Capturar conteúdo do título existente em render array/cache seguro pelo provider; não raspar markup nem converter arbitrariamente render arrays em strings.
3. Não renderizar a região title original **somente na rota migrada**, preservando outro page title em rotas não adotantes.
4. Renderizar SDC dentro de `main`, sob o shell multidomínio; o H1 será emitido pelo SDC, com slot contendo apenas conteúdo inline acessível ou `title` plain text escapado.
5. Verificar um H1 no HTML final, título da aba/metatags corretos, headings de conteúdo coerentes e nenhum efeito em AJAX.
6. Sem JS deve manter navegação e renderização completa.

## B1-02.4 — Critérios de aceitação
| Gate | Status atual |
| --- | --- |
| 2 consumers reais em purposes distintos | PENDENTE |
| render arrays + cache/ACL preservados | PENDENTE |
| Core SDC schema + stories + UI Patterns/Styles | PENDENTE |
| um H1 canônico por página migrada, sem CSS para esconder duplicata | PENDENTE |
| shell global presente + mobile/dark/light/A11y | PENDENTE |
| acessibilidade e funcionalidade sem JS | PENDENTE |

## Backlog em microcommits
- **B1-02a:** evidências de duas rotas reais, template e renderização do título.
- **B1-02b:** primeira implementação mínima do SDC com stories, somente depois de B1-02a.
- **B1-02c:** adaptação de cada provider/consumer separadamente; evitar mexer em módulos concorrentes.
- **B1-02d:** revisão e evidências Runtime, UI Patterns Library, dark/light e WCAG.
- **B1-02e:** atualizar catálogo do tema e documentação dos consumidores, marcar estados reais.

**Próximo passo fora de Runtime:** inspecionar arquivos controller/view builder/temas de páginas candidatas no GitHub para localizar pontos de integração, sem adivinhar route names e sem implementar SDC ocioso.
