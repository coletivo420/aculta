# ACULTA Forum — Growroom420 e discussões contextuais

Submódulo de `aculta_portal`. Versão atual: **0.1.0-dev.156**. O
direcionamento vigente, aprovado pelo responsável em 2026-10-19, é reconstruir
as discussões ACULTA com APIs nativas; Forum contrib foi desinstalado do Runtime
de teste e removido do Composer após a limpeza autorizada. A dev.4 introduziu o módulo executável e
a Context API; a View pública já serve a raiz do Runtime de teste e `/criar-topico`
usa Entity Form/Entity Create Access do Core. A ação na listagem aparece somente
quando a rota permite a criação, e a decisão integra o cache da View. O índice
oferece filtro hierárquico pelo vocabulário próprio, sem termos inventados.
O índice `/categorias` apresenta nomes e descrições dos termos do Fórum e se
liga à página de discussões. Ele usa a View `aculta_forum_categories`; a navegação
visual hierárquica e os contadores de tópicos continuam planejados.
`/pesquisar` pesquisa título/corpo de tópicos públicos e oferece categoria em
select hierárquico. Os nomes vêm da Taxonomy atual e respeitam Entity Access;
o filtro usa IDs inteiros indexados, sem tipo de campo ou lista paralela.
Quando não há correspondências, a View orienta a ajustar a busca ou explorar
as categorias.
Fixtures de tópicos do Runtime de teste são inventariadas em
[TEST-FIXTURES](docs/TEST-FIXTURES.md). `aculta_user`
já dispõe de um builder neutro de cartões; o ACULTA420 apresenta o SDC
experimental compartilhado. A integração dos cartões nas discussões atuais e no
motor novo segue por contexto e fases.

## Arquitetura e regras

- A administração do Growroom fica em `/painel-administrativo/forum`. Os
  caminhos específicos de ferramentas — listagem, filas, denúncias,
  configurações e movimentos — redirecionam localmente ao painel e não executam
  operações. Rotas genéricas Core e configuração compartilhada ficam fora da
  seção. A ação VBO segue disponível na configuração da View, mas não existe
  página administrativa do Fórum que a exponha enquanto vigor a decisão F-D38.

- Growroom420 hospeda somente tópicos públicos independentes.
- Publicações, LMS e Wiki usam comentários no contexto da entidade original; não
  há tópico público automático para publicação ou verbete.
- LMS tem adapter opcional e acesso revalidado por Group/membership e Entity Access.
- `aculta_user` é o dono único de identidade e cartões de autor/participante/perfil. Variantes distinguem `author_forum`, `author_comment`, `author_editorial`, `author_lms`, `author_wiki` e `author_growroom_historico`; autoria histórica não passa pelo resolver de User.
- Nas listagens públicas, a última autoria usa o UID agregado pelo Comment Core e o cartão `author_forum` de `aculta_user`. O nome de login e `last_comment_name` não são exibidos; identidade e avatar obedecem à política de perfil e cache do `aculta_user`.
- ACULTA420 é o dono visual; `aculta_forum` entrega contrato neutro, acesso e cache.
- Core/contrib permanecem fonte de verdade para Node, Taxonomy, Views, Search
  API, History, Flag e workflow/revisão. A entidade `aculta_forum_post` passa a
  guardar novas publicações revisionáveis; os Comments do Fórum seguem como
  leitura/escrita vigente até a migração e o corte aprovados. Não criar storage
  paralelo de tópico/categoria.
- Imagens passam por `aculta_image`; avisos pelo `aculta_messenger`.
- O conteúdo antigo permanece ausente no Runtime de teste; somente as categorias
  ACULTA novas foram preservadas. A retirada executável do contrib ocorreu em dev.50.

## Documentação

- [Arquitetura](docs/ARQUITETURA.md)
- [Contextos e API](docs/CONTEXTS.md)
- [Ownership](docs/OWNERSHIP.md)
- [Matriz Advanced Forum](docs/ADVANCED-FORUM-PORT.md)
- [Pesquisa de módulos contrib e critérios para portar código](docs/CONTRIB-PORT-RESEARCH.md)
- [Experiência e ferramentas Discourse](docs/DISCOURSE-EXPERIENCE.md)
- [Planejamento de movimentação de tópicos e comentários](docs/MOVE-TOOLS.md)
- [Administração do Fórum geral](docs/ADMIN-TOOLS.md)
- [Pesquisa](docs/PESQUISA.md)
- [Busca pública](docs/SEARCH.md)
- [Plano de retirada/migração](docs/MIGRATION-REMOVAL.md)
- [Classes e hooks planejados](docs/CLASSES-AND-HOOKS.md)
- [Matriz de testes](docs/TEST-MATRIX.md)
- [Decisões](docs/DECISOES.md)
- [Roadmap](docs/ROADMAP.md)
- [Reconstrução histórica](docs/RECONSTRUCAO.md) — histórico de pesquisa/importação, não autorização de exclusão.
- [Changelog](CHANGELOG.md)

