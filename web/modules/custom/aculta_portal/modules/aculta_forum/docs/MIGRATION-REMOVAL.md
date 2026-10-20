# Plano de retirada do Drupal Forum contrib

Status: conteúdo legado removido do Runtime de teste em 2026-10-20; o módulo
`drupal/forum` 1.1.3 continua instalado e habilitado enquanto os consumidores e
configurações restantes são auditados. O uninstall e a remoção Composer ainda
não foram executados. Os fixtures `[TESTE]` do bundle novo são tratados em
[`TEST-FIXTURES.md`](TEST-FIXTURES.md).

## Inventário conferido

| Tipo | Referências atuais observadas |
| --- | --- |
| Composer | `drupal/forum:^1.1.3` permanece no projeto e requer `drupal/history:^1`; `drupal/history:^1.0` agora também é requisito direto do projeto para manter os marcadores pessoais de leitura quando Forum contrib for removido. |
| Extensão | `config/sync/core.extension.yml` habilita `forum`; módulo instalado em `web/modules/contrib/forum`. |
| Modelo de conteúdo | Node bundle `forum`, vocabulary `forums`, Node field `taxonomy_forums`, Comment bundle `comment_forum`, campo `comment_forum` no Node. Configs opcionais do módulo contrib definem ainda displays, formulário e settings. |
| Config Sync | `core.entity_*` de node.forum/comment_forum; `node.type.forum.yml`; taxonomy.vocabulary.forums.yml; `views.view.aculta_forum_topics.yml`; `flag.sinalizar_comentario` aponta a bundle `comment_forum`; `field_discussao` exportado em blog aponta à Node bundle `forum`; campos de autoria legada. |
| `aculta_user` | `ParticipationRules` fixa bundles `forum`/`comment_forum`; `RestrictedForumGrants`, `RestrictedForumHooks`, `ForumModerationHooks` e `ActivityController` ainda têm consumidores a migrar. `DiscussionHooks` e `ForumTopicPresentationHooks` foram removidos; `config/install` ainda declara `field_discussao` legado em activity/article/document/project/wiki_entry. |
| `aculta_magazine` | Em dev.15 foram removidos `LegacyPublicationTopicLookup`, `ForumOpeningPost`, os campos `field_discussao` dos displays/configuração exportada e a dependência declarada `forum:forum`. `PublicationTypeConverter` continua movendo Comments compartilhados. Runtime cache rebuild e raiz Magazine HTTP 200; importação de configuração e conversão com Comments reais pendentes. |
| `aculta_messenger` | `MessageSubscribeEventHooks` atende criação/publicação de Node forum; `AvisoService`/`AvisoRetencao` têm política e tipos vinculados ao fórum. |
| Tema | `forum-list.html.twig`, `node--forum--full.html.twig`, `comment--comment-forum.html.twig`, `forum-index.css`, `discussion.css`, traduções e estilos do padrão Forum. |
| Documentação/gates | Portal/Forum/LMS/Magazine, mapa módulo-tema, fronteira, sitemap/purpose, slugs, buscas, avisos e políticas de participação referem Forum legado. Fazer `rg` global antes da remoção final. |

Lista pesquisável no checkout: `rg -n -i 'drupal/forum|node.forum|comment_forum|taxonomy_forums|field_discussao|ForumTopicSync|ForumSectionResolver|forum\\.forum' composer.json composer.lock config/sync web/modules/custom/aculta_portal web/themes/custom/aculta420 docs`.
Este comando inclui strings históricas; revisar cada resultado e não apagar
referências de auditoria/changelog sem motivo.

### Efeitos verificados no código contrib instalado

Na versão instalada `drupal/forum` 1.1.3, foram inspecionados
`src/ForumUninstallValidator.php`, `forum.services.yml`, `forum.install` e
`tests/src/Functional/ForumUninstallTest.php`. O validator bloqueia o uninstall
enquanto houver qualquer Node do bundle `forum` ou termo no vocabulário
configurado do Forum. O teste funcional upstream confirma a ordem: remover os
Nodes; remover os termos; então executar o uninstall oficial.

