# Handoff Agente 1: Saneamento da base de comentários e entrega para Agente 2

- **Data:** 2026-11-08
- **Branch:** `dev`
- **Ciclo:** Comentários Compartilhados ACULTA 0.2.0 (`0.2.0-dev.2`)
- **Status Runtime:** DEFERRED (código e testes em dev; sincronização e cache no Homelab dependem de infraestrutura/ordem operacional).

---

## 1. Escopo executado

O Agente 1 concluiu as microfases **C02-01** e **C02-02** e saneou os defeitos reproduzidos da base atual (COM-02, COM-03, COM-04, COM-06, COM-07, COM-08, COM-09, COM-10, COM-11) sem antecipar a criação do novo compositor inline, entregando uma base limpa, segura e desacoplada para o Agente 2.

### A. Registro central dos providers (C02-01)
- `WikiDiscussionContextProvider` foi movido de `aculta_wiki` para `aculta_forum/src/Context/Provider/WikiDiscussionContextProvider.php` e registrado em `aculta_forum.services.yml`.
- O registro legado em `aculta_wiki.services.yml` foi removido; documentação e README de `aculta_wiki` foram alinhados.
- A classe reconhece genericamente `node:wiki_entry` e `field_aculta_discussion` sem depender de classes da Wiki nem gerar dependência reversa Fórum -> Wiki.
- Foram implementados testes isolados em `DiscussionContextDiscoveryIsolatedTest` e `WikiDiscussionContextProviderTest` provando resolução dos quatro contextos (Revista, Wiki, LMS e Growroom420), falha fechada para nós não suportados, e comportamento isolado com e sem provider Wiki.

### B. Atributos, identidade e permalinks (C02-02)
- Templates `comment--field-aculta-discussion.html.twig` e `comment--comments.html.twig` atualizados para encapsular a marcação com `<article{{ attributes.addClass('comment', 'comment--discussion', 'js-comment', status ? 'comment--' ~ status) }}>`, preservando os atributos do Drupal Core (`id="comment-{id}"`, `data-comment-user-id`, classes de estado publicado/pendente/prévia) e acessibilidade com `<p class="visually-hidden">{{ parent }}</p>`.
- Preservada a autoria resolvida pelo `aculta_user` via `content.author_card` (sem leitura indevida de User no Twig para bypass do builder).
- Criado o serviço `DiscussionCommentPermalinkBuilder` e o hook `DiscussionCommentPermalinkHooks` (`preprocess_comment`), que resolvem permalinks humanos apontando diretamente para o hospedeiro canônico, preservando idioma, pager 0 e âncora `#comment-{cid}`, em conformidade estrita com D-65 (a rota standalone `/comment/{cid}` permanece bloqueada com 404).
- O README do SDC `discussion-comment` foi corrigido para registrar os templates realmente usados.

### C. Saneamento dos achados reproduzidos
- **COM-02 e COM-07 (`forum-quote.js`):** Eliminada a manipulação insegura de citação no cliente (onde o script copiava `innerText` sem filtro, cancelava o evento e colava no editor sem definir pid no servidor). O handler JS agora preserva estritamente o fluxo seguro do servidor caso não haja formulário contextual já construído e validado para aquele pai específico (`data-parent-id`). A política única de sanitização e limite (1200 chars) do `ForumQuoteBuilder` permanece no servidor.
- **COM-03 (Seletores de formulário):** O seletor global `form#comment-form` foi removido. A lógica passa a usar escopo por container de discussão (`[data-aculta-discussion-thread]`, `[data-aculta-discussion-context]`) e IDs únicos.
- **COM-08 e COM-11 (Desacoplamento e contrato `#links`):** `ForumQuoteHooks::commentLinksAlter` deixou de instanciar diretamente o SDC `aculta420_v2:button` no módulo PHP. A ação é gerada como link neutro do Drupal dentro da chave `#links['comment-quote']`, sendo coletada nativamente por `Link::preRenderLinks`.
- **COM-09 (Autorização e cache):** `ForumQuoteHooks` passou a checar obrigatoriamente `$discussion->getWriteAccess()->isAllowed()` além de `getReadAccess()`, impedindo que a ação de citar apareça em discussões fechadas ou para contas com escrita revogada.
- **COM-10 (Seletores semânticos):** O link de citação emite `data-action="comment-quote"`, `data-quote-id` e classes semânticas. O CSS de `forum-post-card` foi atualizado para selecionar por `data-action` e classes do tema, garantindo targets táteis acessíveis (mínimo 44px / 2.75rem).

---

## 2. Status dos achados COM-01 a COM-12

