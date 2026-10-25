# Segurança e privacidade

A busca de pessoas para iniciar conversas consulta apelidos dos perfis e usa
`aculta_user` (`PublicMemberIdentityInterface`) para obter o nome permitido pela
política pública. Não pesquisa nomes internos de conta nem oferece fallback
para `UserInterface::getDisplayName()`; contas sem apelido público não aparecem.

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
Ao responder uma thread existente, o Messenger adquire o lock da conversa,
recarrega a entidade e revalida membership e acesso `view` do remetente antes
do submit. Uma remoção concorrente não pode enviar usando a thread antiga.

A busca da gaveta e o envio de primeira mensagem aplicam a política central
`ConversationPolicy::canReceiveMessages()`: o destinatário deve estar ativo e
ter as duas permissões nativas exigidas para usar a caixa postal. O formulário
revalida os IDs enviados pelo navegador, e a rota iniciada pelo perfil falha
fechada para destinatários inelegíveis. A busca não revela esses membros.
No envio de grupos, os bloqueios bilaterais são revalidados antes de o serviço
contrib criar uma thread nova e novamente depois de resolver/recarregar a thread
sob lock. Um bloqueio ocorrido após a validação não deixa uma thread vazia.

O evento de notificação é processado pela fila do Core após o hook nativo. A
entrega revalida thread, usuário ativo, permissões e ambos os sentidos de
bloqueio, além do limite individual de limpeza do histórico: mensagens criadas
no mesmo instante ou antes da limpeza não geram aviso atrasado. Isso importa
quando mensagens novas posteriores mantêm a thread acessível, mas o item
enfileirado já não aparece para o destinatário. Uma entidade Message persistida
com referência à mensagem de origem, UID do destinatário e estado oferece
deduplicação; lock cobre concorrência.
Novos itens usam Mailer com correlação determinística, e o log de aceite suprime
retries do mesmo destinatário. Falhas temporárias têm no máximo três tentativas.
Itens antigos de fila sem o marcador continuam temporariamente no caminho
Message Notify, sem garantia de correlação após aceite; o caminho e a dependência
legados serão retirados depois de comprovado o esvaziamento da fila no Runtime.

Abrir uma notificação por GET só muda seu estado de leitura em navegação iniciada
no mesmo site. Prefetch e prerender recebem `204` privado sem redirect. Uma
navegação cross-site pode seguir para o destino autorizado, mas não grava leitura.
O mesmo classificador é usado pelos avisos LMS, Wiki, Loja, Doações, Alertas do
Site e conversas.

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

A exclusão e o worker de avisos por e-mail usam o mesmo lock por thread. O
worker mantém o lock enquanto recarrega a conversa, revalida cada destinatário
e executa a entrega, de modo que a retenção não exclua a thread entre a
verificação de acesso e o envio. Se outra operação já detém o lock ou a thread
desapareceu, o item falha para retry; nenhuma rotina de retenção foi executada
para validar essa alteração.

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

- Revalidação de segurança Dev.253: `PrivateMessageNotificationWorkerTest` **11/185 PASS**. Casos adicionais demonstram que conta inativa, ausência de `use private messaging system` ou ausência de `access user profiles` encerram o processamento antes de consultar bloqueios, persistir notificação ou acionar transporte. Esta é evidência Unit; não substitui Kernel, navegador nem Runtime.

- Dev.254: a ação confirmada de limpar histórico exige sessão autenticada, conta ainda ativa, membership atual e autorização `view` obtida pelo entity access handler no próprio limite de mutação. A primeira etapa do formulário apenas apresenta confirmação. `ConversationRelationshipFormTest` **3/27 PASS** cobre acesso revogado, conta inativa e ausência de mutação antes da confirmação. Unit Messenger **254/1.976 PASS**. O teste passa PHPCS Drupal/DrupalPractice; o formulário ainda tem 15 erros/23 avisos de baseline PHPCS Drupal (reduzidos de 20/25). Kernel e Runtime não executados.

- Dev.255: ações de amizade e bloqueio no menu de membro recarregam conta, alvo e thread e revalidam conta ativa, permissões de mensagens/perfis, membership de ambos e acesso `view` antes de chamar os managers. `ConversationRelationshipFormTest` **5/40 PASS** cobre alvo removido, acesso revogado e efeitos da confirmação; Unit Messenger **256/1.989 PASS**. PHPCS Drupal/DrupalPractice do teste e gate Drupal 11+ passam; o formulário mantém seu baseline PHPCS de 15 erros/23 avisos. Navegador/Kernel/Runtime não executados.

