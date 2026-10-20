# Contextos de discussão e contratos

Este documento define o contrato funcional para Fórum público, Publicações,
LMS e a futura Wiki. A dev.4 implementa a interface comum, o resolver e o
provider do Fórum público. Providers de Publicações, LMS e Wiki continuam por
suas fases de integração; nenhuma entidade é tratada como contexto só por slug,
nome de bundle enviado pelo cliente ou presença de dados arbitrários.

**Inicialização limpa:** por F-D33, Nodes `forum`, Comments `comment_forum`,
termos do vocabulário `forums` e vínculos antigos `field_discussao` foram
removidos do Runtime de teste em 2026-10-20; nenhum tópico ou comentário antigo
será importado. A extensão contrib ainda aguarda retirada oficial depois da
auditoria de consumidores/configuração. As 12 categorias do vocabulário novo
`aculta_forum_categories` permanecem. Conteúdo editorial hospedeiro e seus
comentários diretos foram preservados.

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

## Separação estrutural no painel de conteúdo

O painel `/painel-administrativo/conteudo` não deve misturar objetos de discussão
com os demais conteúdos. Cada contexto possui uma listagem/moderação própria,
com filtros fixos no lado do servidor, permissões adequadas e acesso/cache da
entidade hospedeira preservados. Listagens genéricas de Nodes ou Comments não
são a interface administrativa dos contextos de discussão.

| Contexto | Estrutura administrativa | Estado |
| --- | --- | --- |
| Fórum público Growroom420 | `/painel-administrativo/conteudo/forum` apresenta tópicos e as três abas de comentários publicados, rejeitados e aguardando moderação. A entrada geral `/painel-administrativo/conteudo/forum/comentarios` permanece para consulta agregada. Tópicos e comentários ficam fora das listas genéricas. | Views filtram pelo bundle do tópico e campo de discussão. O campo `field_aculta_rejected` distingue rejeição definitiva de pendência (`status=0` sem rejeição) e só é editável por quem tem `administer comments`; fica oculto no display público. A camada de tarefas locais do Core suporta dois níveis, então os três estados aparecem diretamente sob Fórum. Revista, LMS e Wiki não entram nestas filas. |
| Publicações | Conteúdo e comentários editoriais são reunidos em `/painel-administrativo/conteudo/revista`; nunca aparecem na listagem do Fórum. | Provider Magazine usa Comment compartilhado. View editorial lista `article`/`blog` e mostra seus comentários na mesma página administrativa. |
| LMS | Espaço administrativo próprio em `/painel-administrativo/lms/forum`, com discussões de curso, turma e atividade separadas e autorização contextual de Educador. | Contrato para o adapter LMS; não criar listagem global até haver filtro por Group/Entity Access demonstrado. Comentários de avaliação (`lms_answer_comment`) são um recurso distinto e não viram comentários do Fórum. |
| Wiki420 | Espaço administrativo próprio em `/painel-administrativo/wiki/forum`, limitado a comentários de discussão de verbetes; edição/revisão editorial permanece separada. | Contrato futuro, condicionado ao provider e ao bundle Wiki. Não criar agora rota/listagem sem contexto instalável e access validado. |

Moderação segue a árvore do sistema dono do conteúdo, sem fila global que
misture contextos: Fórum → estado do comentário; Revista → publicações e
comentários editoriais; Wiki → Comentários → Moderação; LMS → Cursos → Fórum →
Comentários, sob responsabilidade contextual dos Educadores. Nesta entrega, as
três abas de estado do Fórum estão ativas; Revista já tem área própria; Wiki/LMS aguardam seus
adapters e não recebem rotas antecipadas. Rotas futuras devem nascer com os
adapters e ser registradas no mapa global. A fila genérica do Core exclui
Comments hospedados por `field_aculta_discussion`; novas integrações devem
manter seus filtros, Entity Access e, no LMS, membership/Group atuais. Nunca
usar apenas `comment_type` para autorizar ou listar conteúdo contextual.

