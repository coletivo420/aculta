# Relacionamentos entre membros

## Pesquisa upstream

O estudo foi feito sobre o release `friendship` 2.1.0, tag `12f00f2`, baixado
somente para leitura em checkout temporário. Foram lidos todos os arquivos
PHP/YAML do pacote, incluindo serviço, entidade, controller, formulário de
configuração, plugins Views, hooks, permissões, rotas e README. O release
declara Drupal `^9.5 || ^10 || ^11`, porém o projeto informa que não é coberto
pela política de advisories de segurança do Drupal.

### O que serve de referência

- Ações conceituais: solicitar/seguir, aceitar, cancelar, remover e consultar
  amizade/relacionamento existente.
- O estado precisa distinguir pedido pendente de amizade aceita.
- A tela de perfil pode oferecer um ponto de entrada, e a conta deve listar
  pedidos recebidos.

### Apresentação no perfil público

Para uma pessoa autenticada que abre `/u/<apelido>` de outro membro, a ação de
amizade fica no cabeçalho do cartão do perfil, ao lado da identidade. O botão
usa o mesmo contrato de apresentação de “Ver Perfil” na visão geral da conta.
O tema só estiliza o formulário; a solicitação e suas transições continuam no
`FriendshipActionForm` e no `FriendshipManager` do `aculta_user`.

### O que não será copiado

- O banco upstream mantém dois registros espelhados por relação; no ACULTA um
  registro contém o par ordenado de UIDs, solicitante, destinatário e estado.
- O serviço upstream injeta `database` e lê diretamente a tabela `friendship`.
  O ACULTA usa Entity API e `EntityQuery` com `accessCheck()` explícito.
- A entidade upstream usa anotação e `\Drupal::currentUser()` em `preCreate()`.
  O código ACULTA segue atributos de entidade suportados pelo Core instalado e
  injeta dependências.
- Ações upstream usam links GET (com token CSRF), AJAX e um controller que
  altera dados. O ACULTA usa Form API/POST, validação de participante no serviço
  e continua funcional sem JavaScript.
- Os plugins de Views que consultam diretamente a tabela não serão trazidos sem
  uma tela concreta que precise deles.
- Textos configuráveis para botões, hook procedural com service locator e o
  comando AJAX personalizado não atendem ao escopo atual.

## Modelo ACULTA

`AcultaFriendship` (`aculta_friendship`) representa um único par em ordem
canônica. O ID string `uid-menor:uid-maior` (até 24 caracteres) é a chave
primária e impede duplicidade por par no storage. Campos e machine names respeitam o limite do Portal de 32 caracteres.
Estados: `pending`, `accepted`, `declined`, `cancelled` e `removed`. Ao aceitar,
ambas as pessoas são amigas. Um novo pedido só pode reutilizar um registro
encerrado; pedido repetido do mesmo solicitante é idempotente e um pedido
simultâneo inverso precisa ser resolvido explicitamente pelo destinatário.

| Código | Responsabilidade |
| --- | --- |
| `Entity/AcultaFriendshipInterface` | Interface da entidade e getters de par/estado |
| `Entity/AcultaFriendship` | Content entity; storage e campos Drupal |
| `Entity/AcultaFriendshipAccessControlHandler` | `view`/`update` limitado aos dois participantes |
| `Social/FriendshipStatus` | Estados válidos do fluxo |
| `Social/FriendshipManagerInterface` | API pública para outros submódulos |
| `Social/FriendshipManager` | Criar/transicionar e consultar relações usando DI/Entity API/lock |

A API pública é `aculta_user.friendship_manager`. `FriendshipActionForm` integra
a solicitação/cancelamento/remoção ao perfil público; `FriendRequestsForm`, no
Messenger, permite aceitar/recusar na aba Mensagens de `/avisos-mensagens/mensagens`. O contador
multidomínio inclui pedidos recebidos pendentes. A entidade é instalada em sites
existentes pelo update `aculta_user_update_10002()`. O `aculta_messenger` pode
consultar amizade para aplicar a política de início de conversa; não acessa a
tabela, não cria entidades diretamente e não duplica o estado.

## Segurança e limites

- Conta precisa estar autenticada e ativa; solicitação para si próprio é
  rejeitada.
- Toda relação é privada para seus dois participantes. Acesso administrativo
  exige permissão restrita própria.
- Queries internas com `accessCheck(FALSE)` são limitadas a UID(s) fornecidos
  pelo serviço; entidades são verificadas com o access handler depois do load.
- Ações só podem ser executadas pelo participante autorizado à transição.
- Lock Core serializa solicitações para o par e reduz duplicidade concorrente.
- Flood API do Core limita solicitações novas a dez por usuário por hora.
- Preferência `aculta_messenger/message_privacy` é aplicada pelo formulário nativo Private Message
  apenas ao criar thread nova; bloqueios nativos continuam prioritários.
- Exclusão de conta apaga as relações privadas pelo hook OOP `user_delete`.
  `module_preuninstall` bloqueia a remoção do `aculta_user` enquanto houver
  registros, evitando perda silenciosa; exportação/retention permanece política
 operacional a definir antes do RC.
- Notificações devem ser emitidas pelo Messenger após transição válida e
  deduplicadas; a entidade de amizade continua sendo a fonte do estado.

### Integração de avisos

`FriendshipManager` continua dono do workflow e publica `FriendshipChangedEvent`
após pedido ou aceite válido. O adapter `FriendshipNoticeEventProducer` do
`aculta_messenger` traduz essa transição para `MessengerNoticeEvent`; o consumer
recarrega a amizade por UUID e revalida estado e destinatário antes de criar o
`Message` persistente e enfileirar o e-mail mínimo. User não depende do
Messenger, e a integração não cria conversa privada nem storage de aviso
paralelo. A cobertura atual é Unit; a prova Kernel do pipeline completo segue
pendente.

## Fontes

- [Friendship: visão geral e avisos de segurança](https://www.drupal.org/project/friendship)
- [Friendship 2.1.0: release notes](https://www.drupal.org/project/friendship/releases/2.1.0)
- [Documentação Friendship](https://www.drupal.org/docs/extending-drupal/contributed-modules/contributed-module-documentation/friendship)
- [User Relationships: status e versões suportadas](https://www.drupal.org/project/user_relationships)
- [Flag: API de flags para entidades](https://www.drupal.org/project/flag)
- [Drupal 11 Entity API](https://api.drupal.org/api/drupal/core%21lib%21Drupal%21Core%21Entity%21entity.api.php/group/entity_api/11.x)
- [ContentEntityType attribute](https://api.drupal.org/api/drupal/core%21lib%21Drupal%21Core%21Entity%21Attribute%21ContentEntityType.php/class/ContentEntityType/11.x)