`forum_uninstall()` não exclui Nodes, Comments nem termos por conta própria.
Ele apaga os storages dos campos `node.taxonomy_forums`,
`node.comment_forum` e `taxonomy_term.forum_container`, chama `FieldPurger` com
lote de até 10 itens e libera o nome de bundle `forum`. Portanto, a exclusão de
conteúdo autorizada precisa ser uma etapa explícita anterior; o uninstall não é
um substituto para a limpeza dos Nodes/Comments. Remover `taxonomy_forums`
também afeta todos os Nodes que tenham esse field storage, mesmo fora do bundle
`forum`; o inventário não encontrou motivo para assumir que apagar apenas os
Nodes satisfaz esse impacto.

O vocabulário `forums` tem o campo legado `field_categoria_revista`. A auditoria
somente leitura de 2026-10-19 encontrou 5 dos 8 termos com referência a
`editorial_category` e 3 sem valor. Os 12 Nodes de publicações que apontam para
tópicos antigos (8 `article`, 4 `blog`) já têm `field_editorial_category`; nos
12 casos a categoria direta é igual ao destino indicado pelo mapeamento antigo
do termo do Fórum. Assim, a categoria editorial não precisa ser migrada do
vocabulário antigo; o campo auxiliar e seus valores podem ser retirados com o
legado, enquanto os campos diretos e os termos `editorial_category` permanecem.
O field config em Config Sync pertence à estrutura Magazine e depende
explicitamente de `taxonomy.vocabulary.forums`; remover essa definição de campo
e revisar sua field storage antes de retirar a configuração do vocabulário.

Os tópicos antigos são usados como destino de `field_discussao` e como abertura
histórica em 12 publicações. A Revista já tem Comment direto, mas a submissão e
exibição ainda não foram validadas por navegador com um Comment real. Até essa
validação e a sequência de retirada, manter os vínculos e Nodes de abertura.
A exclusão das entidades antigas deve ser feita pela API Drupal, com nova
consulta imediatamente antes da operação e revisão dos efeitos sobre Comments,
referências, revisions e arquivos. O uninstall não deve descartar associação
editorial implicitamente.

