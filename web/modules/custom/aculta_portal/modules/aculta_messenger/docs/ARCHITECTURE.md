# Arquitetura do ACULTA Messenger

Documento de referência arquitetural. Dono: `aculta_messenger` no ACULTA
Portal. Revisão: 2026-10-15.

> Estado atual do projeto (rotas, serviços, hooks, classes e contratos com o
> tema) está em [`docs/portal/MESSENGER.md`](../../../../../../docs/portal/MESSENGER.md).
> A separação entre módulo, submódulo e tema está em
> [`docs/portal/MESSENGER-AUDIT.md`](../../../../../../docs/portal/MESSENGER-AUDIT.md).
> As seções deste arquivo descrevem as decisões de cada subfase.

## Fronteira

```text
Drupal Core + módulos contrib (dados, APIs, access, entrega)
                         ↓
              aculta_portal/aculta_messenger
             integração, URLs, access, cache
                         ↓
               contrato neutro renderizável
                         ↓
                     ACULTA420
```

O módulo agrega as fontes existentes; não cria uma segunda fonte de verdade.
ACULTA420 recebe apenas dados e render arrays já autorizados. O tema não decide
destinatários, permissões, domínio, armazenamento ou política de notificação.

## Fontes de verdade

Preferências de comunicação ficam no ACCOUNT. O opt-out de e-mail reutiliza
`private_message.receive_notification` em Core `user.data`; nenhum estado de
preferência duplicado é introduzido. Bloqueios e assinaturas mantêm o modelo
nativo por membro e por tópico.

| Capacidade | Fonte de verdade | Papel do Messenger | Storage próprio |
| --- | --- | --- | --- |
| Conversa e mensagens privadas | Private Message | Integração de UX, ligações e eventual presenter | Não |
| Evento/notificação registrada | Message, quando o caso exigir Message entity | Define tipos de eventos aprovados e coordena geração | Não |
| Entrega de evento | Message Notify + Drupal Mail | Define conteúdo mínimo, preferências e destinatário permitido | Não |
| Assinatura | Message Subscribe + Flag | Seleciona eventos suportados e filtra por access | Não |
| Alerta temporário | Site Alert | Entrada e apresentação do alerta | Não |
| Comunicado editorial persistente | Node `aculta_notice`, workflow `editorial`, campos de público/validade e Flag `aculta_notice_read` | Segmentação por papéis e leitura individual | Não |
| Preferência de usuário | Configuração/field/entity nativa a confirmar no upstream | Orquestra a UX sem cópia paralela | Não até prova de lacuna |
| Identidade e papel | User + Profile + Group | Aplicação de política sem replicar identidade/associação | Não |
| Host e propósito | Domain + `DomainPurposeManager` | Resolver rotas canônicas e aplicar política de propósito | Não |

## Contratos de propósito

| Propósito | Responsabilidade |
| --- | --- |
| `account` | Central de conversas, avisos pessoais e notificações do membro |
| `main` | Administração de alertas/comunicados; não é uma caixa postal alternativa |
| Demais purposes existentes | Podem produzir eventos autorizados ou apontar para `account`; não hospedam inbox |

As rotas novas da central devem declarar ownership `account` com a opção de
rota do Portal e URLs absolutas/cross-purpose devem ser produzidas por
`DomainPurposeManager`. GET/HEAD pode navegar para outro purpose conforme a
política; POST/PUT/PATCH/DELETE nunca são redirecionados nem replayados entre
hosts. Redirect deliberado entre purposes usa `TrustedRedirectResponse` e URL
resolvida pelo serviço. Nenhuma rota pública usa slug inglês/técnico; rotas de
ação preservam a exceção de rota técnica documentada pelo projeto.

## Eventos e públicos iniciais