O contrato de cartões, privacidade e API fica em
[`aculta_user/docs/AUTHOR-CARDS.md`](../aculta_user/docs/AUTHOR-CARDS.md). Normas
comuns: [Drupal 11+](../../../../../../docs/portal/DRUPAL-11-STANDARDS.md),
[guardrails](../../../../../../docs/portal/GUARDRAILS.md),
[fronteira módulo-tema](../../../../../../docs/portal/FRONTEIRA-MODULO-TEMA.md),
[mapa módulo-tema](../../../../../../docs/portal/MAPA-MODULO-TEMA.md),
[fonte da verdade](../../../../../../docs/portal/SOURCE-OF-TRUTH.md),
[participação](../../../../../../docs/portal/PAPEIS-PARTICIPACAO.md),
[LMS](../../../../../../docs/portal/LMS-INTEGRATION-CONTRACTS.md),
[Wiki](../../../../../../docs/portal/WIKI.md) e
[notificações](../../../../../../docs/portal/AVISOS-E-NOTIFICACOES.md).

## Estado confirmado na auditoria

Estado de remoção conferido em 2026-10-20: `drupal/forum` não está no Composer,
em `core.extension.yml` nem em `web/modules/contrib`; Node `forum`, vocabulário
`forums`, Comment `comment_forum`, tabelas `forum`/`forum_index` e o campo
`field_discussao` estão ausentes no Runtime de teste. Consulte o [inventário de remoção](docs/MIGRATION-REMOVAL.md) e
[auditoria de cartões](../aculta_user/docs/AUTHOR-CARDS.md).

## Versão e execução

A dev.5 completou a limpeza e a retirada contrib no Runtime de teste. Os
Comments diretos de `article`/`blog` e as categorias ACULTA são independentes do
Forum antigo. Um restore point pós-limpeza e pré-uninstall foi criado e validado
por `gzip -t`; contém zero conteúdo antigo. A configuração versionada foi
limpa sem importar Config Sync integral, bloqueado pelo drift preexistente. O índice
público do novo bundle está ativo na raiz do Runtime e `/criar-topico` verifica
Entity Create Access. Releases Portal, User e tema conservam linhas próprias;
o roadmap detalha as próximas entregas.

A lista personalizada `/discussoes/nao-lidas` também mostra o número de novas
respostas por tópico e liga diretamente à primeira resposta não lida. Essa
capacidade usa o campo `node_new_comments` do módulo History, apenas nessa View
autenticada com cache desativado; o campo esconde contagens vazias.

Na página de discussão, o controle “Não lidas” da barra de progresso depende
somente da classe `.new` que o History aplica aos Comments renderizados para a
conta autenticada. Ele permanece oculto sem uma mensagem marcada; usa o mesmo
salto de progresso e não mantém contagem, timestamp ou histórico paralelo.
## Administração contextual de comentários

A View `aculta_forum_admin_comments` mantém páginas próprias em
`/painel-administrativo/forum/comentarios` e nas três subabas de estado:
`/painel-administrativo/forum/comentarios/publicados`,
`/painel-administrativo/forum/comentarios/rejeitados` e
`/painel-administrativo/forum/comentarios/aguardando-moderacao`. Os displays
exigem `administer comments`, filtram o Comment compartilhado, o campo de
discussão, o tópico público e o Domain ativo do Growroom. A página central aponta
apenas para a entrada Comentários; a administração genérica de comentários do
Core permanece fora desta seção.

Core Comment distingue publicado de não publicado. Para distinguir uma rejeição
definitiva de uma pendência, o bundle compartilhado usa `field_aculta_rejected`;
moderadores com a permissão `administer comments` marcam esse campo no formulário
de edição de comentário; o acesso ao campo é negado aos demais usuários. A fila
pendente considera não publicados sem a marca; a rejeitada considera não
publicados com a marca. Publicar um comentário rejeitado tira-o da fila de
rejeitados. O campo não aparece no display público.

## Posição como subkernel compartilhado

`aculta_forum` possui estrutura própria e contratos reutilizados na Revista, Wiki e LMS. Tópicos gerais ficam exclusivamente no Fórum público; comentários de publicação, discussões de verbetes e de turmas permanecem associados às entidades/contextos de origem, com Group e access revalidados. `aculta_user` centraliza cartões, `aculta_image` processa imagens e `aculta_messenger` fornece avisos; o Studio oferecerá moderação autorizada sem novo storage nem permissão paralela. Veja `docs/architecture/aculta-kernels.md` na raiz.
