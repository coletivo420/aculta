# Inventário de integrações

Documento de referência. Dono: `aculta_messenger` no ACULTA Portal. Revisão:
2026-10-14. Versões publicadas foram comparadas com os pacotes Composer
instalados nesta branch.

## Estado do repositório

| Pacote | `composer.lock` | Código em `web/modules/contrib` | Conclusão |
| --- | --- | --- | --- |
| `drupal/private_message` | 3.0.7 | 3.0.7 | Inspecionado |
| `drupal/message` | 1.9.0 | 1.9.0 | Inspecionado |
| `drupal/message_notify` | 1.5.0 | 1.5.0 | Inspecionado |
| `drupal/message_subscribe` | 2.1.0 | 2.1.0 | Inspecionado |
| `drupal/site_alert` | 1.4.0 | 1.4.0 | Inspecionado |

Base encontrada: Drupal Core 11.4.8 no lock; `composer.json` declara PHP >=8.3.
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
conversa. O assunto é fixo e genérico. Até a integração das rotas `/mensagens`,
o link aponta para a raiz do purpose `account`. Falhas de entrega retornadas
pelo notifier podem ser tentadas até três vezes; runtime no Homelab ainda é
necessário para verificar o transporte existente.

Este inventário é estático. Ainda não comprova instalação Drupal, habilitação,
cache/access de runtime ou compatibilidade em PHP 8.5.

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

## Fontes públicas consultadas

- [Private Message 3.0.7](https://www.drupal.org/project/private_message/releases/3.0.7) e [guia de instalação](https://www.drupal.org/docs/contributed-modules/private-message/installing-the-module).
- [Message](https://www.drupal.org/project/message), [Message Notify](https://www.drupal.org/project/message_notify) e [Message Notify 8.x-1.5](https://www.drupal.org/project/message_notify/releases/8.x-1.5).
- [Message Subscribe](https://www.drupal.org/project/message_subscribe) e [releases](https://www.drupal.org/project/message_subscribe/releases).
- [Site Alert](https://www.drupal.org/project/site_alert), [releases](https://www.drupal.org/project/site_alert/releases) e [issue queue](https://www.drupal.org/project/issues/site_alert).
- [Issue queue de Private Message](https://www.drupal.org/project/issues/private_message?categories=All&status=All), para alertas de compatibilidade atuais.
- [Drupal Core QueueInterface 11.x](https://api.drupal.org/api/drupal/core%21lib%21Drupal%21Core%21Queue%21QueueInterface.php/interface/QueueInterface/11.x), que define semântica de processamento pelo menos uma vez.
- [Drupal Core HookCollectorPass 11.x](https://api.drupal.org/api/drupal/core%21lib%21Drupal%21Core%21Hook%21HookCollectorPass.php/class/HookCollectorPass/11.x), que registra hooks OOP em classes `Hook` como serviços autowired.