| Evento | Público elegível | Canal | Regra |
| --- | --- | --- | --- |
| Nova mensagem privada | Participantes autorizados não remetentes | Inbox e aviso por e-mail, se habilitado | E-mail não inclui corpo; link seguro para a central |
| Comunicado publicado | Membros que possuem ao menos um papel selecionado no Node | Central; e-mail conforme preferência/política | Node access reavaliado na listagem; avisos iniciados/expirados respeitam validade |
| Evento de entidade assinada | Assinantes ativos com acesso atual ao contexto | Message Notify quando configurado | Revogação de access suprime entrega |
| Site Alert ativo | Visitantes conforme regra nativa | Bloco/componente global de apresentação | Início, término e severidade vêm da entidade Site Alert |

Para mensagem privada, a referência ao Message e o lock por par evento/
destinatário definem a idempotência inicial; Queue API processa o evento depois
de salvar a thread. Mensagem, status e número de tentativas usam a entidade
Message. Não há fila ou tabela própria. Uma notificação não revela a existência
de usuário, conversa ou conteúdo a público não autorizado.

Para Message Subscribe, o escopo inicial é publicação de Node `forum` seguido
pela flag existente `seguir_topico`. O evento registra Node/revisão para
deduplicação, e Message Subscribe revalida status e acesso atual do Node no
dispatch. URLs usam o purpose `forum`; conteúdo sem alias português não é
incluído no aviso. A entrega usa o notifier configurado e o e-mail já instalado.
Como `gnode` não está habilitado, atividades Group não participam do escopo.

Site Alert permanece a fonte dos avisos temporários; sua instância de bloco
nativa ocupa `highlighted` e usa o intervalo de atualização próprio. Por ser
sitewide e não oferecer escopo Domain, é apresentada nos purposes do ACULTA420.
Comunicados persistentes usam o bundle `aculta_notice`, workflow `editorial`,
validade opcional e segmentação por papéis do usuário. Um hook de access nega
visualização fora do público/intervalo. A flag `aculta_notice_read` registra
estado individual sem tabela paralela. O sino de membros soma avisos
persistentes não lidos e Site Alerts ativos; visitantes veem a contagem de Site
Alerts ativos.

O Portal compõe o shell da conta e oferece alters genéricos de seção/subrota.
Messenger participa como módulo opcional, enquanto rotas privadas nativas são
classificadas para ACCOUNT. O contador vem do serviço Private Message; a lista
de notificações usa Message com UID da sessão e cache privado. Avisos
institucionais persistentes aparecem no mesmo modal, mas são armazenados como
Nodes e não no Site Alert.

## Inventário técnico upstream

O inventário verificado no código instalado (Core 11.4.10) está em
[INTEGRATIONS.md](INTEGRATIONS.md). Em síntese, Private Message tem entidades
de mensagem/thread/ban e serviços de thread, bloqueio e contagem não lida;
Message tem entidades `message` e `message_template`; Message Notify oferece
`message_notify.sender`; Message Subscribe consulta Flag por
`message_subscribe.subscribers`; Site Alert tem entidade própria e serviço de
alertas ativos.

Private Message exige na rota da inbox `use private messaging system` e
`access user profiles` em conjunto. A entidade `private_message_ban` provê
bloqueio upstream; denúncia segue sem definição de produto. `message_subscribe`
tem queue opcional e opção de revalidar access da entidade. Site Alert inclui
cache context `active_site_alerts`; validar o bubbling quando a UI for
integrada.

`private_message_notify` (submódulo do pacote Private Message) foi desinstalado
do Runtime: seu template padrão inclui o corpo privado. O Messenger impede a
reativação simultânea enquanto esse template não for substituído por uma
configuração que respeite a política ACULTA. O código continua no pacote
`drupal/private_message`, que o Messenger usa.

`hook_private_message_new_message` é o evento público do Private Message;
Message Subscribe fornece hooks de seleção e alteração de candidatos e
mensagens. Escolher handlers/hooks OOP somente após revisar assinaturas no
Core e upstream instalados.

## Decisões arquiteturais

### ADR-MSG-008 — pedidos de amizade e privacidade de novas conversas

