# Arquitetura do ACULTA420

`aculta420` é a camada visual Drupal do ACULTA. O Portal entrega dados e
render arrays neutros; o tema apresenta com SDC, Bootstrap e tokens semânticos.

## Decisões vigentes

- **2026-10-18 — Catálogo de stories via UI Patterns Library.** Situação:
  vigente. Motivo: as demonstrações usam SDCs e dados fictícios no próprio
  ambiente administrativo, sem manter aplicação Storybook independente.
  Origem: `0.6.0-dev.82`,
  [histórico arquivado](../history/changelog/aculta420.md). O catálogo atual e
  seu acesso administrativo estão descritos em
  [component-catalog.md](../../web/themes/custom/aculta420/docs/component-catalog.md).
- **2026-10-09 — Ícones pelo pacote Bootstrap Icons já integrado ao tema.**
  Situação: vigente. Motivo: reaproveitar a biblioteca compartilhada e evitar
  SVG inline/copiar outra implementação. Origem: `0.5.1`,
  [histórico arquivado](../history/changelog/aculta420.md). A biblioteca
  `aculta420/bootstrap-icons` é dependência global do tema e o gate de
  design-foundations reprova SVG inline.
- **2026-10-14 — Um modelo visual para publicações da Revista.** Situação:
  vigente. Motivo: artigos e blogs compartilham estrutura editorial e coluna de
  leitura consistentes; o conteúdo continua no Drupal. Origem: `0.6.0-dev.21`,
  [histórico arquivado](../history/changelog/aculta420.md). O componente
  `aculta420:revista-publication` é usado pelo template de publicação descrito
  em [components.md](../../web/themes/custom/aculta420/docs/components.md).
- **2026-10-13 — Marca do domínio segue contrato neutro do Portal.** Situação:
  vigente. Origem: `0.6.0-dev.17`, [histórico arquivado](../history/changelog/aculta420.md);
  decisão transversal em [ADR-028](../decisions/ADR-028-marca-neutra-portal-tema.md).

## Fronteiras

- `aculta_portal` decide acesso, contexto, cache e significado dos dados.
- ACULTA420 decide apresentação e composição; não consulta dados de domínio nem
  repete regras de autorização.
- Tokens semânticos pertencem ao tema; consumidores não criam estilos visuais
  paralelos para um componente existente.
