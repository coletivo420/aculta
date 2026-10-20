# Plano de retirada do Drupal Forum contrib

Status: dev5 em auditoria; pré-requisitos operacionais para remoção ainda não
foram satisfeitos. `drupal/forum` 1.1.3 continua instalado e habilitado; nenhum
conteúdo/configuração do sistema legado foi alterado e não houve uninstall. Os
fixtures `[TESTE]` do bundle novo são tratados separadamente em
[`TEST-FIXTURES.md`](TEST-FIXTURES.md).

## Inventário conferido

| Tipo | Referências atuais observadas |
| --- | --- |
| Composer | `drupal/forum:^1.1.3` permanece no projeto e requer `drupal/history:^1`; `drupal/history:^1.0` agora também é requisito direto do projeto para manter os marcadores pessoais de leitura quando Forum contrib for removido. |
| Extensão | `config/sync/core.extension.yml` habilita `forum`; módulo instalado em `web/modules/contrib/forum`. |
| Modelo de conteúdo | Node bundle `forum`, vocabulary `forums`, Node field `taxonomy_forums`, Comment bundle `comment_forum`, campo `comment_forum` no Node. Configs opcionais do módulo contrib definem ainda displays, formulário e settings. |
| Config Sync | `core.entity_*` de node.forum/comment_forum; `node.type.forum.yml`; taxonomy.vocabulary.forums.yml; `views.view.aculta_forum_topics.yml`; `flag.sinalizar_comentario` aponta a bundle `comment_forum`; `field_discussao` exportado em blog aponta à Node bundle `forum`; campos de autoria legada. |
| `aculta_user` | `ParticipationRules` fixa bundles `forum`/`comment_forum`; `RestrictedForumGrants`, `RestrictedForumHooks`, `ForumModerationHooks` e `ActivityController` ainda têm consumidores a migrar. `DiscussionHooks` e `ForumTopicPresentationHooks` foram removidos; `config/install` ainda declara `field_discussao` legado em activity/article/document/project/wiki_entry. |
| `aculta_magazine` | `LegacyPublicationTopicLookup`, `PublicationTypeConverter` e `ForumOpeningPost` ainda preservam/leem vínculos históricos; `ForumSectionResolver` e updates 10001/10002 de criação do vocabulário/pastas foram aposentados em dev.14. `ForumTopicHooks` removido em dev.12 após confirmar zero Comments `comment_forum`. `field_discussao` ainda referencia Nodes Forum. |
| `aculta_messenger` | `MessageSubscribeEventHooks` atende criação/publicação de Node forum; `AvisoService`/`AvisoRetencao` têm política e tipos vinculados ao fórum. |
| Tema | `forum-list.html.twig`, `node--forum--full.html.twig`, `comment--comment-forum.html.twig`, `forum-index.css`, `discussion.css`, traduções e estilos do padrão Forum. |
| Documentação/gates | Portal/Forum/LMS/Magazine, mapa módulo-tema, fronteira, sitemap/purpose, slugs, buscas, avisos e políticas de participação referem Forum legado. Fazer `rg` global antes da remoção final. |

Lista pesquisável no checkout: `rg -n -i 'drupal/forum|node.forum|comment_forum|taxonomy_forums|field_discussao|ForumTopicSync|ForumSectionResolver|forum\\.forum' composer.json composer.lock config/sync web/modules/custom/aculta_portal web/themes/custom/aculta420 docs`.
Este comando inclui strings históricas; revisar cada resultado e não apagar
referências de auditoria/changelog sem motivo.

O antigo `DiscussionHooks` consultava `comment_forum` sem `topic->access('view')`
para renderizar trechos editoriais. Foi removido após confirmar que todos os 12
Nodes atualmente referenciados têm `field_aculta_discussion`; a Revista usa agora
o formatter Core direto e não carrega resumo de Comments do tópico antigo.
`ForumTopicHooks` de links legados foi removido (o Runtime não possui Comments
`comment_forum`). `LegacyPublicationTopicLookup` ainda lê vínculos para conversão
de tipo e `ForumOpeningPost` apresenta a publicação vinculada nos Nodes Forum antigos;
esses leitores serão retirados somente após preparar a limpeza dos dados.

## Separação entre Drupal Forum e acervo histórico Growroom

