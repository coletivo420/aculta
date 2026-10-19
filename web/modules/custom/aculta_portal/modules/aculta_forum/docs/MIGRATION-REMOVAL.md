# Plano de retirada do Drupal Forum contrib

Status: inventário e plano documental somente (`0.1.0-dev.1`). `drupal/forum`
1.1.3 continua instalado e habilitado em `origin/main` (`core.extension.yml`).
Nenhuma entidade, configuração, Composer ou Runtime foi alterado por este plano.

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

## Portas obrigatórias antes de remover

1. Fechar contratos e machine names; implementar bundles/campos/provedores e testes de instalação/update em uma fase não destrutiva.
2. Remover criação automática de tópico de publicação e migrar consumidores funcionais de `aculta_user`, `aculta_magazine` e `aculta_messenger`, com paridade de acesso, apresentação, notificações, URLs e busca.
3. Inventariar no ambiente de teste contagem, IDs, comentários, categorias, flags, arquivos, vínculos e dependências de cada Node; separar conteúdo histórico de conteúdo contemporâneo e de conteúdo editorial não relacionado.
4. Preparar backup/restauração e plano de rollback verificados conforme `docs/operations/environments.md` e `docs/operations/DEPLOYMENT.md`; ambiente/URI/banco precisam ser confirmados com hostname, pwd, Git e `drush status` antes de qualquer operação persistente.
5. Aprovar explicitamente o escopo de exclusão de dados antigos. O pedido permite discutir exclusão de conteúdo Forum, mas não autoriza apagar conteúdo da Revista, Wiki, LMS, usuários ou outros módulos.
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