Este estudo é somente código/documentação: nenhum validator foi contornado,
nenhum campo/termo/Node foi alterado e o módulo continua habilitado. A referência
API confirma que o validator existe para impedir perda de dados durante o
uninstall; ver [registro de mudança do Core](https://www.drupal.org/node/2404451)
e [API do validator](https://api.drupal.org/api/drupal/core%21modules%21forum%21src%21ForumUninstallValidator.php/function/ForumUninstallValidator%3A%3Avalidate/11.x).

O antigo `DiscussionHooks` consultava `comment_forum` sem `topic->access('view')`
para renderizar trechos editoriais. Foi removido após confirmar que todos os 12
Nodes atualmente referenciados têm `field_aculta_discussion`; a Revista usa agora
o formatter Core direto e não carrega resumo de Comments do tópico antigo.
`ForumTopicHooks` de links legados foi removido (o Runtime não possui Comments
`comment_forum`). `LegacyPublicationTopicLookup` ainda lê vínculos para conversão
de tipo e `ForumOpeningPost` apresenta a publicação vinculada nos Nodes Forum antigos;
esses leitores serão retirados somente após preparar a limpeza dos dados.

## Bundles Drupal Forum e decisão de limpeza Growroom

F-D32 registrou que os Nodes `forum` e Comments `comment_forum` representavam o
Growroom restaurado. Em 2026-10-20, F-D33 substituiu a decisão de preservar esse
conteúdo: o responsável determinou que o banco de teste ficasse sem tópicos,
respostas, termos do vocabulário `forums` ou referências ativas àqueles tópicos.
Nenhum conteúdo antigo será importado para os bundles ACULTA.

O Drupal Forum contrib continua instalado apenas até que os consumidores,
campos, Views, flags e configurações dependentes sejam retirados pelo processo
oficial. O novo vocabulário `aculta_forum_categories` e seus 12 termos são
estrutura atual e permanecem. A limpeza do Runtime não alterou Nodes da Revista,
Wiki, LMS, usuários ou comentários diretos de publicação.

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
| Terms no vocabulário `forums` | 8 | Campo legado `field_categoria_revista`: 5 termos mapeiam para `editorial_category`, 3 vazios; categorias diretas das 12 publicações vinculadas conferem. Ver auditoria detalhada abaixo. |
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

Consultas adicionais de 2026-10-19, somente leitura, confirmaram: 8 publicações
`article` e 4 `blog` têm `field_discussao` não vazio; todas as 12 têm
`field_aculta_discussion` e `field_editorial_category` preenchidos. Comparação
por entidade confirmou que as 12 categorias diretas correspondem exatamente
ao destino do `field_categoria_revista` no termo do tópico vinculado. No lado
dos Nodes `forum`, 4 usam o termo `Editorial` e 25 `Notícias`; entre os 12
tópicos vinculados à Revista, são 4 `Editorial` e 8 `Notícias`. Esses resultados
eliminam a necessidade de migração de categoria, mas não validam o formulário
de Comment direto: sua submissão/exibição no navegador continua pendente.

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

O uninstall também exige retirar o vocabulário antigo `forums`. A verificação
confirmou que as categorias editoriais permanecem nos campos diretos das
publicações e que o mapeamento auxiliar não diverge. A remoção de termos e do
campo legado só pode ocorrer na janela autorizada de limpeza e não autoriza
excluir o vocabulário `editorial_category` ou dados de outras funcionalidades.

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

### Decisão operacional da auditoria de 2026-10-19 (superada por F-D33)

Naquele momento, foi registrado que não se deveria excluir o legado antes de
validar o restore e os consumidores editoriais. A determinação de 2026-10-20
substituiu essa decisão para o conteúdo do Runtime de teste. O registro histórico
permanece para explicar a sequência; ele não é a instrução operacional atual.

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

## Limpeza do Fórum antigo no Runtime de teste — 2026-10-20

Ambiente verificado imediatamente antes da operação: `hostname=toca`, checkout
`/home/piradopirata/projetos/aculta-site`, branch
`docs/aculta-forum-0.1.0-dev.1`, Drupal 11.4.10, URI
`https://growroom420.aculta.toca.net.br`, banco `aculta_runtime`. Produção não
foi consultada nem alterada.

Restore point integral, privado e externo ao Git:
`/home/piradopirata/.local/share/aculta-private-backups/aculta_runtime-forum-content-before-delete-20261020.sql.gz`;
modo `0600`, 6,397,232 bytes, `gzip -t` PASS, SHA-256
`2ad6723d6dc56599681cdeafd7758291698ee5b0d92d4e40734fd5835de65f43`. O dump
contém dados do Runtime; não copiar para repositório, produção ou artefatos
públicos. Restore isolado não foi executado.

Inventário repetido imediatamente antes da operação confirmou exatamente 30
Nodes `forum`, zero Comments `comment_forum`, oito termos no vocabulário
`forums` e 12 Nodes editoriais com `field_discussao` apontando a esses tópicos.
Os 12 Nodes tinham `field_aculta_discussion` preenchido e eram `article`/`blog`;
nenhum outro bundle usava os termos `forums`. O vocabulário novo
`aculta_forum_categories`, com 12 categorias, ficou fora do escopo.

Em uma transação via Entity API foram removidos os 30 tópicos e oito termos,
apagados comentários `comment_forum` (zero encontrados) e limpos os 12 valores
legados de `field_discussao` nas publicações. Nodes editoriais, seus conteúdos,
revisões de conteúdo e comentários diretos permaneceram. As verificações após a
operação encontraram zero em: Nodes `forum`, Comments `comment_forum`, termos
`forums`, referências ativas e históricas `field_discussao` aos tópicos,
revisões dos Nodes excluídos, aliases, flaggings, `forum_index` e tabela
relacional `forum`.

O uninstall continua pendente. `module_installer->validateUninstall(['forum'])`
retornou lista vazia depois da limpeza. O dry-run de dependências ainda informa
que a remoção atualizará `field.field.node.blog.field_discussao` e permissões de
papéis, e excluirá configurações do bundle/campos/termos antigos. Restam revisar
consumidores PHP/Views/tema, executar uninstall oficial com backup vigente e só
depois remover `drupal/forum` do Composer. A limpeza de conteúdo não foi tratada
como autorização para fazer essas etapas sem inspecionar seus efeitos.

## Rollback e limites

Código/config pode ser revertido enquanto nenhum dado legado foi excluído. Após
exclusão, rollback exige o restore point descrito acima ou procedimento de
reimportação idempotente, nunca apenas `git revert`. Nenhuma ação atinge produção
sem decisão própria do responsável; merge em `main` não é deploy.