O conteúdo do Drupal Forum contribuído descrito nesta auditoria é uma base
legada local para retirada; sua exclusão permanece no plano operacional e não é
uma importação. O acervo Growroom a restaurar é uma origem histórica distinta,
que será importada para Nodes `aculta_forum_topic` e Comments
`aculta_discussion_post`, com referências `field_autor_legado` a `aculta_legado_autor`.

O acervo importado será preservado como somente leitura: Nodes
`aculta_forum_topic` e Comments `aculta_discussion_post` marcados com
`field_autor_legado` e respostas relacionadas
não podem ser editados, excluídos, aprovados/desaprovados ou reabertos por
nenhum papel. A discussão deve chegar fechada; `aculta_user` aplica o estado
fechado no presave e nega operações de alteração. Nenhum item do Drupal Forum
contrib é convertido nesse acervo. A referência histórica existente nos bundles
`forum` e `comment_forum` não os classifica como parte da restauração. Ver F-D31
em `DECISOES.md`.

## Auditoria do Runtime de teste — 2026-10-19

Ambiente confirmado antes da consulta: `hostname=toca`, checkout
`/home/piradopirata/projetos/aculta-site`, branch
`docs/aculta-forum-0.1.0-dev.1`, Drush conectado ao banco `aculta_runtime`.
Foi usado `--uri=https://growroom420.aculta.toca.net.br` para resolver o
purpose. Esse é o Runtime Homelab de teste, não produção. Consultas Entity API
com `accessCheck(FALSE)` foram somente de leitura e retornaram:

| Dado legado | Quantidade | Observação |
| --- | ---: | --- |
| Nodes bundle `forum` | 30 | 12 publicados vinculados a publicações; 1 publicado independente; 17 não publicados sem referência editorial |
| Nodes bundle `forum` referenciados por `field_discussao` | 12 | Um vínculo editorial por node; manter o vínculo temporariamente até o comentário direto estar operacional, depois excluir o tópico-casca sem importar seu conteúdo |
| Comments bundle `comment_forum` | 0 | Campo configurado, sem entidades Comment nesse bundle |
| Terms no vocabulário `forums` | 8 | Vocabulário também tem `field_categoria_revista`; uso editorial precisa ser migrado antes da remoção |
| Arquivos de Config Sync com dependências dos bundles/campos antigos | 24 | Inclui permissões, flags, Magazine e displays |
| Flagging entities | 2 no total; 0 do Forum | Ambos apontam a `site_alert` (flags `aculta_site_alert_dismissed` e `aculta_site_alert_read`) |
| Drupal Core `validateUninstall(['forum'])` | Bloqueado | Exige excluir todos os nodes `forum` e todos os termos `forums` antes do uninstall |

Restore point pré-limpeza criado em 2026-10-19 no servidor `toca`, após
confirmar Drush URI Magazine e banco `aculta_runtime`:
`/home/piradopirata/.local/share/aculta-private-backups/forum-removal-before-delete-20261019.sql.gz`.
Arquivo fora do Git, modo `0600`, 5,841,604 bytes; `gzip -t` passou. SHA-256:
`17ce96d31de417f18892a6366a929162044df30b09dfbff058da77e6451183da`.
Este ponto recupera o banco, não os arquivos públicos/privados. O dump ainda não
foi restaurado em uma base isolada; nenhuma exclusão foi executada.

Consulta adicional de 2026-10-19, somente leitura, agrupou os vínculos por
bundle hospedeiro: 8 publicações `article` e 4 `blog` têm `field_discussao` não
vazio. Os mesmos 12 Nodes têm também o campo `field_aculta_discussion`; essa
presença de campo não prova que os Comments diretos estejam homologados por
submissão/browser. Manter os vínculos e Nodes de abertura enquanto forem
necessários para leitura histórica ou conversão de tipo; não remover o fallback
nem limpar esses registros até a validação funcional e a sequência de retirada.

### Política de dados para o novo Fórum

O responsável determinou que o novo Fórum deve começar limpo. Portanto, o
conteúdo do Drupal Forum antigo **não será migrado** para `aculta_forum_topic`
nem convertido em `aculta_discussion_post`. Na janela de retirada autorizada,
serão eliminados os tópicos legados e todos os comentários ligados a eles; a
consulta imediatamente anterior deverá confirmar a contagem e os bundles reais
de Comment, incluindo respostas aninhadas. No inventário de 2026-10-19 havia 30
tópicos `forum` e zero comentários `comment_forum`.

