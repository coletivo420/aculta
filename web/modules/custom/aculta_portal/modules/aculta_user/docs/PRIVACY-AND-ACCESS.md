# Privacidade e acesso dos cartões

`aculta_user` prepara apenas atributos cuja exibição pública esteja permitida.
Quem chama um cartão não pode elevar visibilidade. O acesso é revalidado para
cada campo/ação e seus resultados contribuem cacheability.

| Dado/ação | Regra documental |
| --- | --- |
| Apelido/nome público | Usar Account/Profile atual e rota pública canônica; conta privada segue `PublicProfilePolicy`. |
| Foto/avatar | `user_picture` via `aculta_image`; anexo privado permanece inacessível e URLs não são inferidas pelo tema. |
| Bio e redes | Só conforme visibilidade escolhida/public policy; links externos são validados por serviço. |
| Email, endereço, documentos | Nunca integrar a Author Card pública. |
| Amizade/mensagem | Só conta atual e operação permitida; legado não recebe conta, friendship ou Messenger. |
| Role/papel | Não listar roles globais; contexto LMS só informa função se access e pertinência estiverem aprovados. |
| Curso/turma/atividade | Nunca revelar membership, nota, resposta, grupo, trabalho ou educador fora do contexto autorizado. |
| Estatística | Excluir dados privados e observar access antes de contar. Valores agregados podem reidentificar grupos pequenos; avaliar antes de exposição pública. |
| Conquistas | Respeitar escolha de exibição em `aculta_user`/`aculta_game`; não conceder durante visualização/importação. |
| Autoria histórica | Nome preservado, origem/acervo e memorial coletivo; sem login/identificação de conta atual por coincidência. |
| Cache | Mesclar cache tags/contexts/max-age dos dados, policy e access; preferir max-age conservador quando a política for dinâmica. |

Cobertura mínima de testes: perfil público/privado, apelido alterado, usuário
bloqueado, avatar removido/substituído, amizade indisponível, conta sem perfil,
autoria legada e cache entre dois usuários/grupos. Desenho geral: [Cacheability
de render arrays](https://www.drupal.org/docs/drupal-apis/render-api/cacheability-of-render-arrays).