**Decisão:** `aculta_user` é dono do workflow de amizade e expõe seu serviço
para o `aculta_messenger`. Nenhum módulo instalado oferece o contrato completo
de pedido, aceite, recusa, notificação e autorização exigido. O estado explícito
será uma entidade Drupal de `aculta_user`; eventos poderão ser Messages
entregues por Message Notify. Não instalar o projeto Friendship, que declara
não estar coberto pela política de segurança Drupal, nem reutilizar User
Relationships, voltado a Drupal 7. A implementação de Friendship é referência
de casos de uso, com modelo refeito para DI, Entity API, access control e
Form API/POST; Flag permanece para leitura/assinatura, sem virar grafo social.

**Política:** `todos` é o padrão retrocompatível. `somente amigos` afeta apenas
a criação de uma nova thread; respostas a threads existentes continuam
permitidas, inclusive se a preferência mudar ou a amizade terminar. O
destinatário deve ser conta ativa, não pode ser o remetente e não pode bloquear
o remetente; bloqueio bilateral nativo do Private Message é sempre respeitado.
Administradores e editores de conteúdo podem ignorar a preferência de novas
conversas, nunca os bloqueios. Essa exceção não concede permissão Drupal nem
acesso a threads. Regras são aplicadas no servidor no submit e não dependem da
visibilidade de links.

**Motivo:** a separação preserva ownership: `aculta_user` governa perfil e
relacionamentos entre contas; `aculta_messenger` integra comunicação. A
preferência individual no User Data evita configuração paralela; a entidade
permite queries, estados e acesso explícitos, sem confundir pedido pendente
com amizade aceita. Mensagens e notificações continuam nas fontes existentes.

**Consequências:** novas entidades e ações exigem validação de permissão,
cacheabilidade por usuário, proteção contra IDOR, serialização de transições
concorrentes e testes de isolamento. Notificações não são fonte do estado da
amizade. A implementação deve manter nome de bundle e campos abaixo de 32
caracteres.

### ADR-MSG-001 — Contrib como fonte de verdade

**Decisão:** não criar engine, storage ou preferências duplicadas no Portal.

**Motivo:** conversas, mensagens, flags e alertas já possuem módulos
especializados. Um espelho divergiria em autorização, exclusão e retenção.
Integração custom só entra quando o upstream não fornece o contrato exigido e
a lacuna for demonstrada por código/teste.

### ADR-MSG-002 — Conta única para comunicação

**Decisão:** toda caixa postal fica no purpose `account`; administração fica em
`main`.

**Motivo:** a separação de host não pode criar caixas inconsistentes nem
permitir acesso administrativo em purpose público. Domain determina URL e
ownership; não se infere purpose do hostname no módulo ou tema.

### ADR-MSG-003 — Notificação mínima

**Decisão:** e-mail de mensagem privada comunica o evento e leva a link seguro;
corpo/conteúdo privado não vai por e-mail como padrão.

**Motivo:** e-mail pode ser encaminhado, copiado por provedores e persistido em
caixas compartilhadas. A leitura do conteúdo continua protegida pelo access
online.

### ADR-MSG-004 — Site Alert é temporário; Node é editorial

**Decisão:** Site Alert guarda alertas transitórios. Comunicados com revisão,
autoria, publicação, validade e histórico usam Node/Views/workflow se a
segmentação for verificável.

**Motivo:** não duplicar o armazenamento Site Alert nem forçar seu modelo
temporário a funcionar como arquivo editorial.

### ADR-MSG-005 — Limite de envio usa Flood do Core

**Decisão:** limitar o formulário nativo do Private Message com o serviço Flood
do Drupal: dez envios válidos por usuário em uma janela de uma hora.

**Motivo:** o Core já fornece contagem com expiração e limpeza por cron. Uma
tabela custom adicionaria dados operacionais e manutenção sem necessidade. O
limite é aplicado como callback Form API após validações de destinatários e
bloqueio, preservando o submit nativo.

### ADR-MSG-006 — Aviso privado em fila e sem corpo

