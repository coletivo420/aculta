# Ownership — discussão, identidade e apresentação

| Capacidade/dado | Fonte/dono | Integração permitida | Proibido |
| --- | --- | --- | --- |
| Nodes de tópico e publicação | Node/Core e `aculta_forum` para política de discussão | Entity API, Views, Access API | storage paralelo de tópico |
| Respostas | Comment/Core | Campos Comment e API de contexto | entidade/tabela paralela de resposta |
| Categorias públicas | Taxonomy/Core e `aculta_forum` | vocabulário e Views | categoria paralela |
| Ação de criar tópico | `aculta_forum` consulta o access da rota Core | link render array em `views_pre_render`, token visual `button-primary` já existente, cacheability do resultado | link estático que ignore Entity Create Access ou regra contextual |
| Ação de citar comentário | `aculta_forum` valida Comment e provider/contexto | link Core `comment.reply` com token neutro `button-secondary`; o tema traduz `data-portal-ui` | classes CSS, templates, markup de cartão ou acesso decidido pelo tema |
| Identidade, apelido e User | User/Profile + `aculta_user` | Author Card API a definir | consulta de dados privados e cartão próprio do Forum |
| Avatar/foto | User picture + `aculta_user` + `aculta_image` | render autorizado por finalidade `avatar` | processar/excluir arquivo fora de `aculta_image` |
| Autoria histórica | entidade `aculta_legado_autor` + `aculta_user` | `LegacyAuthorManager`, campo existente, memorial coletivo | conta User, perfil, login ou cartão independente |
| Favorito/seguir/denúncia | Flag | flags existentes; denúncia do Fórum usa permissão contextual, `HistoricForumFlagAccessHooks` e fila `aculta_user` específica do Growroom | armazenamento paralelo de ações; pôr acervo histórico ou outro contexto na fila pública |
| Notificação | `aculta_messenger` e seu contrato de avisos | evento/referência e chamada ao serviço dono | fila/tabela/email próprios no Forum |
| Conquistas | `aculta_game` avalia somente participações atuais do Fórum ACULTA; concessões e catálogo têm fonte única no `aculta_user` | trigger existente `forum_comment` ao publicar Comment do tópico público novo | bundle Drupal Forum legado, storage, contagem, XP, ranking ou concessão próprios no Forum |
| Cursos, atividades, matrícula | LMS + Group | adapter opcional `aculta_forum_lms` | autorização por role global ou cópia da matrícula |
| Galeria e anexos | Gallery/Media/File/PDF viewer + `aculta_image` para imagens | referências com access revalidado | cópia/download público que contorne os donos |
| Busca | Search API + Views | datasource e processor por contexto aprovados | índice público com conteúdo LMS/Wiki não autorizado |
| Dados preparados | `aculta_forum` e `aculta_user` conforme domínio | render arrays neutros com cacheability | tema consultando Entity API ou aplicando access |
| Markup, SDC, Bootstrap e CSS | ACULTA420 | props/slots neutros, tokens semânticos | CSS, classes de design ou templates no módulo |

## Cartões

`aculta_user` é dono único de resolução de identidade e view-models para cartões
de autor, participante e perfil. `aculta_forum` solicita a variante e informa
apenas contexto/ação; não define estrutura ou consulta perfil. ACULTA420 seleciona
o SDC, layout lateral/empilhado, light/dark e acessibilidade. Ver
`aculta_user/docs/AUTHOR-CARDS.md` e
`docs/portal/FRONTEIRA-MODULO-TEMA.md`.

## Eventos e confiança

Evento de discussão carrega IDs/referências mínimas. No momento de exibir ou
entregar, o consumidor recarrega a entidade e revalida publicação, access,
membership, preferências e purpose. Payload enfileirado não é autorização.
