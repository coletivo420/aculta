# Segurança e privacidade

Documento de referência arquitetural. Dono: `aculta_messenger` no ACULTA
Portal. Revisão: 2026-10-24.

## Matriz de acesso e privacidade

| Ação/dado | Público autorizado | Decisão de access | Cache esperado |
| --- | --- | --- | --- |
| Ver caixa postal | Usuário autenticado com `use private messaging system` e `access user profiles` | Rota nativa do Private Message; as duas permissões são exigidas | Inbox upstream `no_cache`; validar contexts/tags do thread |
| Ver thread/mensagem | Participante autorizado ou administrador conforme regra upstream | Entity access `private_message_thread.view` / `private_message.view` e handlers próprios | Não compartilhar entre contas; cache context da thread |
| Iniciar conversa | Membro habilitado e destinatário que aceita contato | Destinatário ativo com `use private messaging system` e `access user profiles`; preferência, bloqueio e rate limit revalidados no servidor | Sem cache de formulário/destinatários privados |
| Receber e-mail | Destinatário válido, elegível e permitido | Revalidar status da conta, access, preferências e assinatura na entrega | Não guardar conteúdo privado em cache compartilhado |
| Ver comunicado | Público explicitamente segmentado | Node access, publicação, validade e membership quando necessário | Tags/contextos de entidade, usuário e grupo conforme decisão |
| Assinar evento | Usuário autorizado a assinar o contexto | Flag/Message Subscribe e access atual da entidade | Contexto de usuário e dependências da entidade |
| Administrar alerta/comunicado | Papéis administrativos autorizados em `main` | Permissões nativas dos módulos/editorial | Rotas administrativas sem cache público |

IDs e handlers foram confirmados no código instalado; contexts e tags de
presenters precisam de testes funcionais. Não copiar permissões para o
Messenger nem concedê-las automaticamente a papéis.

Na configuração exportada examinada nesta fase, não há concessão das
permissões nativas `use private messaging system` e `access user profiles`.
Portanto a inclusão do módulo não habilita automaticamente mensagens para
qualquer papel. A distribuição pretendida dessas permissões deve ser decidida
e registrada antes da ativação do fluxo para membros. A proteção upstream é
mantida enquanto essa política não for configurada.

O módulo opcional `private_message_notify` não deve ser ativado junto do
Messenger: seu template padrão inclui o corpo privado no e-mail. Hooks de
instalação do Messenger impedem essa combinação.

O formulário nativo de criação/resposta recebe um callback Form API que usa o
serviço Flood do Core e identifica o limite pelo UID, sem gravar IP. São
permitidos dez envios que passaram pelas validações anteriores em uma janela de
uma hora; entradas já inválidas não consomem limite. Mensagens acima do limite
são rejeitadas antes do submit/save nativo. Esse valor é um limite inicial do
Messenger e deve ser reavaliado após métricas de uso controladas.
Um callback separado consulta `private_message.ban_manager` e rejeita também
destinatários que bloquearam o remetente, inclusive quando o modo nativo estiver
configurado como passivo. A resposta é genérica para reduzir enumeração.

A busca da gaveta e o envio de primeira mensagem aplicam a política central
`ConversationPolicy::canReceiveMessages()`: o destinatário deve estar ativo e
ter as duas permissões nativas exigidas para usar a caixa postal. O formulário
revalida os IDs enviados pelo navegador, e a rota iniciada pelo perfil falha
fechada para destinatários inelegíveis. A busca não revela esses membros.

O evento de notificação é processado pela fila do Core após o hook nativo. A
entrega revalida thread, usuário ativo, permissões e ambos os sentidos de
bloqueio. Uma entidade Message persistida com referência à mensagem de origem,
UID do destinatário e estado oferece deduplicação; lock cobre concorrência.
Novos itens usam Mailer com correlação determinística, e o log de aceite suprime
retries do mesmo destinatário. Falhas temporárias têm no máximo três tentativas.
Itens antigos de fila sem o marcador continuam temporariamente no caminho
Message Notify, sem garantia de correlação após aceite; o caminho e a dependência
legados serão retirados depois de comprovado o esvaziamento da fila no Runtime.

