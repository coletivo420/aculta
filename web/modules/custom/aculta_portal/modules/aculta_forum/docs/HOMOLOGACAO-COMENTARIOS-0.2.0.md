# Homologação Integrada e Matriz de Aceite — Comentários 0.2.0-dev.3

- **Data:** 2026-11-08
- **Versão:** `0.2.0-dev.3`
- **Componentes:** `aculta_forum`, `aculta420_v2`
- **Estado:** Implementação concluída; homologação no Homelab registrada com status `DEFERRED` (ambiente inacessível nesta sessão).

---

## 1. Fechamento dos Achados COM-01 a COM-12

| ID | Descrição e Problema Identificado | Correção Implementada e Validação | Estado |
|---|---|---|---|
| **COM-01** | Core CommentLazyBuilders gerava `comment.reply` e ForumPostThreadBuilder usava `/publicacao/nova`, forçando nova página. | Implementado compositor inline nativo Form API + HTMX com endpoint em português `/discussoes/compositor/{entity_type}/{entity_id}` e `DiscussionReplyFormBuilder`. Resposta abre na mesma página. | **CONCLUÍDO** |
| **COM-02** | `forum-quote.js` cancelava o clique e manipulava `innerText` no DOM sem pid validado no servidor. | Saneado em 0.2.0-dev.2 e integrado a `DiscussionReplyUrlBuilder` com query `resposta={pid}&modo=citar`. Política central no servidor (`ForumQuoteBuilder`). | **CONCLUÍDO** |
| **COM-03** | Seletor global frágil `form#comment-form` gerava conflitos em páginas com múltiplas discussões. | Identificadores contextuais e únicos por container `[data-discussion-reply-composer]` com `#discussion-composer-{entity_type}-{entity_id}-{parent_id}`. | **CONCLUÍDO** |
| **COM-04** | Templates descartavam `attributes` do Drupal Core (`id`, classes de estado e autoria). | Templates encapsulam `<article{{ attributes.addClass(...) }}>` e `<p class="visually-hidden">{{ parent }}</p>`. | **CONCLUÍDO** |
| **COM-05** | Core `CommentForm` definia action `comment.reply` para novo comentário sem pai, quebrando permanência na página. | `DiscussionReplyFormHooks` força `#action` canônica do hospedeiro, define `setIgnoreDestination(TRUE)` e aplica HTMX swap outerHTML sem redirect. | **CONCLUÍDO** |
| **COM-06** | Template imprimia permalink Core e D-65 bloqueia rota standalone canonical `/comment/{cid}` (404). | `DiscussionCommentPermalinkBuilder` aponta ao hospedeiro canônico com idioma, pager 0 e `#comment-{cid}`. | **CONCLUÍDO** |
| **COM-07** | JS copiava `innerText` sem filtros ou limites, divergindo da política do servidor. | Sanitização central e limite estrito de 1200 caracteres via `ForumQuoteBuilder`. | **CONCLUÍDO** |
| **COM-08** | Módulo PHP instanciou diretamente componente SDC do tema (`aculta420_v2:button`). | Desacoplado: PHP emite render array neutro Drupal; o tema aplica a apresentação SDC. | **CONCLUÍDO** |
| **COM-09** | Citação consultava apenas `getReadAccess()`, ignorando discussões fechadas ou contas sem escrita. | Revalidação estrita de `getWriteAccess()` em `DiscussionReplyAccess`, `ForumQuoteHooks` e `DiscussionReplyEndpointAccess`. | **CONCLUÍDO** |
| **COM-10** | CSS e JS usavam literais frágeis de URL (`?quote=`, `/comment/reply`). | Links emitem atributos semânticos `data-action="comment-quote"`, `data-action="comment-reply"`, `data-quote-id` e `data-parent-id`. | **CONCLUÍDO** |
| **COM-11** | Citar era inserido fora do grupo `#links` do Core (`links['aculta_forum_quote']`), quebrando coleta de `preRenderLinks`. | Links de citação e resposta inseridos sob `#links['comment-quote']` e `#links['comment-reply']`, coletados nativamente por `Link::preRenderLinks`. | **CONCLUÍDO** |
| **COM-12** | Formulário salvo retornava por redirect e podia respeitar destination manipulado. | Destination ignorado via `setIgnoreDestination(TRUE)`; swap HTMX in-place sem redirect externo. Fallback sem JS usa PRG na própria página do hospedeiro. | **CONCLUÍDO** |

---

## 2. Matriz de Aceite (Seção 8 do Plano)

| Cenário / Contexto | Requisito | Resultado do Código & Testes | Status Runtime |
|---|---|---|---|
| **Revista** (`node:revista`) | Discussão em `field_aculta_discussion`, respostas inline e citação | PASS (Unit & Kernel) | DEFERRED |
| **Wiki** (`node:wiki_entry`) | Provider centralizado no Fórum, sem dependência reversa | PASS (38/38 Unit tests) | DEFERRED |
| **LMS** (`node:aculta_lms_topic`) | Discussão com isolamento de turma/curso e restrições de escrita | PASS (LmsDiscussionContextIsolationKernelTest) | DEFERRED |
| **Growroom420** (`node:aculta_forum_topic`) | Fórum público, ForumPostThreadBuilder com preservação dos pagers 0 e 1 | PASS (ForumPostThreadHooksKernelTest) | DEFERRED |
| **Idempotência & Concorrência** | Reenvio idêntico recupera estado sem criar duplicatas no banco | PASS (DiscussionReplyIdempotencyManagerTest) | DEFERRED |
| **SDC & Visual** | `discussion-reply-composer` com schema estrito, tokens semânticos e story | PASS (Validação SDC e Twig neutro) | DEFERRED |
| **Acessibilidade & Foco** | Indicação de pai, `role="status"` em mensagens, motion reduzido | PASS (`forum-quote.js` & templates) | DEFERRED |
| **Slugs & Rotas Amigáveis** | Endpoints próprios em português, sem acento, hífens e minúsculas | PASS (`validate-public-slugs.php`: 415 rotas) | DEFERRED |