Os 12 nós que funcionam como abertura histórica da Revista não serão
preservados como tópicos. A Revista já usa comentário direto na publicação e a
API de sincronização foi removida; `LegacyPublicationTopicLookup` somente lê
vínculos antigos. Seus conteúdos editoriais e entidades de publicação permanecem intocados. O
novo campo começa sem comentários, e os outros contextos também começam sem
posts importados. Não remover contas, Nodes editoriais, cursos, turmas,
atividades, verbetes, imagens ou arquivos por causa desta limpeza.

O uninstall também exige retirar o vocabulário antigo `forums`; verificar e
migrar antes qualquer uso editorial (incluindo `field_categoria_revista`). A
remoção de termos/categorias antigos é limitada aos dados do módulo Forum e não
autoriza exclusão de vocabulários ou dados de outras funcionalidades.

O `aculta_forum` está habilitado no Runtime, mas seu provider novo atende o
bundle `aculta_forum_topic`; os 30 nodes antigos e seus consumidores não foram
migrados. `aculta_magazine` ainda declara `forum:forum`, sincroniza tópicos,
usa `field_discussao` e apresenta a abertura editorial. `aculta_user` ainda
implementa acesso, moderação, atividade e apresentação para `forum` e
`comment_forum`. Logo, a remoção agora quebraria consumidores ativos e perderia
as discussões de 12 publicações antes de existir o substituto contextual
previsto na dev.8.

Os backups observados em `/home/aculta/private/aculta` datam de 2026-10-17 e
2026-10-18 e foram criados para outras alterações. Nenhum foi validado como
restore point específico desta retirada; não são base suficiente para exclusão.
Não foi criado backup nesta subfase.

### Atualização dev.9 — discussão direta na Revista

Após a auditoria read-only acima, foram aplicadas quatro configurações por
importação parcial no Runtime `aculta_runtime`: field instances Comment de
`article` e `blog`, mais os dois displays full. Config Sync ficou sem divergência
nesses objetos. O default aberto foi observado sem persistência em Nodes antigos.
Com o usuário de teste uid 5, a renderização em memória incluiu o formulário de
comentário; nenhum comentário foi submetido. Havia 14 publicações publicadas, 30
Nodes Forum legados, nenhum `comment_forum` e nenhum Comment compartilhado.

Os 12 Nodes `forum` ligados às publicações e seus vínculos `field_discussao`
continuam intactos. Os hooks de insert/update/delete de `aculta_magazine` foram
retirados: salvar, atualizar ou excluir uma publicação não cria, altera ou
despublica tópico legado. Permanecem temporariamente serviços de leitura e
conversão dos vínculos antigos e o ajuste visual de links de comentários. O
Forum contrib, o pacote Composer, os tópicos e o vocabulário não foram removidos.

### Limpeza posterior dos fixtures do fórum novo — 2026-10-19

Em nova conferência do Runtime `toca` (Drupal 11.4.10, URI do purpose `forum`,
banco `aculta_runtime`), foram encontrados os oito Nodes `[TESTE]` 383–390 do
bundle `aculta_forum_topic`, todos publicados por uid 5. O responsável já havia
determinado que os tópicos e comentários de teste fossem removidos para deixar
o novo Fórum sem conteúdo. Antes da operação, cada ID foi revalidado por bundle,
título e owner; os Nodes não tinham Comments, Flags, nem referências editoriais
ou externas. A Entity API removeu somente esses oito Nodes. Não havia Comments
de teste.

Consulta posterior confirmou 0 Nodes `aculta_forum_topic` e 0 Comments
`aculta_discussion_post`. Os 12 termos da hierarquia inicial foram preservados
como estrutura de categoria solicitada. O Fórum legado continua ativo: 30 Nodes
`forum` (13 publicados/17 não publicados), 8 termos `forums`, 0 Comments
`comment_forum` e 12 vínculos `field_discussao` das publicações. Nenhuma
configuração foi alterada, e nenhum conteúdo editorial ou legado foi removido.
Os oito aliases antigos não têm mais registro de Pathauto e respondem HTTP 404.
Detalhes e IDs: [`TEST-FIXTURES.md`](TEST-FIXTURES.md).