Para assinaturas, só se processam tópicos `forum` ainda publicados. Message
Subscribe verifica status da conta e acesso de visualização do Node no dispatch.
Flagging significa preferência de aviso, nunca acesso. O evento Message tem
chave Node/revisão e estado persistido; `sendMessage()` não retorna resultado
por destinatário, portanto falhas parciais podem exigir reconciliação e serão
cobertas no hardening. A URL é gerada no purpose `forum`; sem alias português
seguro, o envio é suprimido.

As páginas da central exigem autenticação, `access aculta portal` e as duas
permissões nativas do Private Message. A lista de notificações aplica filtro
explícito pelo UID da sessão antes de renderizar Message; nenhum identificador
de usuário é controlado pela URL. A query declara `accessCheck(FALSE)` porque
Message não tem handler que restrinja ownership; filtro UID, bundle conhecido,
status `sent`, limite e cache max-age zero complementam a autorização. Inbox e
ações continuam nos handlers nativos.

## Invariantes

A preferência de e-mail é individual e usa `user.data` nativo do Private
Message; o formulário salva somente para o UID autenticado e o worker revalida
a escolha antes do envio. A página é sem cache compartilhado. Bloqueio usa o
mecanismo nativo; assinar tópico nunca concede acesso, e Message Subscribe
revalida visibilidade no dispatch.

- Cada leitura e escrita usa APIs públicas Drupal e permissões/access nativos;
  não consultar tabelas de contrib por SQL.
- Thread, destinatário e conteúdo são autorizados no servidor em cada request;
  ID de entidade fornecido pela URL não prova acesso.
- `EntityQuery` declara `accessCheck(TRUE|FALSE)` conscientemente. Uma query
  de destinatários não substitui o access handler no endpoint final.
- Formularios de envio usam Form API, token CSRF e fluxo nativo. Sem JavaScript,
  submit e navegação permanecem funcionais.
- Não expor autocomplete que facilite enumeração de usuários; resultados só
  incluem membros selecionáveis conforme permission e preferência do alvo.
- Limitar frequência usando mecanismos Drupal/contrib existentes após verificar
  sua disponibilidade. Não inventar uma tabela de contadores no Messenger.
- O corpo de mensagens privadas nunca aparece em log, e-mail, breadcrumb, cache
  público, metadado de perfil público ou mensagem de erro detalhada.
- E-mails têm informação mínima e link para a rota canônica `account`; link é
  gerado por `DomainPurposeManager`, sem host hardcoded.
- Assinatura é preferência de notificação, não autorização para ler entidade.
  A publicação e a entrega revalidam acesso e membership.
- Entidades de Site Alert não são visíveis em purposes incorretos por uma
  conveniência de tema; purpose, visibilidade e cache são decididos no Drupal.
- Desinstalação não apaga dados pertencentes a contrib. Remoção de config ou
  dados só ocorre com proteção explícita e confirmação operacional.

## Bloqueio e denúncia

Private Message 3.0.7 inclui a entidade `private_message_ban` e o serviço
`private_message.ban_manager`, com operações `isBanned()`,
`isCurrentUserBannedByUser()`, `banUser()` e `unbanUser()`. Reutilizar esse
mecanismo e suas rotas/formulários; não criar storage de bloqueio próprio. A
política ACULTA de denúncia ainda não foi aprovada; não criar fluxo ou registro
paralelo sem decisão de escopo.

## Exclusão e retenção de conversas

O menu contextual e o atalho acessível da conversa apontam ao formulário nativo
`clear_personal_history` do Private Message. A confirmação limpa o histórico
somente para o membro atual; não usa a permissão administrativa de apagar a
thread para todos. A caixa esconde as ações globais nativas da listagem.