**Decisão:** registrar o evento como Message, enfileirar somente o ID da
mensagem privada e resolver thread, access, bloqueio e destinatário durante o
processamento. A entrega usa `message_notify.sender` com template ACULTA que
contém somente um link para a página central do purpose `account`.

**Motivo:** o hook upstream ocorre antes de salvar uma thread nova, e o
notificador opcional do Private Message inclui o corpo privado no template
padrão. A fila processa depois do salvamento; o campo de referência no Message
mantém uma chave de deduplicação por mensagem e destinatário. O link usa a raiz
da conta enquanto as rotas portuguesas da caixa postal não existem. Drupal
Queue processa com semântica de pelo menos uma vez, então a entidade persistida
impede recriar o evento e limita tentativas de entrega.

### ADR-MSG-007 — Apresentação ACULTA420 sem acoplamento funcional

**Decisão:** o Messenger e o Portal entregam render arrays com access/cache e
valores neutros. O tema mapeia rotas/entidades já resolvidas para os SDCs
`messenger-list`, `messenger-item`, `messenger-compose`,
`messenger-notification`, `institutional-notice`, `unread-badge` e
`messenger-state`.

**Motivo:** SDC é a camada visual; não deve consultar storage, Domain ou
permissões, nem substituir Form API. Slots recebem render arrays, preservando
cacheability e os formulários Private Message. A severidade de Site Alert é
passada pela saída nativa e estilizada com tokens semânticos. O Portal envia a
contagem de não lidas como número no contrato neutro do shell; o tema decide sua
forma visual.

## Caixa de conversas e dispensa de avisos (0.1.1)

Ao abrir a gaveta, o controller chama `PrivateMessageServiceInterface::updateLastCheckTime()`;
isso reaproveita o estado nativo do módulo e faz o contador refletir zero após a
consulta da inbox. A lista continua usando view mode `inbox` da entidade de
thread. Cada link abre a rota canônica da thread em diálogo AJAX Drupal, cujo
view mode completo fornece o histórico e o formulário nativo de resposta.

`PrivateMessagePresentationHooks::preprocessPrivateMessage()` fornece avatar
User API (compact, respeitando field access/cache) e booleano neutro de direção.
O `ThemeHooks::preprocessPrivateMessage()` mapeia esses valores ao SDC
`messenger-item`, que exibe balões; persistência, validação e envio continuam no
Private Message. `aculta_site_alert_dismissed` e `aculta_notice_read` são Flags
não globais por usuário, para que o X dispense só na conta que o acionou e a
contagem do badge seja recalculada na próxima consulta.
# Avisos e estado de leitura

Avisos temporários ficam no Site Alert e continuam com agenda, severidade e
visibilidade de origem. Comunicados persistentes usam Node (`aculta_notice`),
workflow editorial, público por papéis Drupal e validade opcional. O Flag
`aculta_notice_read` guarda a leitura por usuário. Conversas usam o estado
nativo por thread de Private Message; nenhuma tabela paralela de recibos é
criada. As mensagens são lidas e enviadas na gaveta da barra multidomínio (ver
`docs/portal/MESSENGER.md`); a gaveta é carregada por rotas same-origin, e a
navegação canônica para mensagens continua no ACCOUNT.


## Amizades e preferência de novas conversas (0.1.1)

O ownership do relacionamento fica em `aculta_user`; a entidade `aculta_friendship` guarda um registro por par com uma chave string canônica única, direção solicitante/destinatário e estado. A API `FriendshipManagerInterface` serializa transições com o lock persistente do Core e verifica participante/estado em cada mutação.

O Messenger consome essa API em três pontos: ações de perfil, pedidos recebidos em Notificações/contador, e validação de destinatários ao criar nova thread. `user.data` (`aculta_messenger/message_privacy`) guarda a preferência individual sem schema duplicado. Admin/editor ignoram somente essa preferência; a verificação nativa de bloqueio corre antes e continua obrigatória. Threads existentes não são afetadas.