### Decisão operacional desta auditoria

- Não excluir Nodes, termos, Comments ou Flags do Forum legado; não desinstalar
  `forum`, não remover `drupal/forum` do Composer e não executar
  updates/import/export nesta subfase.
- Preservar temporariamente os 12 tópicos vinculados à Revista e seus campos
  `field_discussao`. A discussão direta via Comment já está instalada para
  `article` e `blog`; a sincronização automática foi removida sem apagar ou
  migrar os tópicos antigos.
- A remoção do contrib aguarda a migração dos consumidores restantes de
  Magazine e User e a cobertura contextual correspondente. Gates para exclusão/uninstall:
  backup atual de Runtime e arquivos relacionados; restore validado em ambiente
  de teste isolado; inventário repetido; plano de rollback específico; e
  confirmação do escopo exato dos dados.
- Na execução aprovada, não migrar conteúdo de discussão: após provar os novos
  fluxos vazios por contexto e desativar os consumidores antigos, excluir os
  Nodes de tópico e Comments do Forum legado; repetir consulta e confirmar zero
  antes do uninstall oficial. Manter conteúdo de publicação e demais entidades.
- Preservar `drupal/history` como dependência independente antes de remover
  `drupal/forum`; hoje a versão contrib substitui History Core e implementa
  leitura do usuário. A versão 1.0.0 não é coberta pela política de avisos de
  segurança do Drupal.org; esse risco e a necessidade de acompanhar releases
  devem permanecer no plano de operação.
- O responsável autorizou em princípio excluir apenas conteúdo antigo do Forum
  no teste, condicionado à confirmação de ambiente e escopo. Isso não cobre
  publicações, contas, LMS, Wiki nem outras entidades. A validação de restore e
  do escopo ainda não satisfaz os pré-requisitos para apagar.

## Portas obrigatórias antes de remover

1. Fechar contratos e machine names; implementar bundles/campos/provedores e testes de instalação/update em uma fase não destrutiva.
2. Migrar consumidores funcionais de `aculta_user`, `aculta_magazine`, `aculta_messenger` e `aculta_game`, com paridade de acesso, apresentação, notificações, URLs e busca. A discussão editorial direta está ativa; remover lookup/readers de vínculos antigos somente quando o corte preservar os caminhos acordados.
3. Repetir no Runtime de teste o inventário de nodes, comments, termos, flags, arquivos, vínculos e dependências imediatamente antes da operação; separar conteúdo histórico, contemporâneo e editorial.
4. Preparar backup/restauração e plano de rollback verificados conforme `docs/operations/environments.md` e `docs/operations/DEPLOYMENT.md`; ambiente/URI/banco precisam ser confirmados com hostname, pwd, Git e `drush status` antes de qualquer operação persistente.
5. Conferir a autorização já registrada contra o inventário atual e delimitar os IDs/bundles de conteúdo Forum. Ela não autoriza apagar conteúdo da Revista, Wiki, LMS, usuários ou outros módulos.
6. Executar somente em Runtime de teste identificado, em etapa dedicada e com relatório de impacto/recuperação. Excluir apenas entidades legadas no escopo autorizado por Entity API; preservar configurações/cópias de recuperação conforme plano.
7. Desinstalar `forum` pelo mecanismo oficial Drupal após remover dependentes e inspecionar validators/impacto. Confirmar `updatedb`/uninstall plan antes de executar.
8. Só depois da desinstalação concluída e estado recuperável, retirar pacote Composer e regenerar lock. Fazer auditoria residual de extensões, plugins, config e comandos.
9. Repetir instalações, updates, uninstall, gates, testes de acesso e revisão de dados; documentar o resultado antes de declarar fase concluída.

Não executar `drush pm:uninstall`, `composer remove`, exclusão em massa, `updb`, importação integral ou alteração de Runtime nesta fase documental.

## Rollback e limites

Código/config pode ser revertido enquanto nenhum dado legado foi excluído. Após
exclusão, rollback exige restore point anterior validado ou procedimento de
reimportação idempotente, nunca apenas `git revert`. Nenhuma ação atinge produção
sem decisão própria do responsável; merge em `main` não é deploy.
