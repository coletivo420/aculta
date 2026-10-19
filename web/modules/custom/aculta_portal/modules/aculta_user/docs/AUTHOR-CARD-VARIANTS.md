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
| `author_comment` | Autor de comentário, avatar conforme policy, ações fornecidas pelo host | Builder e SDC compartilhados; ainda não integrado às publicações. |
| `author_forum` | Autor de tópico/resposta, avatar conforme policy, ações fornecidas pelo host | Builder e SDC compartilhados; ainda não integrado ao Fórum contrib. |
| `author_forum_stacked` | Mesma identidade de `author_forum`, empilhada acima da mensagem | Mesmo view-model; variante visual implementada no SDC. |
| `author_lms` | Identidade e papel contextual visível, somente após Group/membership access | Preparada no contrato; sem dados pedagógicos ou implementação contextual nesta fase. |
| `author_wiki` | Identidade de participante da discussão do verbete | Preparada no contrato; Wiki futura continua sem alterações. |
| `author_growroom_historico` | Nome de acervo, sem User, login, perfil individual ou ações de conta | SDC/story preparados; builder aguarda consumidor autorizado do conteúdo legado. |
| `author_minimal` | Menção/aviso/prévia: nome público mínimo e link apenas se permitido | Identidade compartilhada aceita pelo resolver e builder. |

O ACULTA420 renderiza as variantes pelo SDC experimental `content/author-card`.
`AuthorIdentityResolver` aceita as variantes User documentadas, exceto
`author_growroom_historico`; `author_forum_stacked` reutiliza a mesma identidade e muda só
o layout. A variante histórica está disponível visualmente, mas ainda depende
de integração contextual segura. Sem JS, conteúdo e ações renderizadas continuam acessíveis.
