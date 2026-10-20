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
| Fórum público Growroom420 | Seção própria em `/painel-administrativo/forum`, com tópicos e três filas de comentários. O menu lateral também liga à gestão de categorias, configuração do bundle de tópico, denúncias e regra compartilhada de participação. | Views mantêm filtros por bundle/campo e estados publicado/rejeitado/pendente. Rotas antigas sob Conteúdo retornam 404 no Runtime de teste. Links Core/User aplicam as permissões dos seus próprios handlers. HTTP anônimo 403 e menu/tarefas verificados; renderização autenticada no navegador ainda pendente. Revista, LMS e Wiki não aparecem na seção. |
| Publicações | Conteúdo e comentários editoriais são reunidos em `/painel-administrativo/conteudo/revista`; nunca aparecem na listagem do Fórum. | Provider Magazine usa Comment compartilhado. View editorial lista `article`/`blog` e mostra seus comentários na mesma página administrativa. |
| LMS | Espaço administrativo próprio em `/painel-administrativo/lms/forum`, com discussões de curso, turma e atividade separadas e autorização contextual de Educador. | Contrato para o adapter LMS; não criar listagem global até haver filtro por Group/Entity Access demonstrado. Comentários de avaliação (`lms_answer_comment`) são um recurso distinto e não viram comentários do Fórum. |
| Wiki420 | Espaço administrativo próprio em `/painel-administrativo/wiki/forum`, limitado a comentários de discussão de verbetes; edição/revisão editorial permanece separada. | Contrato futuro, condicionado ao provider e ao bundle Wiki. Não criar agora rota/listagem sem contexto instalável e access validado. |

### Inventário administrativo do Growroom420

No estado atual, as ferramentas existentes são a View de tópicos públicos, as
três filas de Comments (`publicados`, `rejeitados` e `aguardando moderação`), e
as ações de mover tópico entre categorias e comentário/subárvore entre tópicos.
Categorias Taxonomy e configuração do bundle Node usam interfaces nativas de
administração Core e ainda não têm links centralizados na seção do Fórum.
Planejar o novo painel como índice que reúne essas ferramentas e respeita as
permissões atuais (`access content overview`, `administer comments`, `move
aculta forum topics` e `move aculta forum comments`); não criar outro CRUD.

Não há formulário próprio de configuração do Fórum hoje. A moderação do
primeiro comentário é uma configuração compartilhada do `aculta_user`, em
`/painel-administrativo/configuracoes/aculta/portal/participacao`, com efeito
em Fórum, Revista, Wiki e outros hosts, exceto LMS. O painel Growroom poderá
apresentar um link para essa opção com seu escopo compartilhado explícito, sem
duplicar o armazenamento nem alterar a política dos outros contextos.

Na dev.105, o adapter `aculta_forum_lms` foi habilitado no Runtime de teste com
`gnode`, bundle `aculta_lms_topic`, campo compartilhado e relações Group para
curso/turma. O smoke da raiz pública e de `/categorias` retornou HTTP 200 e as
contagens de tópicos públicos, LMS, legados e Comments compartilhados ficaram
em zero. A Config Sync do checkout não foi importada/exportada e ainda diverge
do Runtime; a configuração de permissões/roles não foi aplicada. Logo, essa
ativação valida descoberta/instalação de serviços e configuração, não o acesso
real de Educadores no site. Views, Search API, rotas e filas LMS continuam
pendentes. RUNTIME STATUS: PARTIAL.

Na dev.106, `core.extension` passou a rastrear `aculta_forum_lms` e `gnode`.
Uma importação parcial revisada atualizou somente quatro Group roles e
`lms_classes.settings`: estudante/educador/criador têm leitura de tópicos na
relação apropriada; `grade students` do curso é herdado na turma. Os outros
campos dos cinco objetos já coincidiam com a Config Sync; nenhum outro item foi
alterado. Kernel cobre membro, outsider e Educador sem membership direto na
turma. O Runtime continua sem conteúdo para uma prova de acesso HTTP a tópico;
moderação, Views e filas LMS permanecem sem interface.

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
- Na dev.96, membership e permissão de Educador são combinados como caminhos alternativos explícitos; `orIf()` não é usado porque uma negação de membership prevalece sobre uma permissão permitida. O acesso é negado somente quando ambos os caminhos falham. A moderação é explicitamente forbidden para não Educadores; escrita também é forbidden quando o Comment field não está aberto ou falta `post comments`. Unit cobre estudante, Educador, status aberto e fechado. A prova de membership em turma e Group roles reais continua pendente.
- Na dev.97, `lms_classes.settings` herda `grade students` do curso para a permission Group Node de tópico dentro da turma. Kernel usa `ClassPermissionCalculator` real e comprova Educador com membership no curso, outsider e usuário com apenas papel global `lms_teacher`; somente o Educador do curso recebe a permissão. Config Sync ainda não foi importado no Runtime.
- Na dev.98, Kernel de LMS cria Node de tópico na turma, Comment publicado e o marcador compartilhado `field_aculta_rejected`. Estudante membro escreve; somente Educador do curso modera; outsider não lê; fechar o campo bloqueia nova escrita. A prova ainda instancia o provider/hook diretamente e não habilita o adapter; filas e import Runtime seguem pendentes.
- Na dev.99, Config Sync dá permissão de leitura Group Node aos roles de Educador/criador/estudante no curso e estudante na turma. Os configs dependem das relationship types instaladas pelo adapter; a herança `grade students` segue aplicando acesso do Educador do curso à turma. Kernel monta os roles a partir dos próprios YAMLs versionados. Não importar antes de instalar o adapter.
- Na dev.100, Kernel confirma cache contexts por usuário/permissões e dependência do Node no `DiscussionContext` LMS para estudante, Educador e outsider. O teste exerce a composição real de `AccessResult` do Group/Core, sem alterar ou contornar os resultados de acesso. O adapter continua desabilitado até a revisão completa de cache/acesso e das filas.
- Na dev.101, Unit verifica as dependências dos quatro Group roles do Config Sync contra as relationship types do adapter e a herança de `grade students`. Instalar só uma parte do grafo de módulos não seria uma prova válida da ativação; a instalação completa ainda precisa de uma fixture Kernel com todas as dependências reais.
- Na dev.102, Kernel aplica a configuração padrão do adapter via `ConfigInstaller` com pré-requisitos reais de LMS, Group, Comment e Node; bundle, campo compartilhado, duas relationships e formatter são carregados. Isso não habilita `aculta_forum_lms` nem executa `ModuleInstaller`; o ciclo de ativação e desinstalação continua pendente.
- Na dev.104, a fixture Kernel não usa propriedade como atalho: curso e turma pertencem a uma terceira conta; o Educador é role contextual do curso, sem membership direto na turma. `class->access('view')` e `topic->access('view')` passam por permissões LMS/Group herdadas, e somente esse Educador pode moderar. O outsider com papel global `lms_teacher` continua negado. Adapter e Config Sync continuam desativados no Runtime.
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
