# Contextos de discussão e contratos

Este documento define o contrato funcional para Fórum público, Publicações,
LMS e a futura Wiki. A dev.4 implementa a interface comum, o resolver e o
provider do Fórum público. Providers de Publicações, LMS e Wiki continuam por
suas fases de integração; nenhuma entidade é tratada como contexto só por slug,
nome de bundle enviado pelo cliente ou presença de dados arbitrários.

**Inicialização limpa:** os novos tópicos e comentários começam sem conteúdo
copiado do módulo Drupal Forum contribuído. Nodes e termos desse módulo serão
removidos na janela segura de retirada, após os novos fluxos estarem instalados
e verificados. Separadamente, o acervo histórico do Growroom será restaurado no
novo modelo com autoria histórica e em modo somente leitura; ele não é conteúdo
do módulo Drupal Forum. Conteúdo editorial hospedeiro permanece, e tópicos de
sincronização da Revista não são convertidos em tópicos públicos nem em comentários.

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
| `aculta_forum_topic` | Node para tópico independente público | Bundle/Comment/Taxonomy em `config/install` e Config Sync; máquina <=32 caracteres. `aculta_forum` está habilitado no Runtime de teste; nenhum tópico foi criado. Permissões de criação e gestão do próprio tópico declaradas na dev.6. `content_editor` modera tópicos de outros autores pelo access hook do `aculta_user`, que exige confirmação real de e-mail. |
| `aculta_lms_topic` | Node para discussão pedagógica reservada | Planejado para adapter LMS; dependência opcional evita ciclo Forum↔LMS. |
| `aculta_discussion_post` | bundle Comment compartilhado | Configuração de Comment em Node preparada na dev.4; suporte reutilizável em outros bundles Node ainda requer prova Kernel na dev.7/integrações. |
| `aculta_forum_categories` | vocabulário Taxonomy de categorias públicas | Configuração de instalação adicionada na dev.4; a substituição do vocabulário `forums` fica para migração autorizada. |
| `field_aculta_discussion` | campo Comment em Nodes hospedeiros suportados | Configuração instalada inicialmente apenas no tópico público. Teste Kernel em dev.5 provou que um storage Comment pode ser anexado a `article`, `blog` e `aculta_forum_topic`; os fields de publicação e o provider correspondente ainda aguardam configuração explícita e integração. |
| `field_autor_legado` | referência a `aculta_legado_autor` em tópico/resposta Growroom restaurados | Storage de Node/Comment pertence ao `aculta_user`; instâncias de `aculta_forum_topic` e `aculta_discussion_post` acompanham `aculta_forum`. Conteúdo marcado é imutável; tópico permanece fechado. |

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
- O provider atende apenas bundles `article` e `blog` que tenham o campo compartilhado; ainda não há field instance de publicação na Config Sync.
- Leitura combina Node Entity Access com field access. Escrita requer leitura permitida, campo aberto e permissão `post comments`; moderação combina leitura e `administer comments`. As decisões variam por `user.permissions` e carregam cacheability do host/campo.
- `aculta_user` aplica moderação do primeiro comentário compartilhado a qualquer Node com `field_aculta_discussion`, incluindo Fórum, Revista, Wiki e novos contextos. O host `aculta_lms_topic` não é moderado, mas um comentário LMS publicado conta como participação aprovada nos demais contextos e retira o bloqueio temporário de criação de tópico. A opção fica em `/painel-administrativo/configuracoes/aculta/portal/participacao`; o bundle legado `comment_forum` conserva seu controle separado durante a migração.
- O provider editorial e a política de moderação estão cobertos por testes Unit/Kernel isolados, mas as instâncias de campo da Revista ainda não foram ativadas; Runtime/editorial permanece DEFERRED.
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
