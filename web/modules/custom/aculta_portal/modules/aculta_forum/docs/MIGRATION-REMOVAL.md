# Plano de retirada do Drupal Forum contrib

Status: dev5 em auditoria; remoção ainda não autorizada operacionalmente. O
inventário de código/configuração foi atualizado em 2026-10-19 e o Runtime de
teste foi consultado em modo somente leitura. `drupal/forum` 1.1.3 continua
instalado e habilitado. Nenhuma entidade, configuração, Composer ou Runtime foi
alterado nesta auditoria.

## Inventário conferido

| Tipo | Referências atuais observadas |
| --- | --- |
| Composer | `composer.json` requer `drupal/forum:^1.1.3`; lock 1.1.3 requer Core `~11.3 || ~12` e `drupal/history:^1`. |
| Extensão | `config/sync/core.extension.yml` habilita `forum`; módulo instalado em `web/modules/contrib/forum`. |
| Modelo de conteúdo | Node bundle `forum`, vocabulary `forums`, Node field `taxonomy_forums`, Comment bundle `comment_forum`, campo `comment_forum` no Node. Configs opcionais do módulo contrib definem ainda displays, formulário e settings. |
| Config Sync | `core.entity_*` de node.forum/comment_forum; `node.type.forum.yml`; taxonomy.vocabulary.forums.yml; `views.view.aculta_forum_topics.yml`; `flag.sinalizar_comentario` aponta a bundle `comment_forum`; `field_discussao` exportado em blog aponta à Node bundle `forum`; campos de autoria legada. |
| `aculta_user` | `ParticipationRules` fixa bundles `forum`/`comment_forum`; `RestrictedForumGrants`, `RestrictedForumHooks`, `ForumModerationHooks`, `ForumTopicPresentationHooks`, `DiscussionHooks`, `ActivityController`; `config/install` declara `field_discussao` em activity/article/document/project/wiki_entry como entity reference à Node bundle `forum`. |
| `aculta_magazine` | `ForumTopicSync`, `ForumSectionResolver`, `PublicationTypeConverter`, `ForumTopicHooks`, `ForumOpeningPost` e serviços; cria Node `forum` ao salvar publicação com categoria editorial; `field_discussao` de `blog` referencia Node bundle `forum`. |
| `aculta_messenger` | `MessageSubscribeEventHooks` atende criação/publicação de Node forum; `AvisoService`/`AvisoRetencao` têm política e tipos vinculados ao fórum. |
| Tema | `forum-list.html.twig`, `node--forum--full.html.twig`, `comment--comment-forum.html.twig`, `forum-index.css`, `discussion.css`, traduções e estilos do padrão Forum. |
| Documentação/gates | Portal/Forum/LMS/Magazine, mapa módulo-tema, fronteira, sitemap/purpose, slugs, buscas, avisos e políticas de participação referem Forum legado. Fazer `rg` global antes da remoção final. |

Lista pesquisável no checkout: `rg -n -i 'drupal/forum|node.forum|comment_forum|taxonomy_forums|field_discussao|ForumTopicSync|ForumSectionResolver|forum\\.forum' composer.json composer.lock config/sync web/modules/custom/aculta_portal web/themes/custom/aculta420 docs`.
Este comando inclui strings históricas; revisar cada resultado e não apagar
referências de auditoria/changelog sem motivo.

`DiscussionHooks` hoje consulta comentários publicados do Node vinculado por
`comment_forum` com EntityQuery `accessCheck(FALSE)` para montar trecho e
contagem, enquanto valida publicação do tópico mas não chama explicitamente
`topic->access('view')` nesse método. Isso é um ponto de migração a revisar para
paridade de access/cache; não afirma, isoladamente, uma exposição confirmada no
Runtime. `ForumTopicSync::sync()` cria Node `forum` por publicação com categoria
editorial e `ForumOpeningPost` apresenta o conteúdo editorial como abertura.

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
| Nodes bundle `forum` referenciados por `field_discussao` | 12 | Um vínculo editorial por node; preservar entidades editoriais e as discussões até migração |
| Comments bundle `comment_forum` | 0 | Campo configurado, sem entidades Comment nesse bundle |
| Terms no vocabulário `forums` | 8 | Vocabulário também tem `field_categoria_revista`; uso editorial precisa ser migrado antes da remoção |
| Arquivos de Config Sync com dependências dos bundles/campos antigos | 24 | Inclui permissões, flags, Magazine e displays |
| Flagging entities | 2 no total | Contagem global; associação ao Forum ainda requer consulta específica |

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

### Decisão operacional desta auditoria

- Não excluir nodes, termos, comments ou flags; não desinstalar `forum`, não
  remover `drupal/forum` do Composer e não executar updates/import/export.
- Preservar temporariamente os 12 tópicos vinculados à Revista e seus campos
  `field_discussao`. Retirar a sincronização automática somente junto da
  migração que mantém a discussão acessível na entidade editorial.
- A retirada executável aguarda a migração dos consumidores de Magazine e User
  e a cobertura contextual correspondente. Gates para exclusão/uninstall:
  backup atual de Runtime e arquivos relacionados; restore validado em ambiente
  de teste isolado; inventário repetido; plano de rollback específico; e
  confirmação do escopo exato dos dados.
- O responsável autorizou em princípio excluir apenas conteúdo antigo do Forum
  no teste, condicionado à confirmação de ambiente e escopo. Isso não cobre
  publicações, contas, LMS, Wiki nem outras entidades. A validação de restore e
  do escopo ainda não satisfaz os pré-requisitos para apagar.

## Portas obrigatórias antes de remover

1. Fechar contratos e machine names; implementar bundles/campos/provedores e testes de instalação/update em uma fase não destrutiva.
2. Migrar consumidores funcionais de `aculta_user`, `aculta_magazine`, `aculta_messenger` e `aculta_game`, com paridade de acesso, apresentação, notificações, URLs e busca. A interrupção de `ForumTopicSync` aguarda o substituto editorial para não deixar publicações sem discussão.
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
