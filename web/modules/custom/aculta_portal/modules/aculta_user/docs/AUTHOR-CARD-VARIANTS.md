# Variantes de cartão de autoria

Contrato de evolução. Todas as variantes compartilham identidade em
`aculta_user`. A resolução base aceita variantes de User e sempre retorna o
mesmo payload mínimo; dados, ações e renderizações específicas seguem pendentes.
Uma variante nunca amplia acesso por si só.

| Variante | Finalidade/dados candidatos | Estado |
| --- | --- | --- |
| `profile_full` | Perfil público completo: apelido, avatar, biografia e redes/ações somente se explicitamente visíveis | Evolução da página existente; preservar URL e privacy policy. |
| `profile_compact` | Nome público, avatar opcional, link permitido | Preparar para listas. |
| `author_editorial` | Autoria editorial e link para publicação/perfil | Reutilizar caixa do Magazine/SDC atual. |
| `author_comment` | Autor, avatar permitido, timestamp e ações de comentário; adequado a Magazine | Preparar; não assumir que `comment_forum` é tipo definitivo. |
| `author_forum` | Autor de tópico/resposta, data, perfil público e ações sociais elegíveis | Preparar para motor próprio. |
| `author_forum_stacked` | Mesmo view-model de `author_forum`, disposto acima da mensagem | Variação de apresentação no tema, nunca duplicação de serviço. |
| `author_lms` | Identidade e papel contextual visível (educador/participante) | Preparar; não revelar turma, matrícula, nota ou estatística privada. |
| `author_wiki` | Identidade de participante da conversa Wiki | Planejada; não introduzir autoria de revisão na página pública atual. |
| `author_legacy` | Nome histórico individual, indicação de acervo, avatar memorial e link coletivo | Reusar entidade/memorial existente; fora do resolver de User e sem ações de conta. |
| `author_minimal` | Menção/aviso/prévia: nome público mínimo e link apenas se permitido | Preparar para consumidores Messenger/Views. |

O ACULTA420 renderiza as variantes por SDCs existentes ou componentes a aprovar.
`AuthorIdentityResolver` aceita as variantes User documentadas, exceto
`author_legacy`, e prepara somente nome e link. A composição visual continua no
ACULTA420, em fase própria. Sem JS, o conteúdo e as ações essenciais continuam acessíveis.
