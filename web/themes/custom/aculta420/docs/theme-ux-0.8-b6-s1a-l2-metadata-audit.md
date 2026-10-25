# THEME-UX 0.8 — B6-S1a-L2: fechamento do inventário de metadados

**Data:** 2026-10-22 · **PR:** #297 · **Branch:** `docs/aculta-forum-0.1.0-dev.1` · **Estado:** leitura de metadados concluída; inspeção individual de Twig/CSS/stories e homologação ainda pendentes.

## Evidência e abrangência

Na árvore da branch com HEAD inicial `b009262ccfb5150b437ba5c1c822f34f67ad8c51` foram encontrados **41 metadados SDC**, **41 templates Twig**, **32 arquivos CSS** e **65 stories** dentro de `components/`. Os 13 metadados do lote anterior [B6-S1a](theme-ux-0.8-b6-s1a-contract-findings.md) somam-se à **leitura dos 28 arquivos de metadados restantes neste lote**, completando a cobertura de leitura dos 41 arquivos YAML. A existência dos 41 Twig e dos stories foi verificada por caminhos; **não** confundir isso com leitura completa de seus conteúdos ou conformidade Core.

## Componentes revisados nesta etapa (metadados YAML)

| Família | SDCs inspecionados | Achados e atenção |
| --- | --- | --- |
| Identidade e conteúdo | `author-card`, `course_card`, `editorial-card`, `forum-category`, `institutional-notice` | `author-card` enumera variantes do `aculta_user`; `course_card` declara `replaces: lms:course_card`; confirmar contrato upstream e cache. |
| Messenger | `messenger-compose`, `messenger-item`, `messenger-notification` | Slots preservam Form API e entidades renderizadas; confirmar ACL em providers e regras de live-region no Runtime. |
| Commerce | `product-buy`, `product-details` | Formulário de compra vem como slot; não processar preço, variações nem ações em Twig. |
| Doações | `donation-card`, `donation-dedication`, `donation-notification`, `donation-pane`, `donation-quick`, `donation-summary-container` | **Quatro SDCs declaram schema legado** em vez do Core (detalhe abaixo). |
| Feedback | `consent-banner`, `unread-badge` | Consentimento depende do módulo Statistics e JS; contador deve vir autorizado do Portal. |
| Padrões | `carousel`, `content-grid`, `figure`, `hero`, `messenger-list`, `messenger-read-status`, `quote`, `rail`, `revista-publication`, `social-links-page` | Contratos para slots e props localizados; preservar mecanismo VVJB, semântica editorial e fontes de dados. |

## Finding B6-S1a-04 — schema SDC legado nos formulários de doação

**Severidade:** P1 para saneamento de contratos; **não implica falha em execução sem teste**.

Os arquivos abaixo ainda usam `$schema: https://git.drupalcode.org/project/sdc/-/raw/1.x/src/metadata.schema.json`, e contêm comentários boilerplate sobre o formato antigo:

- `components/donation-dedication/donation-dedication.component.yml`
- `components/donation-notification/donation-notification.component.yml`
- `components/donation-pane/donation-pane.component.yml`
- `components/donation-summary-container/donation-summary-container.component.yml`

**Proposta B6-S2:** atualizar metadados para o schema Core compatível com a versão instalada, remover comentários de template gerado sem valor e validar `libraryOverrides`, props e slots existentes antes de alterar contratos. Não reescrever Twig/formulários da doação nesta migração. `donation-card` e `donation-quick` já declaram o schema Core.

## Observações sobre a compatibilidade SDC/UI Suite

- Os outros 24 metadados lidos nesta etapa declaram o caminho de schema Core. Isso **não é equivalente** a aprovação de YAML/schema, props ou stories em Drupal.
- `course_card` usa `replaces` e array de `extra_fields` com item `content: object`; verificar compatibilidade com o componente original do LMS e o renderer antes de qualquer refatoração.
- `author-card` é apresentação sob responsabilidade funcional de `aculta_user`; **não** duplicar regras de perfil no tema.
- `revista-publication`, `figure`, `quote` e `editorial-card` permanecem SDCs distintos, a consolidar somente após evidência dos consumidores.
- O finding anterior B6-S1a-01 (`cart-offcanvas.sites` array pouco tipado) permanece aberto.

## Próxima execução

1. **B6-S1a-L3:** auditoria do conteúdo dos 41 Twig, 32 CSS e 65 stories, com checks estáticos sistemáticos para semântica, compatibilidade com props/slots, classes de design, tokens e dados de fixtures. Registrar matriz individual; não declarar PASS sem executá-la.
2. **B6-S1b:** rastrear consumidores em código/config, ACL/cache, render arrays e possíveis conflitos UI Patterns/UI Styles.
3. **B6-S2:** corrigir primeiro os quatro schemas legados das doações em commits pequenos, com validações de compatibilidade; depois demais defeitos comprovados.
4. **B6-R:** executar no homelab Core/SDC Devel, biblioteca UI Patterns, UI Styles, navegador, shell multidomínio, mobile/light/dark, H1, AJAX, acessibilidade, access e cache.

**Regra invariável:** Institution Bar + Domain Header permanecem em todos os purposes. Apenas reuso transversal compatível com SDC/UI Suite, nunca Twig legado avulso como componente novo. Nenhum arquivo funcional de outras frentes foi modificado neste lote.