| ID | Status atual | Responsável | Detalhes |
| --- | --- | --- | --- |
| **COM-01** | **Pendente** | Agente 2 | Depende da implementação do compositor inline em C02-03/04/06. |
| **COM-02** | **Corrigido** | Agente 1 | Interceptação sem pid eliminada; fluxo servidor preservado. |
| **COM-03** | **Corrigido** | Agente 1 | Escopo por thread e data-parent-id em vez de ID global. |
| **COM-04** | **Corrigido** | Agente 1 | Templates preservam attributes Core, classes e acessibilidade do pai. |
| **COM-05** | **Pendente** | Agente 2 | Action do Core CommentForm e controle de resposta no compositor. |
| **COM-06** | **Corrigido** | Agente 1 | `DiscussionCommentPermalinkBuilder` aponta ao hospedeiro + pager 0 + âncora. |
| **COM-07** | **Corrigido** | Agente 1 | `ForumQuoteBuilder` como política única; manipulação cliente insegura desativada. |
| **COM-08** | **Corrigido** | Agente 1 | Retirada instanciação de `aculta420_v2:button` de `ForumQuoteHooks`. |
| **COM-09** | **Corrigido** | Agente 1 | Revalidação de escrita (`getWriteAccess`) em links e form alter. |
| **COM-10** | **Corrigido** | Agente 1 | Atributos semânticos `data-action="comment-quote"` e `data-quote-id`. |
| **COM-11** | **Corrigido** | Agente 1 | Ação estruturada sob `#links['comment-quote']` e coletada por `Link::preRenderLinks`. |
| **COM-12** | **Preparado / Pendente** | Agente 2 | Eliminação de redirects do fluxo inline atribuída a C02-03/04/07. |

---

## 3. Contratos, IDs de serviços e extensões

- **Serviço de permalink:** `aculta_forum.discussion_comment_permalink_builder` (`Drupal\aculta_forum\Service\DiscussionCommentPermalinkBuilder`).
- **Hook de permalink:** `Drupal\aculta_forum\Hook\DiscussionCommentPermalinkHooks` (`#[Hook('preprocess_comment')]`).
- **Provider Wiki:** `aculta_forum.discussion_context_provider.wiki` (`Drupal\aculta_forum\Context\Provider\WikiDiscussionContextProvider`), tag `aculta_forum.discussion_context_provider`.
- **Ação de citação no render array:**
  ```php
  $links['comment']['#links']['comment-quote'] = [
    'title' => t('Citar'),
    'url' => $url,
    'attributes' => [
      'class' => ['comment-action', 'comment-action--quote'],
      'data-action' => 'comment-quote',
      'data-quote-id' => (string) $comment->id(),
      'aria-label' => t('Citar comentário #@id', ['@id' => $comment->id()]),
    ],
  ];
  ```

---

## 4. Concorrência e Idempotência (Levantamento para Agente 2)

- O fluxo atual de submissão do Drupal Core `CommentForm` não possui token persistente de idempotência por intenção. Requisições POST repetidas ou concorrentes (duplo clique ou retry por timeout de rede) criam comentários duplicados com novos IDs e threads idênticas.
- Para ForumPost, existe a tabela de posts com revisões e locks gerenciados pelo Fórum, mas no fluxo Comment compartilhado ainda não há barreira de deduplicação antes do save.
- **Recomendação para Agente 2 (C02-07):** Desenhar idempotência baseada em chave de intenção no FormState (`user_id` + `host_entity` + `parent_id` + `hash_conteudo` com janela temporal), utilizando a Lock API ou controle transacional, sem criar tabelas proprietárias antes de validar as capacidades nativas do Core Form API.

---

## 5. Pendências e tarefas para o Agente 2 (C02-03 a C02-08)

1. **C02-03 (Experimento Form API + HTMX):** Construir o compositor com render array e lazy builder, validando POST na mesma página sem recarregamento ou redirect indesejado.
2. **C02-04 (Backend compartilhado Comment):** Controlar action e resposta de validação/erros/prévia para Revista, Wiki e LMS sem vazar `comment.reply`.
3. **C02-05 (SDC e citação unificada):** Integrar o componente visual `discussion-reply-composer`, anexando a citação diretamente no editor instanciado na mesma discussão.
4. **C02-06 (Adapter ForumPost):** Suportar respostas a tópicos e posts do Fórum inline com preservação dos dois pagers e workflow de moderação.
5. **C02-07 (URLs, concorrência e integrações):** Ajustar parâmetros e slugs amigáveis em português, supressão de destination e garantia de idempotência no retry.
6. **C02-08 (Homologação integrada):** Matriz completa com contas autenticadas distintas (não apenas UID 1), claro/escuro, responsivo mobile-first.
