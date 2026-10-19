# Contextos de discussão e contratos

Este documento define o contrato funcional para Fórum público, Publicações,
LMS e a futura Wiki. A dev.4 implementa a interface comum, o resolver e o
provider do Fórum público. Providers de Publicações, LMS e Wiki continuam por
suas fases de integração; nenhuma entidade é tratada como contexto só por slug,
nome de bundle enviado pelo cliente ou presença de dados arbitrários.

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
| `aculta_forum_topic` | Node para tópico independente público | Configuração de instalação adicionada na dev.4; <=32 caracteres. Não habilitada no Runtime. |
| `aculta_lms_topic` | Node para discussão pedagógica reservada | Planejado para adapter LMS; dependência opcional evita ciclo Forum↔LMS. |
| `aculta_discussion_post` | bundle Comment compartilhado | Configuração de Comment em Node preparada na dev.4; suporte reutilizável em outros bundles Node ainda requer prova Kernel na dev.7/integrações. |
| `aculta_forum_categories` | vocabulário Taxonomy de categorias públicas | Configuração de instalação adicionada na dev.4; a substituição do vocabulário `forums` fica para migração autorizada. |
| `field_aculta_discussion` | campo Comment em Nodes hospedeiros suportados | Configuração instalada inicialmente apenas no tópico público. Reuso em publicação/Wiki/LMS depende de prova Kernel e configuração explícita por bundle. |

Todos os machine names ficam abaixo do limite local de 32 caracteres, mas isso
deve ser validado automaticamente antes de registrar configuração. Esta tabela
não concede autorização para criar configuração ou instalar entidades nesta fase.

## Providers

### `public_forum` — provider implementado na dev.4

- Hospedeiro: Node `aculta_forum_topic`.
- Público: tópico independente classificado em categoria pública; sem Group membership.
- Leitura: pública quando publicado e permitido pela política de Domain/purpose; o provider implementado nega fora do purpose `forum` e varia o access result pelo cache context `domain`.
- Escrita: regras atuais de participação e confirmação de e-mail; moderação conforme política vigente.
- Pesquisa e aviso: elegível para descoberta pública apenas se publicado e acessível.
- Identificador estável: `public_forum:node:{uuid}`. O UUID é obtido da entidade; não do request.
- Nesta fase, acesso de escrita e moderação retorna neutral com dependência de cache da entidade. Ações de Comment ainda exigem a verificação nativa de campo/entidade e serão integradas depois.

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

Dev.4 fornece a configuração inicial para Node e não afirma compatibilidade de
acesso ou renderização entre todos os contextos. Não habilitar o módulo nem
importar essa configuração no Runtime sem a etapa de instalação/teste aprovada.

Referências: [configuração de comentários em entidade](https://www.drupal.org/docs/8/core/modules/comment/administering-a-content-types-comment-settings), [interface Comment](https://api.drupal.org/api/drupal/core%21modules%21comment%21src%21CommentInterface.php/interface/CommentInterface/11.x) e [cacheability de render arrays](https://www.drupal.org/docs/drupal-apis/render-api/cacheability-of-render-arrays).
