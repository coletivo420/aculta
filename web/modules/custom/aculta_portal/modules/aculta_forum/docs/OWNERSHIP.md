# Ownership — discussão, identidade e apresentação

| Capacidade/dado | Fonte/dono | Integração permitida | Proibido |
| --- | --- | --- | --- |
| Nodes de tópico e publicação | Node/Core e `aculta_forum` para política de discussão | Entity API, Views, Access API | storage paralelo de tópico |
| Respostas públicas do Growroom | Entity `aculta_forum_post` revisionável; Comments Core existentes ficam legados até migração validada | Entity/Revision/Workflow APIs, resposta pai e acesso no tópico | criar Comments novos no Growroom ou remover dados sem migração comprovada |
| Contagem/atividade nas listas de tópicos | `comment_entity_statistics` do Core para Comments legados; `ForumPublicPostStatistics` agrega ForumPosts publicados | Plugins Views combinam contagem/timestamps; cache da lista invalida por `aculta_forum_post_list`; filtros Node access e Domain ficam fixos | escrever no agregado Core ou contar posts não publicados/idiomas não padrão |
| Comments em Publicações, LMS e Wiki | Comment/Core | Field e API de contexto da entidade hospedeira | mover esses contextos para o storage do ForumPost público |
| Categorias públicas | Taxonomy/Core e `aculta_forum` | vocabulário e Views | categoria paralela |
| Ação de criar tópico | `aculta_forum` consulta o access da rota Core | link render array em `views_pre_render`, token visual `button-primary` já existente, cacheability do resultado | link estático que ignore Entity Create Access ou regra contextual |
| Ação de responder/citar | `aculta_forum` valida ForumPost público ou Comment contextual e seu provider | Form/Access API do contexto, resposta pai e cacheability | link `comment.reply` usado como substituto da resposta revisionável pública; classes/markup visual no módulo |
| Identidade, apelido e User | User/Profile + `aculta_user` | Author Card API a definir | consulta de dados privados e cartão próprio do Forum |
| Avatar/foto | User picture + `aculta_user` + `aculta_image` | render autorizado por finalidade `avatar` | processar/excluir arquivo fora de `aculta_image` |
| Autoria histórica | entidade `aculta_legado_autor` + `aculta_user` | `LegacyAuthorManager`, campo existente, memorial coletivo | conta User, perfil, login ou cartão independente |
| Favorito/seguir/denúncia | Flag | seguir/salvar usam flags existentes; denúncias usam `sinalizar_comentario` para Comment e `sinalizar_post_forum` para posts revisionáveis, com permissão contextual, `HistoricForumFlagAccessHooks` e fila `aculta_user` do Growroom | armazenamento paralelo de ações; pôr acervo histórico ou outro contexto na fila pública; transferir flaggings sem preservar dono/timestamp |
| Avisos de seguidores do Fórum | Flag `seguir_topico` para assinatura; `aculta_messenger` / `aculta_aviso` para agregação e leitura; `aculta_mailer` para transporte | Comment, ForumPost e revisão de tópico publicam `MessengerNoticeEvent`; consumer resolve UUID/revisão, revalida assinatura/acesso e persiste pelo ledger/`aculta_aviso` existente, com marcadores independentes | Gerar chat/thread ao seguir, inbox privada paralela, avisos duplicados, fila/SMTP/storage próprios no Fórum |
| Conquistas | `aculta_game` avalia Comment ACULTA vigente e primeira publicação de ForumPost público; concessões e catálogo têm fonte única no `aculta_user` | trigger existente `forum_comment`, com migração/sync e autoria histórica excluídos | Forum contrib/legado, storage, contagem, XP, ranking ou concessão próprios no Forum |
| Cursos, atividades, matrícula | LMS + Group | adapter opcional `aculta_forum_lms` | autorização por role global ou cópia da matrícula |
| Galeria e anexos | Gallery/Media/File/PDF viewer + `aculta_image` para imagens | referências com access revalidado | cópia/download público que contorne os donos |
| Busca | Search API + Views | índice público com Nodes de tópico e ForumPosts; grants/publicação aplicados antes da paginação e tópico hospedeiro monitorado para reindexação | índice público com conteúdo LMS/Wiki; confiar apenas no acesso pós-busca |
| Dados preparados | `aculta_forum` e `aculta_user` conforme domínio | render arrays neutros com cacheability | tema consultando Entity API ou aplicando access |
| Markup, SDC, Bootstrap e CSS | ACULTA420 | props/slots neutros, tokens semânticos | CSS, classes de design ou templates no módulo |

## Cartões

`aculta_user` é dono único de resolução de identidade e view-models para cartões
de autor, participante e perfil. `aculta_forum` solicita a variante e informa
apenas contexto/ação; não define estrutura ou consulta perfil. ACULTA420 seleciona
o SDC, layout lateral/empilhado, light/dark e acessibilidade. Ver
`aculta_user/docs/AUTHOR-CARDS.md` e
`docs/portal/FRONTEIRA-MODULO-TEMA.md`.

## Conversas privadas e discussões contextuais

- Posts públicos do Growroom e Comments nos contextos Revista/LMS/Wiki são
  **discussões**, sujeitos à moderação e ao acesso da entidade de origem.
- O Fórum pode oferecer uma ação explícita **Iniciar conversa** entre membros,
  resolvida pelo serviço público do `aculta_messenger`, com permissão,
  preferências de contato, bloqueio e autorização da conta.
- Seguir tópico, responder, moderar ou matricular-se não cria nem associa
  participantes a threads de Private Message; não existe chat automático por
  tópico, turma ou verbete. Decisão F-D41.
- A interface de integração deve ser opcional e não criar dependência circular
  entre `aculta_forum` e `aculta_messenger`.

## Eventos e confiança

Evento de discussão carrega IDs/referências mínimas. No momento de exibir ou
entregar, o consumidor recarrega a entidade e revalida publicação, access,
membership, preferências e purpose. Payload enfileirado não é autorização.
