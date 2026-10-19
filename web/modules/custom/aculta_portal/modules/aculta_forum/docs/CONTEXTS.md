# Contextos de discussão e contratos

Este documento define o contrato funcional para Fórum público, Publicações,
LMS e a futura Wiki. É especificação; os providers ainda não existem nesta base.

## Interface do contexto

Um provider, resolvido por um serviço central (conceitualmente
`DiscussionContextResolver`), oferece:

- entidade hospedeira e referência imutável;
- identificador estável e tipo do contexto;
- acesso de leitura/escrita/moderação como `AccessResultInterface` cacheável;
- política de publicação/moderação e limites de participação;
- URL canônica e âncora/permalink;
- política de listagem, busca, sitemap, feeds e notificação;
- metadados de cache para entidade, usuário, permissões, Group e estado editorial.

Providers não aceitam decisão de acesso apenas por slug/payload do navegador. A
entidade é carregada pelo Entity API e o acesso é revalidado na operação.

## Modelo inicial a validar

| Nome proposto | Uso | Estado/validação |
| --- | --- | --- |
| `aculta_forum_topic` | Node para tópico independente público | Nome livre no inventário do código/config desta base; confirmar comprimento e colisão no gate antes de configurar. |
| `aculta_lms_topic` | Node para discussão pedagógica reservada | Idem; adaptador opcional separado evita dependência circular Forum↔LMS. |
| `aculta_discussion_post` | bundle Comment compartilhado, se a configuração do Core permitir | Proposta ainda não provada. Confirmar Comment field storage, bundles hospedeiros, permissões e render/access em testes Kernel. |
| `aculta_forum_categories` | vocabulário Taxonomy de categorias públicas | Proposta; substituir vocabulary `forums` somente na fase de migração autorizada. |
| `field_aculta_discussion` | campo Comment em Nodes hospedeiros suportados | Entity type Node pode compartilhar o field storage entre bundles; Wiki, Magazine, tópico público e tópico LMS são Nodes no plano atual. Verificar field config e possibilidade de ocultar/desativar em bundles específicos. |

Todos os machine names ficam abaixo do limite local de 32 caracteres, mas isso
deve ser validado automaticamente antes de registrar configuração. Esta tabela
não concede autorização para criar configuração ou instalar entidades nesta fase.

## Providers

### `public_forum`

- Hospedeiro: Node `aculta_forum_topic`.
- Público: tópico independente classificado em categoria pública; sem Group membership.
- Leitura: pública quando publicado e permitido pela política de Domain/purpose.
- Escrita: regras atuais de participação e confirmação de e-mail; moderação conforme política vigente.
- Pesquisa e aviso: elegível para descoberta pública apenas se publicado e acessível.

### `publication`

- Hospedeiro: Node da publicação, com `field_aculta_discussion`.
- Um único fluxo de comentários na própria página; nunca criar tópico em outro Node.
- Publicação/despublicação e permissões editoriais condicionam leitura/escrita.
- Canonical do comentário preserva o caminho editorial e permalink/âncora.
- Search, aviso e métricas seguem a política editorial e access da publicação.

### `lms`

- Hospedeiro: Node `aculta_lms_topic` com vínculo ao Group/curso e referências contextuais aprovadas para turma/atividade.
- Provider só é incluído pelo adapter opcional `aculta_forum_lms`; não criar dependência obrigatória do submódulo principal para `aculta_lms`.
- Ler/escrever exige autorização do recurso e membership atual para o grupo correto; papel global `estudante` sozinho não autoriza.
- Nenhuma rota, View, busca, cache, perfil público, sitemap, feed ou notificação pública divulga o conteúdo.
- Atividade reutilizada por cursos distintos não compartilha conversa sem decisão específica.

### `wiki`

- Contrato futuro para Node do bundle Wiki com uma discussão por verbete.
- Renderizado dentro do verbete; comentários não alteram revisão editorial nem criam tópico público.
- Usa `author_wiki`; criação do provider aguarda a reimplementação da Wiki.

## Reutilização do Comment

Comment é conteúdo associado a uma entidade e ao campo que habilita comentários.
O Core documenta suporte a campos Comment em diferentes tipos de entidade; não
significa que um mesmo storage possa cruzar entity types sem configuração própria.
O modelo inicial limita a hospedagem a Node para favorecer field storage
compartilhável; a prova de compatibilidade real fica para a fase executável.
Se o Core/configuration exigir bundles Comment ou fields distintos, a camada
única de contexto e apresentação permanece. O formato nativo de encadeamento,
aprovação, publicação, autor e acesso deve ser aproveitado.

Referências: [configuração de comentários em entidade](https://www.drupal.org/docs/8/core/modules/comment/administering-a-content-types-comment-settings), [interface Comment](https://api.drupal.org/api/drupal/core%21modules%21comment%21src%21CommentInterface.php/interface/CommentInterface/11.x) e [cacheability de render arrays](https://www.drupal.org/docs/drupal-apis/render-api/cacheability-of-render-arrays).
