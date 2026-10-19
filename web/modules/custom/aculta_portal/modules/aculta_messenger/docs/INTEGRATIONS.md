# Inventário de integrações

Documento de referência. Dono: `aculta_messenger` no ACULTA Portal. Revisão:
2026-10-14. Versões publicadas foram comparadas com os pacotes Composer
instalados nesta branch.

## Estado do repositório

Na prévia da barra, as threads são agrupadas pelo outro membro da conversa e
ordenadas pela atualização mais recente. O cabeçalho de cada caixa abre a
thread mais recente daquele remetente; as threads individuais permanecem
ordenadas dentro da caixa e mantêm o link nativo do Private Message.

### Pesquisa de pedidos de amizade (0.1.1)

O `composer.lock` desta branch contém Drupal Core 11.4.10, Flag 5.1.1, Private
Message 3.0.7, Message 1.9.0 e Message Notify 1.5.0; não contém Friendship,
User Relationships nem Open Social. O código instalado do Flag foi revisado
no checkout local existente: `FlagServiceInterface` oferece operações públicas
para criar, consultar e remover Flagging entities em entidades User. Flag tem
release estável para Drupal 11 e é coberto pela política de advisories.

O módulo Friendship 2.1.0 declara suporte a Drupal 11 e cobre follow, aceitar
e remover amizade, mas o projeto declara explicitamente que não é coberto pela
política de segurança Drupal. Sua documentação recomenda Views próprios para
exibir seguidores/amizades e não descreve uma central de pedidos/notificações.
Na tag 2.1.0 revisada localmente, as rotas são links GET com verificação de
token CSRF, o serviço escreve sua própria entidade de relacionamento e o
pacote não contém testes automatizados. O handler de acesso e os fluxos de
notificação não oferecem, sem adaptação, os contratos privados e de mensagens
do ACULTA. O suporte Drupal 11 declarado não elimina esses pontos.

User Relationships é um projeto legado focado em Drupal 7, sem release
adequado ao baseline Drupal 11. Não é candidato. Flag, por sua vez, fornece
armazenamento de flaggings e integração Views, mas não possui workflow nativo
de solicitação, aceite, cancelamento, recusa ou inbox de pedidos. Representar
amizade apenas como Flag seria ambíguo: não há estado explícito de pedido e
aceite, nem transições atômicas definidas pelo módulo.

**Decisão para 0.1.1:** não instalar Friendship nem User Relationships e não
simular workflow de amizade com flags. A capacidade social será implementada
em `aculta_user`, que já é dono dos perfis e regras individuais de conta. O
Messenger consumirá seu serviço público; Message/Message Notify continuam
responsáveis por avisos, Private Message por conversas e User Data pela
preferência de comunicação. Essa é uma lacuna funcional real, não uma nova
engine de mensagens. Nenhum pacote Composer novo é necessário.

O código Friendship serve como referência para a máquina de estados e os casos
de uso `follow`, `accept`, `remove`, `isFriend` e `hasRelationship`, não como
cópia literal. A versão ACULTA terá um registro por par com estados explícitos,
DI, Entity API e EntityQuery com `accessCheck()` declarado; não usará service
locator nem SQL direto. Ações mutáveis serão Form API/POST com validação CSRF,
acesso por participante e transições verificadas no serviço. Solicitações
validam contas ativas, self-request, duplicidade e permissões; o bloqueio
bilateral é aplicado pelo formulário de novas conversas do Private Message. Recusa/cancelamento não cria amizade; a autorização é revalidada
ao consultar ou entregar notificações.

O destinatário escolhe `all` (Todos os membros) ou `friends` (Somente amizades confirmadas) em Minha Conta. O Messenger guarda a preferência em User Data com proprietário `aculta_messenger`, sem criar campo ou armazenamento paralelo. A regra
restringe somente o início de novas conversas; respostas em threads existentes
continuam permitidas. Administradores e editores de conteúdo podem iniciar
novas conversas apesar da preferência do destinatário, mas o bloqueio
individual nativo do Private Message permanece obrigatório inclusive para
esses papéis. A configuração de papéis usa papéis existentes e não concede
permissões Drupal novas.