## Modelo inicial a validar

| Nome proposto | Uso | Estado/validação |
| --- | --- | --- |
| `aculta_forum_topic` | Node para tópico independente público | Bundle/Comment/Taxonomy e campo `field_domain_source` em `config/install` e Config Sync; formulário title/body/categoria e displays full/teaser aplicados no Runtime de teste. Atribuição de origem usa o domínio canônico do purpose `forum`; criação falha fechada fora desse purpose ou se ele não existir. Nenhum tópico criado. Permissões de criação e gestão do próprio tópico declaradas na dev.6. `content_editor` modera tópicos de outros autores pelo access hook do `aculta_user`, que exige confirmação real de e-mail. |
| `aculta_lms_topic` | Node para discussão pedagógica reservada | Planejado para adapter LMS; dependência opcional evita ciclo Forum↔LMS. |
| `aculta_discussion_post` | bundle Comment compartilhado | Configuração de Comment em Node preparada na dev.4; suporte reutilizável em outros bundles Node ainda requer prova Kernel na dev.7/integrações. |
| `aculta_forum_categories` | vocabulário Taxonomy de categorias públicas | 12 categorias novas no Runtime de teste; as 8 categorias antigas `forums` foram apagadas conforme F-D33. |
| `field_aculta_discussion` | campo Comment em Nodes hospedeiros suportados | Configuração do tópico público e fields de `article`/`blog` estão em Config Sync; as duas instâncias editoriais e os displays full foram aplicados no Runtime de teste. Teste Kernel em dev.5 provou o storage compartilhado nos três bundles. |
| `field_autor_legado` | referência a `aculta_legado_autor` em autoria histórica validada | Storage pertence ao `aculta_user`; instâncias previstas em bundles ACULTA só entram com futura importação explicitamente aprovada. O Runtime atual não contém conteúdo legado. |

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

### CTools nas listagens do Fórum

Views permanece responsável pela consulta, filtros fixos, acesso e cache. Para blocos de Views do Fórum com ajustes editoriais por posicionamento, usar `ctools_views` para quantidade e pager; não expor controles de filtro, campo ou ordenação e não usar CTools como substituto do Entity Access. A listagem e os filtros do novo Fórum ainda serão implementados na fase dev.6; esta decisão segue `docs/CTOOLS.md`.

### `publication` — provider adicionado na dev.5

- Hospedeiro: Node da publicação, com `field_aculta_discussion`.
- Um único fluxo de comentários na própria página; nunca criar tópico em outro Node.
- O provider atende apenas bundles `article` e `blog` que tenham o campo compartilhado; as duas field instances e os displays full estão aplicados no Runtime de teste, sem comentários criados.
- Leitura combina Node Entity Access com field access. Escrita requer leitura permitida, campo aberto e permissão `post comments`; moderação combina leitura e `administer comments`. As decisões variam por `user.permissions` e carregam cacheability do host/campo.
- `aculta_user` aplica moderação do primeiro comentário compartilhado a qualquer Node com `field_aculta_discussion`, incluindo Fórum, Revista, Wiki e novos contextos. O host `aculta_lms_topic` não é moderado, mas um comentário LMS publicado conta como participação aprovada nos demais contextos e retira o bloqueio temporário de criação de tópico. A opção fica em `/painel-administrativo/configuracoes/aculta/portal/participacao`. Os bundles contrib `forum`/`comment_forum` estão vazios no Runtime de teste após F-D33; criação legado permanece bloqueada até o uninstall. O toggle antigo de moderação do Forum contrib é obsoleto e ignorado.
- O provider editorial e a política de moderação estão cobertos por testes Unit/Kernel isolados. `aculta_magazine` aplica em memória o status default configurado a Nodes anteriores sem status persistido; status explícitos e estatísticas são preservados. Runtime editorial PARTIAL: campo/display presente e status padrão verificado; renderização HTTP e envio permanecem pendentes.
- Publicação/despublicação e permissões editoriais condicionam leitura/escrita.
- Canonical do comentário preserva o caminho editorial e permalink/âncora.
- Search, aviso e métricas seguem a política editorial e access da publicação.

