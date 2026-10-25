# Port do Author Pane do Advanced Forum

O Author Pane histórico é referência de capacidades para cartões `author_forum`
e `author_forum_stacked`; não é fonte de código executável. A identidade e os
dados pertencem a `aculta_user`, o layout ao ACULTA420.

| Elemento histórico | Tratamento ACULTA | Limite |
| --- | --- | --- |
| Nome de usuário / nome público | Reusar apelido resolvido pela policy e `PublicProfileLink` | Perfil privado continua conforme policy; sem link forçado. |
| Avatar/foto | Reusar `user_picture` e `aculta_image` | Não expor imagem privada; autoria legada usa avatar do acervo com origem anotada. |
| Online e último acesso | Excluir por padrão | O dado histórico derivava de atividade recente da conta; não é necessário e pode revelar hábitos/presença. Só decisão explícita pode reintroduzir com policy adequada. |
| Título/role do participante | Mostrar só função de contexto aprovada (por exemplo, educador na discussão daquele curso) | Nunca despejar roles globais/permissões. |
| Data de entrada/registro | `author_forum`/`author_forum_stacked` mostram somente o ano de criação da conta, com o rótulo “Conta ACULTA desde”; somente para perfil público | Perfis privados não revelam data. `author_growroom_historico` não recebe data: o acervo não informa ingresso original confiável. Cache depende da conta/perfil; UTC evita variação de fuso. |
| Estatísticas: tópicos/respostas | A aba privada de atividade mostra, em cada tópico público criado pela própria pessoa, a contagem publicada mantida pelo Comment Core. Estatísticas agregadas por participante em cartões seguem fora de escopo até definição própria de métrica/cache | Ignorar conteúdo sem acesso e excluir histórico importado das métricas contemporâneas. |
| Badges e pontos | Reusar apenas conquistas com visibilidade pública autorizada | Pontos Userpoints do template histórico não são portados; sem leaderboard/ranking. |
| Perfil público | Link canônico atual `/u/{apelido}` | Sem UID e sem URL hardcoded fora de DomainPurposeManager. |
| Links sociais/contato | Reusar opt-in `SocialNetworks` e Friendship/Messenger APIs | Não expor email; Messenger só para conta atual elegível. |
| IP e controles rápidos de administração | Excluir do cartão; manter moderação em interfaces administrativas existentes e autorizadas | Nunca incluir IP, Fasttoggle ou operação privilegiada no cartão de participante. |
| Assinatura | Não portar até definir campo, formato, policy de acesso e moderação | Template histórico renderizava assinatura; ACULTA não executa markup livre de perfil. |
| Layout lateral/empilhado | Implementar como apresentação responsiva do mesmo componente/view-model | Tema SDC/Bootstrap; preferência por adaptação CSS sem duplicar dados. |
| Ações responder/citar/editar | Receber ações Drupal já autorizadas | Form/Entity Access valida no submit; não confiar em esconder link. |

O inventário upstream de categorias/listagens/thread e estilos está em
[`ADVANCED-FORUM-PORT.md`](../../aculta_forum/docs/ADVANCED-FORUM-PORT.md).
Implementação inicial do cartão `author_forum`/`author_comment`, variante
empilhada e SDC visual foi criada na `0.1.0-dev.3`. A data de conta pública foi
implementada em `aculta_user 0.4.0-dev.40`; estatísticas agregadas, badges,
ações sociais e assinatura seguem adiadas pelas políticas acima. O
consumidor do Fórum contrib ainda precisa ser substituído pelo motor novo; o
Runtime e a inspeção visual em navegador permanecem DEFERRED.