Fontes oficiais consultadas: [Friendship](https://www.drupal.org/project/friendship),
[documentação Friendship](https://www.drupal.org/docs/extending-drupal/contributed-modules/contributed-module-documentation/friendship),
[Flag](https://www.drupal.org/project/flag),
[User Relationships (arquivo comunitário)](https://www.drupal.org/docs/extending-drupal/contributed-modules/contributed-module-archive/contrib-modules-for-building-the-site-functionality/social-networking-and-collaboration/community-building-and-social-networking-15),
[Private Message 3.0.7](https://www.drupal.org/project/private_message/releases/3.0.7),
[Message](https://www.drupal.org/project/message) e
[Message Notify](https://www.drupal.org/project/message_notify),
[Drupal Entity API 11.x](https://api.drupal.org/api/drupal/core%21lib%21Drupal%21Core%21Entity%21entity.api.php/group/entity_api/11.x)
e [ContentEntityType attribute](https://api.drupal.org/api/drupal/core%21lib%21Drupal%21Core%21Entity%21Attribute%21ContentEntityType.php/class/ContentEntityType/11.x).

O modal de mensagens consulta o estado nativo de leitura por conversa do
Private Message, relativo ao membro atual. A rota de diálogo do ACULTA Messenger
renderiza a entidade da conversa e reutiliza o formulário `private_message` do
módulo; se a configuração de exibição não trouxer esse componente, inclui o
mesmo Form API nativo como fallback. Acesso e envio continuam sob as regras do
Private Message. O modal de avisos mostra Site Alerts ativos e o
contador representa apenas a quantidade ativa: Site Alert não fornece recibo
de leitura individual. Pedidos de amizade recebidos não têm tela dedicada desde a remoção de `/mensagens/notificacoes` (DT-O15); o contador da barra continua incluindo esses pedidos. A preferência é validada no formulário nativo do Private Message antes da criação de thread; threads existentes permanecem aptas a resposta.

| Pacote | `composer.lock` | Código em `web/modules/contrib` | Conclusão |
| --- | --- | --- | --- |
| `drupal/private_message` | 3.0.7 | 3.0.7 | Inspecionado |
| `drupal/message` | 1.9.0 | 1.9.0 | Inspecionado |
| `drupal/message_notify` | 1.5.0 | 1.5.0 | Inspecionado |
| `drupal/message_subscribe` | 2.1.0 | 2.1.0 | Inspecionado |
| `drupal/site_alert` | 1.4.0 | 1.4.0 | Inspecionado |

Base encontrada: Drupal Core 11.4.10 no lock; `composer.json` declara PHP >=8.3.
Este ambiente executa PHP 8.4.26. Não há PHP 8.5 para validação local. Os
metadados Composer do projeto declaram plataforma PHP >=8.3; isso não substitui
lint, testes ou avaliação das dependências em PHP 8.5.

## Releases estáveis Drupal 11 identificados

| Projeto Drupal.org | Versão estável consultada | Drupal 11 declarado | Ponto de atenção |
| --- | --- | --- | --- |
| [Private Message](https://www.drupal.org/project/private_message) | 3.0.7 (`^3.0`) | Sim | Issues recentes relatam `TypeError` com valor nulo em D11/PHP 8.4; reproduzir/revisar antes de adotar. 4.x consultado é alpha, não base da primeira implementação. |
| [Message](https://www.drupal.org/project/message) | 8.x-1.9 (`^1.9`) | Sim | Projeto indicado como manutenção mínima; verificar issue queue e APIs usadas. |
| [Message Notify](https://www.drupal.org/project/message_notify) | 8.x-1.5 (`^1.5`) | Sim | O metadata instalado requer Core e Message. A página lista Entity API/CTools, mas estes não aparecem como requirements do pacote efetivamente resolvido. |
| [Message Subscribe](https://www.drupal.org/project/message_subscribe) | 2.1.0 (`^2.1`) | Sim | Requer Message, Message Notify e Flag; a página declara busca por co-maintainer. |
| [Site Alert](https://www.drupal.org/project/site_alert) | 8.x-1.4 (`^1.4`) | Sim | Entidade com agenda/severidade e bloco; documentação descreve renderização AJAX sem cache. Conferir acesso/cache e integração de tema. |

Compatibilidade declarada significa que o release anuncia suporte ao Drupal 11.
Não é uma validação de execução deste projeto nem uma afirmação de compatibilidade
formal com PHP 8.5.

## Composer e módulos requeridos

Dry-run real: **5 installs, 0 updates, 0 removals**. `composer.json` declara
somente esses cinco pacotes; o lock agora trava os releases listados acima.
O grafo também resolveu `drupal/flag` 5.1.1 para `message_subscribe`;
`datetime_range` e `options` são módulos do Core exigidos por Site Alert.
Nenhum deles foi incluído como requisito direto do projeto ACULTA.

Metadata Composer: Private Message requer Core, Message, Message Notify e
PHP >=8.1; Message Notify requer Core e Message; Message Subscribe requer Core,
Flag ^4/5, Message e Message Notify; Message e Site Alert declaram faixas Core
compatíveis com Drupal 11. Os `.info.yml` instalados confirmam requisitos Drupal
adicionais `text`, `flag`, `datetime_range` e `options`. A página de Message
Notify lista Entity API e CTools, porém o metadata Composer e `.info.yml`
instalados não declaram esses requisitos; não foram adicionados por causa da
discrepância documental.

O `aculta_messenger.info.yml` depende dos cinco módulos porque as cinco
integrações compõem a proposta do submódulo. Ativar/desinstalar o Messenger não
desinstala esses contrib; a lista de dependências do Drupal protege o ciclo.

## APIs confirmadas no código instalado

| Módulo | Entidades e fields | Services e eventos | Rotas e permissões | Configuração e access |
| --- | --- | --- | --- | --- |
| Private Message | `private_message` (owner, message, created); `private_message_thread` (members, messages, history); `private_message_ban` (owner, target) | `private_message.service`, `private_message.thread_manager`, `private_message.ban_manager`, `private_message.mapper`; `hook_private_message_new_message` | `/private-messages`, `/private-message/create`, `/private-messages/{private_message_thread}`, `/private-message/{private_message}`; `use private messaging system` + `access user profiles`; também permissões de admin, ban e delete | settings, entity displays/forms e view modes. Access handlers próprios; inbox `no_cache`. `private_message_notify` é submódulo opcional para e-mail |
| Message | Content entity `message`; config entity `message_template` | `message.purge_orchestrator`, `plugin.manager.message.purge` | CRUD de template em `/admin/structure/message`, settings `/admin/config/message`; `administer message templates`, `administer messages`, `overview messages` | settings, view mode `full`, ação `message_delete_action`; tipos de mensagem são config entities |
| Message Notify | Sem entity própria | `message_notify.sender` (`MessageNotifyInterface::send`), `plugin.message_notify.notifier.manager`; plugins de notifier, incluindo email | Sem rotas ou permissions próprias no módulo base | view modes `message.mail_body` e `message.mail_subject`; entrega usa Drupal Mail |
| Message Subscribe | Sem entity própria; subscriptions são Flagging entities | `message_subscribe.subscribers` (`sendMessage`, `getSubscribers`, `getFlags`); hooks de seleção/alteração de destinatários | `/admin/config/message/message-subscribe`; `administer message subscribe`; `message_subscribe_ui` e `message_subscribe_email` são opcionais | `message_subscribe.settings`: `use_queue`, `notify_own_actions`, `default_notifiers`, `range`; serviço tem opção `entity access` |
| Site Alert | `site_alert` content entity (id, uuid, label, active, severity, message, scheduling) | `site_alert.get_alerts`, `site_alert.cli_commands`, `cache_context.active_site_alerts` | Collection/add/edit/delete em `/admin/config/system/site-alerts`; AJAX `/ajax/site_alert`; permissions `administer site alert`, `add site alerts`, `update site alerts`, `delete site alerts` | Sem config install própria; fields severity low/medium/high, message text_long, scheduling daterange |

Os manifests e código foram lidos em `web/modules/contrib/{private_message,
message,message_notify,message_subscribe,site_alert}`. A inbox contrib fica na
rota inglesa `/private-messages`; a central ACULTA deve oferecer navegação em
português e manter formulários/ações nativos conforme o teste de path e purpose.
Endereços públicos em inglês ainda precisam ser cobertos pelo contrato de slugs
antes de expor o fluxo.

O submódulo opcional `private_message_notify` também entrega email via
`message_notify.sender`, mas seu template padrão inclui o corpo da mensagem
privada. O lifecycle guard do Messenger bloqueia a ativação simultânea para
evitar exposição e dupla entrega.

O envio externo permanece no transporte de e-mail já instalado pelo projeto:
Drupal Mail com Symfony Mailer/Mailer Plus. Message Notify é responsável pela
preparação/entrega lógica; não há configuração SMTP ou transportador próprio do
Messenger. O `private_message_notify` opcional é um possível chamador do mesmo
serviço, portanto deve haver um único caminho ativo para notificar cada evento.

A configuração exportada atual não concede `use private messaging system` nem
`access user profiles` a papéis. A ativação do Messenger não deve conceder essas
permissões automaticamente. A distribuição pretendida dessas permissões deve
ser revisada antes de habilitar envio por membros; até lá, as rotas nativas
continuam protegidas pelos requisitos existentes.

O Messenger altera somente o Form API `private_message_add_form` com um hook
OOP `#[Hook('form_alter')]` e um callback de serviço resolvido pelo
`CallableResolver`. O callback consulta e registra no Core Flood depois das
validações nativas; não substitui o form object, o salvamento nem o
AJAX/fallback do Private Message. Um segundo callback consulta o serviço nativo
de bloqueio nos dois sentidos antes do submit, sem informar ao remetente a
existência ou a identidade de um bloqueador.

O hook `private_message_new_message` grava um item no Queue API do Core. O
worker resolve a thread após o salvamento, revalida membership/access, status,
permissões e bloqueio, registra evento como Message e envia aviso com
`message_notify.sender`. Uma referência Message → Private Message e lock por
evento/destinatário evitam criar notificações duplicadas. O template contém o
aviso e a URL da página central da conta, sem corpo, assunto ou trecho da
conversa. O assunto é fixo e genérico. O link resolve a central `/mensagens` no
purpose `account`. Falhas de entrega retornadas pelo notifier podem ser
tentadas até três vezes; runtime no Homelab ainda é necessário para verificar o
transporte existente.

Este inventário é estático. Ainda não comprova instalação Drupal, habilitação,
cache/access de runtime ou compatibilidade em PHP 8.5.

## Barra multidomínio: atualização sob demanda

O ACULTA420 mantém a apresentação da gaveta: shell Bootstrap compartilhado com
o carrinho, ícones, badge e estados visuais. Este módulo fornece os endpoints
same-origin e monta o fragmento a partir das entidades/Views com as regras de
acesso do usuário atual. Ao abrir uma gaveta, o tema faz uma requisição GET com
credenciais de sessão, insere o fragmento e anexa os behaviors Drupal para
links como os controles de leitura. Fechar interrompe uma requisição pendente.
As respostas devolvem os contadores atuais nos cabeçalhos; o tema atualiza os
badges e os rótulos acessíveis junto com o conteúdo. Não há polling, novo
framework de AJAX nem caixa postal paralela. As respostas usam `no-store`; a
instalação real e o comportamento visual ainda exigem validação no Homelab.

### Assinaturas de tópicos do fórum (dev.5)

O caso implementado é a primeira publicação de Node `forum`, inclusive a
transição de não publicado para publicado. O Messenger reutiliza a flag
existente `seguir_topico` via `FlagServiceInterface`; não altera prefixo global
do Message Subscribe e não instala `message_subscribe_ui`. O hook OOP de seleção
retorna `DeliveryCandidate` para flaggings desse tópico. Message Subscribe volta
a filtrar contas ativas e `Node::access('view')` no dispatch. A assinatura não
concede acesso.

O evento usa a Queue API do Core. O worker confirma publicação atual, exige
alias público, resolve URL no purpose `forum` por `DomainPurposeManager` e chama
`message_subscribe.subscribers` com access habilitado e dispatch síncrono. O
notifier configurado usa o mailer instalado via Drupal Mail (Symfony
Mailer/Mailer Plus); o Messenger não configura SMTP/transporte. O Message de
evento registra Node/revisão, estado e tentativas; lock e chave Node/revisão
deduplicam chamadas repetidas. `sendMessage()` não retorna resultado individual
da entrega, então falhas parciais e retry precisam de homologação controlada.

Group 3.3.5 está instalado, mas `gnode` não está habilitado. Não há integração
ativa de atividades Group a reutilizar nesta fase.

### Site Alert (dev.6)

O bloco nativo `site_alert_block` é instalado pelo Config Sync no tema ACULTA420, região
`highlighted`, com atualização a cada 300 segundos. A entidade fornece início,
término, conteúdo e severidade `low`/`medium`/`high`; as rotas CRUD e permissões
continuam as do módulo contrib. O módulo é sitewide e não possui segmentação por
Domain, portanto a instância se apresenta nos purposes que usam ACULTA420. A
região recebe os metadados de cache/contexto do bloco; visual no Design System
será conferido na integração de tema.

O tipo existente `article` não apresenta segmentação de acesso nem validade; o
workflow `editorial` existe, mas não atribui bundles (`entity_types: {}`). Não
foi criada entidade/Views de comunicado até haver um contrato verificável para
alvo e revisão editorial. A administração dos Site Alerts usa rotas `/admin`
protegidas pela política global de purpose `main`.

### Área Mensagens da Conta (dev.7)

Rotas ACULTA em português (`/mensagens` e subrotas) usam
`_aculta_domain_purpose: account`, `_user_is_logged_in` e as permissões nativas
de Private Message. Conversas redireciona via `TrustedRedirectResponse` para
`private_message.private_message_page` no purpose ACCOUNT. Um subscriber
classifica as rotas nativas `/private-message*` e `/private-messages*` como
ACCOUNT sem mudar caminhos, formulários ou handlers.

O Portal expõe `aculta_portal_account_sections_alter` e
`aculta_portal_account_parent_sections_alter`, seguindo o padrão dos alters de
abas opcionais de Configurações. Messenger acrescenta a seção somente se a
conta atual possui as três permissões necessárias; o rótulo inclui a contagem
nativa de threads não lidas. O Portal não depende do Messenger.

Notificações consulta apenas Messages de aviso privado, com `uid` igual à
sessão e estado `sent`. Message não declara access handler de ownership, então
a query usa `accessCheck(FALSE)` com filtro obrigatório de UID, bundle conhecido,
limite de resultados e cache privado/no-cache. Avisos fica vazio até haver
conteúdo persistente segmentado; Site Alert é temporário e sitewide, não uma
inbox individual.

## Fontes públicas consultadas

A preferência de e-mail é persistida pelo Private Message via Core `user.data`
(`private_message` / `receive_notification`). O formulário Messenger atualiza
esse valor sem cópia; o worker aplica o opt-out antes de chamar Message Notify.

O ACULTA420 fornece SDCs para listas, itens, composição, notificações, alertas,
contadores e estados. O Portal não referencia IDs dos componentes: o tema
seleciona os SDCs por hooks de apresentação e recebe slots renderizáveis. O
formulário e as entidades contrib permanecem intactos.

- [Private Message 3.0.7](https://www.drupal.org/project/private_message/releases/3.0.7) e [guia de instalação](https://www.drupal.org/docs/contributed-modules/private-message/installing-the-module).
- [Message](https://www.drupal.org/project/message), [Message Notify](https://www.drupal.org/project/message_notify) e [Message Notify 8.x-1.5](https://www.drupal.org/project/message_notify/releases/8.x-1.5).
- [Message Subscribe](https://www.drupal.org/project/message_subscribe) e [releases](https://www.drupal.org/project/message_subscribe/releases).
- [Site Alert](https://www.drupal.org/project/site_alert), [releases](https://www.drupal.org/project/site_alert/releases) e [issue queue](https://www.drupal.org/project/issues/site_alert).
- [Issue queue de Private Message](https://www.drupal.org/project/issues/private_message?categories=All&status=All), para alertas de compatibilidade atuais.
- [Drupal Core QueueInterface 11.x](https://api.drupal.org/api/drupal/core%21lib%21Drupal%21Core%21Queue%21QueueInterface.php/interface/QueueInterface/11.x), que define semântica de processamento pelo menos uma vez.
- [Drupal Core UserDataInterface 11.x](https://api.drupal.org/api/drupal/core%21modules%21user%21src%21UserDataInterface.php/interface/UserDataInterface/main), serviço usado para a preferência individual nativa.
- [Drupal SDC — documentação de props e slots](https://www.drupal.org/docs/develop/theming-drupal/using-single-directory-components/what-are-props-and-slots-in-drupal-sdc-theming), para a separação entre dados tipados e renderables.
- [Uso de SDC via render array](https://www.drupal.org/docs/develop/theming-drupal/using-single-directory-components/using-your-new-single-directory-component), conferido para `#type: component`, `#props` e `#slots` no Core atual.
- [Drupal Core HookCollectorPass 11.x](https://api.drupal.org/api/drupal/core%21lib%21Drupal%21Core%21Hook%21HookCollectorPass.php/class/HookCollectorPass/11.x), que registra hooks OOP em classes `Hook` como serviços autowired.
- [ModuleHandlerInterface::alter](https://api.drupal.org/api/drupal/core%21lib%21Drupal%21Core%21Extension%21ModuleHandlerInterface.php/function/ModuleHandlerInterface%3A%3Aalter/9), [RouteSubscriberBase 11.x](https://api.drupal.org/api/drupal/core%21lib%21Drupal%21Core%21Routing%21RouteSubscriberBase.php/class/RouteSubscriberBase/main), [Url::fromRoute 11.x](https://api.drupal.org/api/drupal/core%21lib%21Drupal%21Core%21Url.php/function/Url%3A%3AfromRoute/11.x) e [TrustedRedirectResponse 11.x](https://api.drupal.org/api/drupal/core%21lib%21Drupal%21Core%21Routing%21TrustedRedirectResponse.php/class/TrustedRedirectResponse/main), consultados para extensão do shell, purpose de rotas e URL ACCOUNT confiável.


## Integração ACULTA User (0.1.1)

`aculta_user.friendship_manager` é a API pública de relacionamento. `aculta_messenger` não acessa storage de amizade diretamente: consulta `areFriendsWith()` ao validar uma nova conversa e `getIncomingRequests()` ao compor notificações/contador. `FriendshipActionForm` é renderizado na página pública do perfil; `FriendRequestsForm` compõe a seção Notificações.

Ações são Form API com POST/CSRF, sem AJAX obrigatório. A autorização de estados é revalidada pelo manager sob lock e cada amizade tem uma chave primária string canônica (`uid-menor:uid-maior`), evitando linhas duplicadas por par. A preferência usa `user.data` na chave `aculta_messenger/message_privacy`; valores desconhecidos seguem o padrão permissivo `all`.