### `lms`

- Hospedeiro: Node `aculta_lms_topic` com vínculo ao Group/curso e referências contextuais aprovadas para turma/atividade.
- Provider só é incluído pelo adapter opcional `aculta_forum_lms`; não criar dependência obrigatória do submódulo principal para `aculta_lms`.
- Ler/escrever exige autorização do recurso e membership atual para o grupo correto; papel global `estudante` sozinho não autoriza.
- Nenhuma rota, View, busca, cache, perfil público, sitemap, feed ou notificação pública divulga o conteúdo.
- Atividade reutilizada por cursos distintos não compartilha conversa sem decisão específica.
- Na dev.93, um hook `entity_access` impede que Nodes `aculta_lms_topic` sem vínculo Group único herdem a visualização padrão de conteúdo publicado; uma turma também precisa de uma única relação com curso. Com a relação válida, a decisão de papéis continua com o access handler do Group.
- Na dev.94, o adapter adiciona configuração de instalação do bundle `aculta_lms_topic`, instância do campo Comment compartilhado e relações `group_node:aculta_lms_topic` limitadas a `lms_course` e `lms_class`. As permissões Group para estudantes/Educadores e as configurações raiz de ativação não foram importadas; o módulo permanece desabilitado.
- Na dev.95, Kernel com Group 3.3.5/gnode comprova acesso de um membro de curso que tem a permission do relacionamento e nega outsider. Isso prova a camada Group Node; a permissão efetiva de Educador nas classes, derivada de `grade students`, ainda precisa de configuração e Kernel próprios.
- A fundação opcional `aculta_forum_lms` foi adicionada na dev.92: provider tagueado reconhece `aculta_lms_topic`, exige vínculo Group único com `lms_course`/`lms_class`, aplica Entity Access e membership contextual, e limita moderação a `grade students` no curso pai. A integração permanece fechada até bundle, campo Comment e relationship types específicos estarem instalados e testados.
- Dev.92 não cria formulário de tópicos, Views administrativas, campos de atividade/aula, Search API privada ou notificações. A delegação de `field_aculta_rejected` está implementada; a prova Kernel e a matriz completa de acesso são obrigatórias antes de habilitar o adapter.

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

Dev.4 fornece a configuração inicial para Node. A prova Kernel de dev.5 cobre a
criação de um único Comment field storage em múltiplos bundles Node, e o provider
editorial cobre as decisões de access/cache; isso ainda não homologa o formulário,
a renderização, a integração de cartões ou o comportamento no Runtime. Não
executar importação de configuração do site sem inspecionar seus efeitos e
confirmar a recuperação.

Referências: [configuração de comentários em entidade](https://www.drupal.org/docs/8/core/modules/comment/administering-a-content-types-comment-settings), [interface Comment](https://api.drupal.org/api/drupal/core%21modules%21comment%21src%21CommentInterface.php/interface/CommentInterface/11.x) e [cacheability de render arrays](https://www.drupal.org/docs/drupal-apis/render-api/cacheability-of-render-arrays).


## Responsabilidade de moderação no LMS

Comentários LMS não entram na regra global de aprovação do primeiro comentário. A moderação pertence aos Educadores
que têm autorização efetiva no contexto pedagógico correspondente. O adapter deve derivar permissão de acesso do curso,
turma ou atividade (Group/membership e Entity Access atuais), sem concedê-la a `content_editor` por papel global ou
a qualquer conta `lms_teacher` sem acesso àquele contexto. Enquanto o adapter de moderação LMS não estiver implementado,
a interface do motor deve falhar fechada para ações de moderação e não apresentar uma fila global de LMS.
