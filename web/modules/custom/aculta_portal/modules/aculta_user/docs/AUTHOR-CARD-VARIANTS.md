# Variantes de cartão de autoria

Contrato de autoria. As variantes compartilham nome/link/cache/policy em
`aculta_user`, mas identificam consumidores diferentes. A apresentação comum
fica no SDC `aculta420:author-card`; metadados de domínio só entram quando um
provider autorizado os fornecer. Uma variante nunca amplia acesso por si só.

| Variante | Finalidade/dados candidatos | Estado |
| --- | --- | --- |
| `profile_full` | Perfil público completo; itens visíveis via `PublicProfilePolicy` | Perfil e rota existentes preservados; SDC ainda não substitui a página toda. |
| `profile_compact` | Nome público, avatar se policy permite, link canônico | Variante aceita pelo resolver; layout compacto no SDC. |
| `author_editorial` | Autoria de publicação; papel editorial vem do consumidor Revista | Variante aceita; caixa editorial existente continua ativa. |
| `author_comment` | Autoria de comentário interno da Revista, avatar conforme policy, ações fornecidas pelo host | `DiscussionCommentAuthorHooks` seleciona para Comments compartilhados em `article` e `blog`; comentários de Fórum não usam esta variante. |
| `author_forum` | Autor de tópico/resposta pública, avatar conforme policy, ano da conta em perfil público, ações fornecidas pelo host | `PublicForumTopicAuthorHooks` integra tópicos `aculta_forum_topic`; `AuthorCardVariantResolver` usa a mesma variante nas respostas hospedadas nesse bundle. Forum contrib permanece em migração separada. |
| `author_forum_stacked` | Mesma identidade de `author_forum`, empilhada acima da mensagem | Mesmo view-model; variante visual implementada no SDC. |
| `author_lms` | Identidade e papel contextual visível, somente após Group/membership access | O resolver identifica o host planejado `aculta_lms_topic`; adapter, dados pedagógicos e acesso Group continuam pendentes. |
| `author_wiki` | Identidade de participante da discussão do verbete | O resolver identifica `wiki_entry`; a integração Comment e a navegação da Wiki futura continuam pendentes. |
| `author_growroom_historico` | Nome de acervo e identificação da origem, sem User, login, data de conta, perfil individual ou ações de conta | `AuthorCardBuilder::buildLegacy()` prepara nome, rótulo do acervo e cache da entidade histórica; comentários compartilhados e tópicos importados o consomem. |
| `author_minimal` | Menção/aviso/prévia: nome público mínimo e link apenas se permitido | Identidade compartilhada aceita pelo resolver e builder. |

O ACULTA420 renderiza as variantes pelo SDC experimental `content/author-card`.
`AuthorIdentityResolver` aceita as variantes User documentadas, exceto
`author_growroom_historico`; `author_forum_stacked` reutiliza a mesma identidade e muda só
o layout. A variante histórica está disponível visualmente, mas ainda depende
de integração contextual segura. Sem JS, conteúdo e ações renderizadas continuam acessíveis.

`AuthorCardVariantResolver` recebe a entidade hospedeira do Comment e só escolhe
uma variante para os bundles reconhecidos: `aculta_forum_topic` →
`author_forum`; `article`/`blog` → `author_comment`; `aculta_lms_topic` →
`author_lms`; `wiki_entry` → `author_wiki`. Bundle desconhecido não herda o
cartão de outro contexto. Essa seleção não decide acesso nem torna um host
visível.