O Cron remove threads sem atividade por seis meses, em lotes de até 20 por
execução. A exclusão é feita pela Entity API do Private Message, cujo ciclo
nativo remove as mensagens e registros de histórico relacionados. A política
mede seis meses desde a última atividade da conversa, não desde a criação de
cada mensagem. Threads vencidas só são removidas quando o Cron executar; não
se executa Cron durante atualização de código nem se apagam dados no deploy.

## Verificação obrigatória nas fases funcionais

Cobrir isolamento entre usuários, IDOR, conteúdo não publicado, revogação de
acesso, csrf, XSS, enumeração, rate limit, cache privado, envio duplicado,
preferências de e-mail, logs e endereços cross-purpose. E-mails de teste devem
usar transporte de teste/destinatário controlado; nunca enviar aos membros.
# Segurança e privacidade

Avisos persistentes são Nodes publicados do bundle `aculta_notice`. A operação
de visualização compara o membro atual com os papéis armazenados no aviso e
com o intervalo de validade; falhas fecham o acesso. A consulta usa
`accessCheck(TRUE)` e cada Node é verificado novamente antes de renderizar.
Flagging `aculta_notice_read` é não global, associado ao usuário, e seu link
nativo respeita as permissões individuais de flag/unflag. O cache do contador
individual varia por usuário e não é compartilhado entre contas.

O preview funciona same-origin no host corrente. A conta e as conversas
permanecem privadas e os links da inbox usam o purpose ACCOUNT. O modal de
avisos combina Site Alert (temporário) e Nodes autorizados; o corpo privado de
mensagens não é incluído em e-mail.


## Amizades e novas conversas (0.1.1)

- Entidade privada: somente participantes podem consultar/atualizar; exclusão é negada aos participantes e exige permissão administrativa restrita.
- Solicitações exigem sessão autenticada/ativa, destinatário ativo, UID diferente e chave única por par. Estado/participante são relidos sob lock antes de transicionar.
- Flood API do Core limita novos pedidos a dez por remetente por hora.
- Form API usa POST e token CSRF nativo; IDs recebidos são tratados como entrada não confiável e as entidades são reautorizadas pelo manager.
- Preferência `friends` é consultada no servidor para cada destinatário apenas ao criar thread. Resposta em thread existente não passa pela regra.
- A validação não informa se o destinatário restringiu mensagens a amizades, evitando expor a preferência individual.
- A exceção `administrator`/`content_editor` não pula os bloqueios bilaterais nativos do Private Message.
- Notificações de amizade mostram somente pedidos destinados ao usuário atual; consultas limitam o recipient UID e os registros passam por access handler após load.
- O contador e a página de mensagens são privados por usuário e sem cache compartilhado.
- Estado verificado em 2026-10-24: `PrivateMessageRouteAccessKernelTest`, `PrivateMessageFloodKernelTest` e `FriendshipNoticePipelineKernelTest` passaram **4 testes / 47 assertions** no MariaDB dedicado. A cobertura verifica rota POST do YAML de produção com membership e token Core, contenção do Flood por lock persistente e persistência/fila das transições de amizade. Unit de privacidade (`PrivateMessageFormValidatorTest`, `ConversationPolicyTest`, `ConversationMessagingStateTest`, `PrivateMessageFormHooksTest`, `NewConversationControllerTest`, `MessagesControllerMemberAccessTest` e `FriendshipBundleUpdateTest`) passou **29 testes / 138 assertions**, incluindo bloqueio bilateral, preferência de amizade, destinatários manipulados e payload de resposta inválido. Atualização do bundle tem teste Unit; não foi executado update em Runtime. `PrivateMessageNotificationWorkerTest` **4/82 PASS** comprova que o aviso enfileirado é suprimido se o destinatário perde acesso à thread ou há bloqueio em qualquer direção; nenhum transportador de e-mail é acionado. Requisição HTTP completa com sessão/formulário, isolamento visual em navegador e homologação no Homelab/Runtime continuam pendentes; RUNTIME STATUS: DEFERRED.