- Dev.256: a guarda que impede prefetch/prerender de atualizar estado de leitura verifica os dois headers (`Sec-Purpose` e `Purpose`) separadamente. Cabeçalho moderno vazio não suprime o legado, e valores de `Sec-Fetch-Site` são normalizados. `MemberInitiatedRequestTest` **3/11 PASS** e Unit Messenger **257/1.991 PASS**; PHPCS Drupal/DrupalPractice e lint PASS. Kernel, Browser e Runtime não executados.
- Dev.257: a guarda também exige destino de navegação `document`, `empty` ou header ausente; destinos explícitos de sub-recursos (por exemplo `iframe`, `image`, `script` e `object`) não podem marcar leitura. A gaveta continua autorizada a buscar fragmentos com Fetch same-origin (`Sec-Fetch-Dest: empty`). `MemberInitiatedRequestTest` **4/16 PASS**, Unit Messenger **258/1.996 PASS**, PHPCS Drupal/DrupalPractice e lint PASS. Kernel, Browser e Runtime não executados.
- Dev.258: redirects personalizados depois da abertura e leitura de avisos de LMS, Wiki, Loja e Doações definem `private, no-store`; `NoticeOpenControllerSafetyTest` valida o header em todas as origens (**5/33 PASS**). Unit Messenger **258/2.000 PASS**; PHPCS Drupal/DrupalPractice e PHP lint PASS. Kernel, Browser e Runtime não executados.
- Dev.259: respostas em thread recarregam a thread sob lock e revalidam bloqueios de remetente e destinatário antes de salvar; se um bloqueio apareceu após a primeira validação, o envio é recusado e os locks de conversa/Flood liberados. O submit de nova conversa também repete a verificação após resolver uma thread concorrente. `PrivateMessageFormValidatorTest` **15/77 PASS**, Unit Messenger **259/2.011 PASS**, PHPCS do validator/teste e PHP lint PASS. `NewConversationForm` preserva baseline PHPCS 16/15. Kernel, Browser e Runtime não executados.
- Dev.260: requests sem Fetch Metadata precisam trazer `X-Requested-With: XMLHttpRequest` (gaveta) ou `Sec-Fetch-User: ?1` em uma navegação de documento ativada para atualizar leitura. `Sec-Fetch-Dest` de embed, prefetch/prerender e cross-site continuam rejeitados. Links sem sinal explícito ainda podem abrir o destino, mas não atualizar leitura. Testes focais **10/53 PASS**, Unit Messenger **260/2.015 PASS**, PHPCS Drupal/DrupalPractice e PHP lint PASS. Kernel, Browser e Runtime não executados.
- Dev.261: o sinal `X-Requested-With` só autoriza o Fetch da gaveta quando `Sec-Fetch-Site` é `same-origin` ou não está disponível; requests `same-site` entre origens distintas não atualizam leitura. `MemberInitiatedRequestTest` **5/21 PASS**, Unit Messenger **260/2.016 PASS**, PHPCS Drupal/DrupalPractice e PHP lint PASS. Kernel, Browser e Runtime não executados.
## Requests de navegação e leitura

Controllers de conversa e abertura de avisos não marcam leitura em requests cross-site, prefetch ou prerender. A navegação de documento exige sinal de ativação explícita e `Sec-Fetch-Site` ausente, `same-origin` ou `none`; `same-site` é recusado porque uma origem irmã não é a origem do site. Fetch da gaveta aceita `XMLHttpRequest` com metadata ausente ou `same-origin`, e rejeita origins irmãs e destinos embutidos. Responses da gaveta e redirects de avisos são privados e `no-store`.

## Política de início de conversa

A preferência de novas conversas e a amizade são verificadas durante a validação e novamente no limite de persistência. O Messenger consulta primeiro o mapper do Private Message: threads existentes continuam sob a política de resposta; antes de `PrivateMessageService::getThreadForMembers()` criar uma nova thread, a permissão é revalidada. Se o destinatário passou a aceitar somente amigos e a amizade não existe, a criação é recusada sem persistir uma thread vazia. Bloqueios bilaterais continuam revalidados separadamente após o carregamento e o lock da thread.

A busca de destinatários e o formulário aberto por perfil usam a mesma checagem de elegibilidade. Ambos os lados de bloqueio são respeitados antes de revelar a identidade ou apresentar o composer. A preferência friend-only filtra somente o início de uma thread; se já existir uma conversa com o conjunto exato de membros, o composer continua disponível para resposta.

