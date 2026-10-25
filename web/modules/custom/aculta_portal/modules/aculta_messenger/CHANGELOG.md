## 0.1.1-dev.256 — fechar fallback de prefetch na leitura de avisos

A classificação de navegação agora avalia `Sec-Purpose` e `Purpose` independentemente. Um cabeçalho novo vazio não esconde o cabeçalho legado que identifica prefetch/prerender; `Sec-Fetch-Site` também é normalizado antes da comparação.

Validação: `MemberInitiatedRequestTest` **3/11 PASS**, Unit Messenger **257/1.991 PASS**, PHPCS Drupal/DrupalPractice e PHP lint PASS. Kernel, Browser e Runtime não executados.

## 0.1.1-dev.255 — revalidar ações de relacionamento na thread

Os formulários de amizade e bloqueio agora carregam o ID da thread como estado interno do servidor e, antes da ação, recarregam conta/alvo/thread e revalidam conta ativa, permissões, membership de ambas as pessoas e autorização `view`. A ação falha genericamente se o acesso mudou desde a checagem de rota.

Validação: `ConversationRelationshipFormTest` **5/40 PASS**; Unit Messenger **256/1.989 PASS**; PHPCS Drupal/DrupalPractice do teste e gate Drupal 11+ (**1.155 checks**) PASS. O formulário mantém baseline PHPCS **15 erros/23 avisos**, sem aumento. Browser, Kernel e Runtime não executados.

## 0.1.1-dev.254 — revalidar autorização ao limpar histórico

A confirmação de limpeza agora revalida conta autenticada e ativa, membership e `thread->access('view', ..., TRUE)` imediatamente antes de alterar o histórico. A etapa inicial permanece somente confirmatória e não altera mensagens. O teste cobre acesso revogado e ausência de mutação antes da confirmação.

Validação: `ConversationRelationshipFormTest` **3 testes / 27 assertions PASS**; Unit Messenger **254/1.976 PASS**; PHPCS Drupal/DrupalPractice do teste PASS. O formulário alterado ainda tem 15 erros/23 avisos de baseline no PHPCS Drupal (antes: 20/25); Kernel e Runtime não executados.

## 0.1.1-dev.253 — caracterizar elegibilidade dos destinatários de e-mail privado

O teste do worker agora prova que conta inativa, revogação de `use private messaging system` ou de `access user profiles` interrompem o aviso antes da consulta de bloqueios, persistência do registro ou transporte. O worker mantém a revalidação dessas condições em cada entrega.

Validação: `PrivateMessageNotificationWorkerTest` **11 testes / 185 assertions PASS**; Unit Messenger **251/1.949 PASS**; PHPCS Drupal e DrupalPractice PASS. Browser, Kernel e Runtime não foram executados neste incremento.

## 0.1.1-dev.252 — integrar pedidos e aceites à central de avisos

Os eventos de amizade agora aparecem também na central compartilhada sob a origem **Amizades**, com paginação, filtro, agrupamento, contador e estado lido/não lido no `Message` existente. Os textos não expõem nomes de terceiros. Update aditivo 10011 instala o campo de leitura nos registros existentes; a integração não cria inbox nem storage paralelo. Proteção de uninstall agora considera fila e registros de amizade.

Validação: Unit Messenger **247 testes / 1.894 assertions PASS**; manager/adapters focais **42/277 PASS**; PHP lint passou. PHPCS dos arquivos novos/limpos passou; os controllers e `LifecycleHooksTest` mantêm violações preexistentes. Kernel não executado porque `SIMPLETEST_DB` está ausente; update e Runtime não executados.

## 0.1.1-dev.251 — caracterizar os e-mails de pedido e aceite

Os testes do worker agora verificam separadamente o texto genérico de pedido e de aceite de amizade e limitam o contexto enviado ao Mailer a `summary` e `url`, sem identidade de terceiros.

Validação: `FriendshipNoticeEmailWorkerTest` **11 testes / 211 assertions PASS**. O transporte segue mockado; Runtime/SMTP não executados.

## 0.1.1-dev.250 — preservar cancelamento concorrente no enqueue

Se a Queue API recusar uma inclusão enquanto outro processo altera o aviso, a recuperação agora só grava `failed` quando o registro ainda está `pending`/`failed` e não houve tentativa de entrega. Estados terminais, como `cancelled` e `failed_permanent`, são preservados.

Validação: `FriendshipNoticeQueueTest` + `FriendshipNoticeEmailWorkerTest` **14 testes / 255 assertions PASS**; PHPCS Drupal/DrupalPractice e PHP lint dos arquivos alterados PASS. Runtime e transporte externo não executados.

## 0.1.1-dev.249 — não reabrir avisos de amizade terminais

O worker de e-mail de amizades agora aceita somente registros `pending` ou `failed` recuperáveis. Replays atrasados da fila não enviam novamente registros cancelados por opt-out/conta inelegível, falhas permanentes ou entregas já concluídas; o caminho de retry temporário permanece ativo.

Validação: `FriendshipNoticeEmailWorkerTest` **10 testes / 187 assertions PASS**; PHP lint nos arquivos alterados PASS. Runtime e transporte externo não executados.

## 0.1.1-dev.248 — agrupar mensagens por remetente no resumo semanal

O resumo semanal de Mensagens Privadas agora agrega os avisos por remetente, com quantidade em uma única linha. O manifesto conserva cada ID de origem para reconhecer somente os itens realmente incluídos após retry; novas mensagens recebidas durante o transporte permanecem pendentes. A linha não repete contador/título e nunca inclui o conteúdo da conversa.

Validação: Unit Messenger **235 testes / 1.767 assertions PASS**; PHPCS Drupal/DrupalPractice nos arquivos alterados e diff check PASS. Kernel não executado sem `SIMPLETEST_DB`; Runtime/SMTP não executados.

## 0.1.1-dev.247 — centralizar e-mail privado e resumo semanal sem conteúdo

Preferências de Mensagens Privadas passam a ser próprias do Messenger, com migração incremental da configuração e das escolhas legadas do Private Message; escolhas Messenger existentes são preservadas e a chave individual antiga é removida após cópia. A pessoa escolhe aviso genérico direto, resumo semanal ou sem e-mail. O resumo reutiliza os registros Message existentes, mostra remetente e link para a inbox, nunca o conteúdo da conversa, revalida acesso e bloqueios, respeita data âncora administrativa e intervalo de sete dias por conta. O digest privado não depende do opt-in de resumo diário das demais origens. Sem execução do update no Runtime.

Validação: Unit Messenger **234 testes / 1.766 assertions PASS**; PHPCS Drupal/DrupalPractice nos arquivos alterados, PHP lint, YAML parse e `git diff --check` PASS. `AvisosResumoMailerKernelTest` não executou por ausência de `SIMPLETEST_DB`. Update, Runtime e SMTP permanecem pendentes.

## 0.1.1-dev.246 — paginação independente por origem na central

A visão sem filtro da Conta agora pagina cada grupo separadamente: comunicados, Fórum, alertas do site, LMS, Wiki, Loja e Doações têm cursores próprios. Avançar um grupo preserva as posições dos demais, com filtros de leitura aplicados antes das páginas, ordem recente e separadores por origem mantidos. Offsets recebidos pela URL são validados e limitados; adapters opcionais continuam isolados. A paginação por origem já existente permanece compatível.

Validação: Unit Messenger **229 testes / 1.702 assertions PASS**; `PersonalNoticeAdapterHooksTest`, `PersistentNoticePaginationTest` e `NoticeListFilterTest` **16/153 PASS**; PHPCS Drupal/DrupalPractice sem novos erros ante o baseline dos dois controllers; PHPCS limpo nos arquivos de hook/teste, PHP lint, índice e `git diff --check` PASS. Firefox/Runtime não executado.

## 0.1.1-dev.245 — cancelar e-mail privado pendente após opt-out

Se a preferência mudar para site-only ou o e-mail deixar de estar confirmado enquanto uma entrega privada está pendente, o worker agora atualiza o registro de outbox para `cancelled`. A mensagem continua na inbox do Private Message; o cancelamento evita deixar uma linha pendente sem retry nem transporte. Nenhuma tentativa é contabilizada quando o envio é suprimido.

Validação: Unit Messenger **228 testes / 1.688 assertions PASS**; `PrivateMessageNotificationWorkerTest` **7/128 PASS**; PHPCS Drupal/DrupalPractice, índice de status e `git diff --check` PASS. Sem Runtime ou SMTP.

## 0.1.1-dev.244 — exigir e-mail confirmado para aviso de mensagem privada

O worker de mensagens privadas agora revalida se o destinatário é uma conta ativa, tem acesso atual à thread, possui e-mail válido e confirmado, não está bloqueado e mantém a preferência de entrega direta antes de criar/enviar o aviso. O e-mail continua mínimo e sem conteúdo da conversa. Itens não confirmados não chegam ao Mailer e não consomem tentativa.

Validação: `PrivateMessageNotificationWorkerTest` **6 testes / 111 assertions PASS**, incluindo confirmação e supressão do e-mail não confirmado; Unit Messenger completo **227 testes / 1.671 assertions PASS**; PHPCS Drupal/DrupalPractice, índice de status e diff check PASS. Sem Runtime ou SMTP.

## 0.1.1-dev.243 — preferências comuns para mensagens privadas e amizade

Mensagens privadas e avisos de amizade passam a usar categorias administráveis no núcleo de preferências. A pessoa escolhe entre somente no site e site mais e-mail direto; essas categorias não oferecem digest. O formulário administrativo pode bloquear o envio direto sem remover a opção site-only. Conversas privadas e avisos de amizade permanecem no site independentemente do e-mail. Os workers revalidam consentimento atual e confirmação de e-mail antes do transporte; opt-out cancela sem contar tentativa. A preferência antiga de Private Message continua como fallback para contas sem escolha nova.

Validação: `AvisoPreferenciasTest`, `AvisosPrivacyFormTest`, `MessengerSettingsFormTest`, `PrivateMessageNotificationWorkerTest` e `FriendshipNoticeEmailWorkerTest` **39 testes / 400 assertions PASS**. Transporte mockado; SMTP e Runtime não executados.

## 0.1.1-dev.242 — validar Doações antes de contar tentativa de e-mail

O worker direto de Doações agora revalida conta ativa, e-mail confirmado e preferência antes de resolver os dados do pedido. O aviso só é resolvido após consentimento atual; tentativas aumentam apenas quando o Mailer será chamado. Opt-out e acesso revogado cancelam a entrega sem registrar uma tentativa de transporte.

Validação: `DonationNoticeEmailWorkerTest` **7 testes / 88 assertions PASS**, incluindo opt-out e e-mail não confirmado antes da resolução do pedido; PHPCS Drupal/DrupalPractice, PHP lint e `git diff --check` PASS. Sem alteração de schema; sem Runtime ou SMTP externo.

## 0.1.1-dev.241 — falhar fechado em política de e-mail inválida

Políticas administrativas de categoria só habilitam digest ou envio direto quando configuradas explicitamente como booleano `true`. Uma categoria explicitamente malformada agora permite apenas `off`; ausência da configuração continua compatível com os modos existentes. O rótulo da permissão administrativa passa a mencionar mensagens, avisos e e-mail.

Validação: `AvisoPreferenciasTest`, `AvisosPrivacyFormTest` e `MessengerSettingsFormTest` **24 testes / 129 assertions PASS**; PHPCS Drupal/DrupalPractice e PHP lint dos arquivos alterados PASS. Não requer alteração de schema nem atualização de dados; Runtime e transporte SMTP não executados.

## 0.1.1-dev.240 — agrupar avisos por origem e ordenar por atualização

A central unificada agora apresenta seções identificadas por origem, separadas visualmente, e mantém a ordem de atualização mais recente disponível dentro de cada seção. Fórum usa `changed` e ID; limpar contadores de leitura ou digest preserva `changed`, para que a entrega não reordene atividade. Adapters Message usam `created` e Alertas do Site usam ID decrescente como ordem estável, pois a entidade não expõe data de criação. Avisos antigos não lidos deixam de ficar acima dos mais recentes; filtros, paginação e agrupamento de eventos relacionados continuam preservados.

Validação: Unit completo do Messenger **221 testes / 1.582 assertions PASS**, incluindo `NoticeOriginGrouperTest`, `NoticeRelatedGrouperTest` e `NoticeListFilterTest` (**9/41**); Kernel MariaDB completo `ForumNoticeIdempotencyKernelTest` **38/893 PASS** (teste focal de ordenação **1/22**); PHPCS Drupal/DrupalPractice e PHP lint dos arquivos novos/alterados PASS; `MessagesController` mantém violações preexistentes, com perfil **95 erros / 65 avisos** (HEAD anterior: 96/65). Foundation **345 checks PASS** e Design Foundations **2.170 checks PASS**. Smoke read-only `drawer-notice-filters.mjs` **8/8 PASS** no Firefox ESR visível, host de teste `aculta.toca.net.br`, viewport 1270 px. Runtime Alpha e demais viewports permanecem pendentes.

## 0.1.1-dev.239 — impedir leitura por prefetch de avisos

As rotas GET de abertura de avisos LMS, Wiki, Loja e Doações agora compartilham a política de requisição iniciada pelo membro usada no Messenger. Prefetch e prerender recebem `204` privado sem redirecionamento; navegações cross-site ainda podem abrir o destino autorizado, mas não marcam o aviso como lido. A navegação normal mantém o redirect e a alteração de leitura.

Validação: testes de requisição/abertura, ServiceProvider, contrato GET-only e acesso do controlador **18 testes / 118 assertions PASS**; PHPCS Drupal/DrupalPractice e PHP lint PASS nos arquivos modificados, exceto `MessagesController`, cujo baseline permanece idêntico (**95 erros / 65 avisos**). Sem Runtime ou navegação em navegador.

## 0.1.1-dev.238 — despachar somente o adapter da origem filtrada

A central agora chama somente o adapter que corresponde à origem selecionada. Filtros de Fórum, Comunicados, Alertas e Conta deixam de disparar consultas de LMS, Wiki, Loja e Doações; o contador agregado continua consultando todos os adapters.

Validação: `PersonalNoticeAdapterHooksTest` **9 testes / 78 assertions PASS**; conjunto de filtros/paginação/ações da central **22/180 PASS**; PHPCS Drupal/DrupalPractice, PHP lint e índice de status PASS. Runtime não executado.

## 0.1.1-dev.237 — respeitar o histórico apagado em avisos enfileirados

O worker de e-mail de mensagens privadas agora compara a data da mensagem com o limite individual de limpeza do histórico. Uma mensagem já ocultada para o destinatário não gera aviso tardio, mesmo que mensagens posteriores mantenham a thread acessível.

Validação: `PrivateMessageNotificationWorkerTest` **5 testes / 100 assertions PASS**; PHP lint e PHPCS Drupal/DrupalPractice PASS. Runtime e e-mail externo não executados.

## 0.1.1-dev.236 — evitar hidratar o histórico na retenção

A limpeza de conversas agora obtém IDs das referências `private_messages` da thread sem carregar cada entidade e corpo
de mensagem. IDs inválidos e repetidos são descartados antes de localizar avisos vinculados; a exclusão continua usando
o ciclo de vida do Private Message e a mesma transação para manter rollback cruzado.

Validação: `PrivateMessageRetentionHooksTest` **3 testes / 64 assertions PASS**; Kernel MariaDB dedicado
`PrivateMessageRetentionKernelTest` **3/42 PASS** (10 deprecações de Core/contrib); PHPCS Drupal/DrupalPractice e PHP
lint PASS. Runtime e limpeza de dados operacionais não executados.

## 0.1.1-dev.235 — rejeitar payload estruturado como primeira mensagem

O formulário de nova conversa agora aceita somente uma string não vazia para a primeira mensagem. Arrays ou outros tipos
recebem erro de validação antes da resolução de destinatários e da aquisição de locks, evitando que o cast PHP grave
`Array` como conteúdo. O fluxo nativo de respostas não foi alterado.

Validação: `NewConversationRecipientIdsTest` **4 testes / 14 assertions PASS**; suites focais de conversas privadas
**24/126 PASS**; PHP lint PASS. PHPCS do teste PASS; `NewConversationForm` mantém o baseline anterior, sem novas
violações (**16 erros / 15 avisos**). Runtime não executado.

## 0.1.1-dev.234 — esclarecer as opções de digest e envio direto

A tela de privacidade agora informa, para Fórum, Aprendizagem, Wiki, Loja e Doações, que o digest diário exige também
o opt-in geral, enquanto o envio direto é controlado somente pela preferência da categoria. O aviso no site permanece
obrigatório. O controle geral foi descrito como opt-in de resumo, não de envio direto.

Validação: `AvisosPrivacyFormTest` **6 testes / 79 assertions PASS**; central/preferências **39/250 PASS**; workers
diretos por origem **27/362 PASS**; execução combinada **66/612 PASS**. Runtime e SMTP externo não executados.

## 0.1.1-dev.233 — verificar políticas administrativas por origem

`AvisoPreferenciasTest` agora aplica ambas as restrições administrativas (desligar envio direto e desligar resumo)
uniformemente a Fórum, Aprendizagem, Wiki, Loja e Doações. Para cada categoria, a preferência incompatível do membro
falha fechada e não pode ser salva; o outro modo permitido continua disponível.

Validação: teste focal **16 testes / 45 assertions PASS**; PHPCS Drupal/DrupalPractice e PHP lint PASS. Runtime e e-mail
externo não executados.

## 0.1.1-dev.232 — provar idempotência da repetição do digest

O teste do digest agora percorre a falha temporária do Mailer e uma nova tentativa após a janela de transporte: o lote
permanece pendente, recebe a mesma correlação determinística e é confirmado uma vez após o envio aceito. A cobertura
fecha a lacuna entre testar apenas a pendência da falha e comprovar a chave idempotente do retry.

Validação: `AvisosResumoProviderPreferenceTest` **6/130 PASS**; `AvisosResumoMailerKernelTest` **4/70 PASS** em
MariaDB dedicado. Runtime e transporte de e-mail externo não executados.

## 0.1.1-dev.231 — cobrir revogação e bloqueios no aviso de mensagem privada

`PrivateMessageNotificationWorkerTest` agora comprova que a fila não cria aviso de e-mail após perder acesso à thread
e que bloqueio bilateral suprime a entrega nos dois sentidos. Nos três casos, nem o Mailer nem o Message Notify são
chamados. Essa cobertura verifica a autorização no momento de processar a fila, além da validação no formulário.

Validação: teste focal **4 testes / 82 assertions PASS**; conjunto Unit do Messenger e adapters LMS/Wiki/Commerce/Doação
**268 testes / 2.169 assertions PASS**. PHPCS Drupal/DrupalPractice e PHP lint PASS; Runtime e e-mail real não executados.

## 0.1.1-dev.230 — caracterizar revogação nos adapters de Loja e Doação

Os workers Commerce, Wiki e Doação ganharam cobertura explícita para o caso em que a revalidação de origem retorna
inacessível entre o enqueue e o envio: a cópia de e-mail é cancelada e o Mailer não é chamado. A bateria inclui o
estado terminal e complementa os Kernels de revogação de acesso LMS e Wiki.

Validação: `CommerceWikiNoticeEmailWorkerSafetyTest` + `DonationNoticeEmailWorkerTest` **17 testes / 222 assertions
PASS**; conjunto Unit do núcleo Messenger e adapters LMS/Wiki/Commerce/Doação **266 testes / 2.124 assertions PASS**;
PHPCS Drupal/DrupalPractice e PHP lint PASS. Sem SMTP ou Runtime.

## 0.1.1-dev.229 — encerrar envio LMS quando o acesso é revogado

Quando o worker LMS revalida uma inscrição sem acesso atual, marca a cópia de e-mail como `cancelled`, terminal para
replays do evento. Antes, `failed` com zero tentativas permitia que o replay reabrisse a fila. O aviso obrigatório no
site continua persistido e legível segundo as permissões atuais.

Validação: `LearningNoticePersistenceKernelTest` **4 testes / 52 assertions PASS** no MariaDB dedicado; as 2
deprecações são das annotations `message` e `message_template` do módulo contrib `message`. Não houve envio SMTP nem
acesso ao Runtime.

## 0.1.1-dev.228 — testar revogação antes do e-mail direto da Wiki

O Kernel agora cobre um aviso editorial enfileirado cuja revisão é publicada antes do processamento da fila. O worker
revalida a revisão, cancela somente o e-mail e mantém o aviso obrigatório no site. A microfase também auditou a
idempotência do digest: lock concorrente, correlação estável, recuperação transacional e boot Drupal independente já
estão cobertos sem exigir uma nova camada de persistência.

Validação: `WikiNoticePersistenceKernelTest` **8 testes / 152 assertions PASS** no MariaDB dedicado; as 2 deprecações
reportadas são das annotations `message` e `message_template` do módulo contrib `message`. `LearningNoticeDigestIntegrationTest`
**24 testes / 205 assertions PASS**; `AvisosResumoMailerKernelTest` **4 testes / 70 assertions PASS**. Runtime e SMTP
externo não executados.

## 0.1.1-dev.227 — evitar carregar mensagens de threads já lidas

`UnreadThreadCounter::isUnread()` agora compara primeiro `updated` com o histórico individual de acesso. Em threads sem
atividade posterior, retorna como lida sem executar o access handler, que no `private_message` instalado carrega e filtra
mensagens pelo corte de exclusão pessoal. Threads potencialmente não lidas continuam passando por access e pela inspeção
de mensagens antes de serem contadas.

Validação: `UnreadThreadCounterTest` **6 testes / 19 assertions PASS**; PHP lint e PHPCS Drupal/DrupalPractice PASS.
Não altera a contagem nativa nem faz consultas de leitura/escrita no Runtime.

## 0.1.1-dev.226 — cobrir central sem adapters opcionais

O dispatcher da central agora tem teste explícito de instalação sem os serviços opcionais LMS, Wiki, Loja e Doações. A
lista e o contador preservam seus dados centrais, sem instanciar adapters ausentes nem registrar erro.

Validação: `PersonalNoticeAdapterHooksTest` **8 testes / 73 assertions PASS**; PHP lint e PHPCS Drupal/DrupalPractice PASS.
Sem Runtime.

## 0.1.1-dev.225 — cobrir troca de digest para envio direto

O teste de preferência do digest agora cobre também a mudança de uma categoria para envio direto. A pendência dessa
categoria é descartada do resumo, sem chamar o Mailer pelo digest; a notificação do site permanece obrigatória. O mesmo
cenário também cobre opt-out.

Validação: `AvisosResumoProviderPreferenceTest` **5 testes / 103 assertions PASS**; PHP lint e PHPCS Drupal/DrupalPractice
PASS. Sem envio de e-mail ou acesso ao Runtime.

## 0.1.1-dev.224 — aplicar confirmação de e-mail a envios diretos integrados

Os workers diretos de LMS, Wiki e Loja agora revalidam `EmailConfirmationPolicy` antes de resolver o aviso ou chamar o
Mailer. Endereço não confirmado cancela somente a entrega por e-mail; o `Message` que mantém o aviso no site permanece
intacto. Isso alinha os três adapters à regra já aplicada ao Fórum, Doações e ao digest combinado.

Validação: Unit de LMS/Wiki/Loja **17 testes / 205 assertions PASS**; `WikiNoticeEmailQueueKernelTest` **2/54 PASS** no
MariaDB dedicado (2 deprecações de discovery `Message`/`message_template` do Core). PHP lint PASS. SMTP externo e Runtime
não executados.

## 0.1.1-dev.223 — caracterizar retry de digest após falha SMTP temporária

O Kernel integrado ao ACULTA Mailer agora força uma resposta SMTP 421 e depois aceita o retry controlado. O aviso
permanece pendente após a falha; a nova execução, depois da janela de segurança, reutiliza a correlação, atualiza o
mesmo Mail Log e confirma o delivery ID uma única vez.

Validação: `AvisosResumoMailerKernelTest` **4 testes / 70 assertions PASS** no MariaDB dedicado, incluindo os cenários
anteriores de aceite/recuperação e concorrência; teste novo **1/23 PASS**. O conjunto focal de digest anteriormente
revalidado passou **75/1.244**; com o novo Kernel, cobertura combinada executada nesta sequência: **76 testes / 1.267
assertions PASS**. SMTP externo e Runtime não executados.

## 0.1.1-dev.222 — coordenar retenção com gravações de conversas

A limpeza por retenção agora compartilha uma trava persistente por thread com os caminhos de envio de respostas e de
primeira mensagem em conversa existente. Depois de adquirir a trava, a retenção recarrega a thread e revalida sua idade
antes de excluir; uma conversa que esteja sendo atualizada é preservada para o próximo cron.

Validação: Unit de validação/envio e retenção **20 testes / 150 assertions PASS**; `PrivateMessageRetentionKernelTest`
**3/42 PASS** no MariaDB dedicado (10 deprecações Core/contrib) e `PrivateMessageFloodKernelTest` **2/21 PASS**.
Idempotência do digest revalidada em **75 testes / 1.244 assertions PASS** (Unit/Integration e Kernels Messenger/Mailer).
PHP lint, PHPCS Drupal/DrupalPractice para serviço/testes, validação YAML, índice e gate Portal Drupal 11+ (**1.155
checks**) PASS. Formulário e controller mantêm ocorrências PHPCS preexistentes fora dos trechos alterados. Nenhuma limpeza
Runtime ou transporte SMTP executados.

## 0.1.1-dev.221 — restringir métodos das rotas de abertura de avisos

As rotas de abertura dos avisos LMS, Wiki, Loja e Doações agora aceitam somente GET, conforme seus links de navegação. Isso impede que endpoints de leitura sejam chamados por métodos de escrita sem contrato. A revalidação de acesso à origem e a marcação como lido continuam no controller/manager existente.

Validação: `NoticeOpenRouteMethodTest` **1/8 PASS**, PHP lint, PHPCS Drupal/DrupalPractice e validação YAML dos quatro métodos PASS. Roteamento HTTP/Runtime não executados.

## 0.1.1-dev.220 — renderizar token CSRF nos formulários do drawer

Formulários de opções carregados como fragmentos agora geram o token de sessão da rota interna explicitamente. O placeholder lazy do gerador de URLs não era processado na resposta do drawer e fazia a rota protegida rejeitar o POST. A suíte de navegador passou a criar uma conversa em grupo com dois destinatários pela interface; os diagnósticos de fetch registram apenas caminho e presença de token, nunca seu valor.

Validação: suíte Firefox ESR/X11 no Runtime de teste **90/90 PASS**, incluindo POST de limpeza de histórico aceito (**HTTP 200**) e ciclo completo de privacidade/conversa; PHP lint, Node syntax, PHPCS de `setup-accounts.php` e `git diff --check` PASS. PHPCS do formulário reporta violações preexistentes espalhadas pela classe; nenhuma aponta para o método novo. Produção e SMTP não usados.

## 0.1.1-dev.219 — centralizar novos avisos de amizade no Mailer

Novos avisos de pedido e aceite de amizade passam a usar o tipo mínimo `friendship_notice` do ACULTA Mailer e uma
correlação determinística por registro persistido. O registro `Message` existente permanece como storage e chave de
idempotência. O marcador `field_aculta_mailer_managed` separa a nova entrega das mensagens de fila anteriores,
que continuam no Message Notify até a drenagem operacional. O update 10009 instala o campo aditivamente no bundle.

Validação: Unit Messenger **188 testes / 1.319 assertions PASS**; `FriendshipNoticePipelineKernelTest` **1 teste / 16 assertions PASS** no MariaDB dedicado (2 deprecações Core/contrib); PHPCS Drupal/DrupalPractice, PHP lint, índice de status e gate Drupal 11+ (**1.155 checks**) PASS. SMTP e Runtime não executados.

## 0.1.1-dev.215 — tornar idempotentes os e-mails diretos

Os workers de e-mail direto do Fórum, LMS, Wiki, Loja e Doações passam a compartilhar a geração de UUID v5 a partir da
identidade lógica do aviso e do tipo de e-mail. Retries de um mesmo aviso usam a mesma correlação, ativando o lock e a
deduplicação persistente do ACULTA Mailer. Antes de migrar um registro legado, o Messenger reconhece o aceite da antiga
correlação UUID v4 para o mesmo tipo e destinatário, quando ainda presente no Mail Log.

Validação: Unit Messenger + Integration Unit LMS/Wiki/Loja/Doações **248/1.882 PASS**; `WikiNoticeEmailQueueKernelTest`
**2/54 PASS** no MariaDB dedicado (2 deprecações de discovery `Message`/`message_template` do Core/contrib); PHPCS
Drupal/DrupalPractice, PHP lint, índice (41 componentes), gate Portal Drupal 11+ (**1155 checks**) e
`AcultaMailerKernelTest` **5/28 PASS**. SMTP e Runtime não executados.

## 0.1.1-dev.214 — preservar página ao atualizar leitura

Ao marcar um aviso como lido ou não lido, o retorno à central preserva o offset da origem selecionada. O formulário aceita
somente offsets inteiros não negativos e ignora offsets de outras origens ou parâmetros inválidos, mantendo os filtros
normalizados e a autorização revalidada pelo adapter.

Validação: `NoticeReadStateFormTest` **5/27 PASS**; Unit completo do Messenger **180/1.248 PASS**; PHPCS
Drupal/DrupalPractice, PHP lint, índice (41 componentes)
e gate Portal Drupal 11+ (**1155 checks**) PASS. Runtime e navegador não executados.

## 0.1.1-dev.205 — rejeitar payloads de resposta malformados

`drawerReply` deixa de converter valores não textuais em strings (por exemplo, um array enviado como `message[value]`, que
virava o texto literal `Array`). A rota retorna 400 sem cache para o payload malformado, antes de validar flood ou tocar
na conversa.

Validação: `MessagesControllerMemberAccessTest` **4 testes / 36 assertions PASS**; PHPCS Drupal/DrupalPractice no teste,
PHP lint e diff check PASS. O PHPCS do controller reporta violações preexistentes fora do trecho alterado. HTTP funcional
com sessão e Runtime não executados.

## 0.1.1-dev.204 — rejeitar identidades duplicadas de digest

O núcleo detecta adapters de digest sem identidade ou com identidade estável duplicada. Essas fontes ficam pendentes sem
participar do lote nem da recuperação, evitando que a confirmação de um snapshot use o provider errado; fontes com
identidade única continuam entregando normalmente. O contrato da identidade agora exige unicidade no site.

Validação: `LearningNoticeDigestIntegrationTest` **21/21 testes, 162 assertions PASS**; PHPCS Drupal/DrupalPractice,
PHP lint, `git diff --check` e índice de status PASS. Nenhuma mudança de storage. Runtime e transporte externo não
executados.

## 0.1.1-dev.203 — estabilizar conteúdo do digest entre adapters

O Messenger ordena adapters por identidade estável e as linhas retornadas por cada fonte antes de montar o e-mail. Uma
mudança na ordem de descoberta dos serviços ou das linhas duplicadas de um provider deixa de alterar o corpo mantendo a
mesma correlação idempotente. O adapter continua proprietário dos IDs confirmados, sem novo armazenamento.

Validação: `LearningNoticeDigestIntegrationTest` **18/18 testes, 137 assertions PASS**; PHPCS Drupal/DrupalPractice,
PHP lint e `git diff --check` PASS. A suíte emitiu aviso de diretório `sites/simpletest/browser_output` não gravável;
os testes Unit concluíram normalmente. Runtime e transporte externo não executados.

## 0.1.1-dev.197 — integrar o digest ao Mailer real em Kernel

Um Kernel de integração agora executa `AvisosResumo` e ACULTA Mailer reais no mesmo banco de teste: grava o envio
aceito no Mail Log, força falha no acknowledgement transacional e recupera o manifesto original. O teste confirma um
único transporte, destinatário e conteúdo esperados, acknowledgement final e nenhuma segunda linha de Mail Log.

Validação: `AvisosResumoMailerKernelTest` **1/18 PASS** no MariaDB dedicado; Unit Messenger **166/1.138 PASS**;
Integration Unit **36/300 PASS**; PHPCS Drupal/DrupalPractice e PHP lint PASS. SMTP e Runtime não executados.

## 0.1.1-dev.196 — contar somente novos envios de digest

`AvisosResumo::enviarResumos()` agora distingue novo transporte de recuperação de um digest que o Mailer já aceitou.
Reconciliações continuam confirmando o manifesto original, mas não são contabilizadas como e-mails enviados nesta execução
do cron.

Validação: Unit Messenger **166/1.138 PASS**; Integration Unit **36/300 PASS**;
`ForumNoticeIdempotencyKernelTest` **34/773 PASS** no MariaDB dedicado. PHPCS Drupal/DrupalPractice, PHP lint,
índice de status e `git diff --check` PASS. Transporte externo e Runtime não executados.

## 0.1.1-dev.195 — carregar revisões da Wiki em lote no contador

O contador não lido consulta UUIDs em lote e carrega as revisões referenciadas por revisão. Mantém a validação da fonte
atual, revisão padrão, estado editorial, titularidade, permissões do revisor e acesso a cada revisão.

Validação: Unit Messenger + Integration Unit LMS/Wiki/Loja/Doações **225/1.697 PASS**; `WikiNoticeManagerTest` **3/45 PASS**; `WikiNoticePersistenceKernelTest` **7/129 PASS** no MariaDB dedicado
(2 deprecações de discovery Core/contrib e 3 notices PHPUnit preexistentes); PHPCS Drupal/DrupalPractice e PHP lint
PASS. Runtime não executado.

## 0.1.1-dev.194 — carregar fontes de Doação em lote no contador

O contador de avisos não lidos agrupa os pedidos e pagamentos aprovados referenciados pelas mensagens pendentes. Revalida
uma vez cada pedido quanto ao tipo, titular e acesso atual, e confirma cada pagamento por estado e vínculo com o pedido.

Validação: `DonationNoticePersistenceKernelTest` **5/80 PASS** no MariaDB dedicado (2 deprecações de discovery Core/contrib e 1 notice PHPUnit preexistentes); Unit Messenger + Integration Unit
LMS/Wiki/Loja/Doações **224/1.670 PASS**; PHPCS Drupal/DrupalPractice e PHP lint PASS. Runtime não executado.

## Revalidação de idempotência do digest — 2026-10-24

`ForumNoticeIdempotencyKernelTest` **34/773 PASS** no MariaDB dedicado;
quatro cenários focais de correlação, retry, lease e contenção entre processos **4/99 PASS**. Dois workers Drupal completos
concorrendo pelo mesmo destinatário, Runtime e SMTP real continuam pendentes.

## 0.1.1-dev.193 — carregar pedidos da Loja em lote no contador

O contador de avisos não lidos reúne UUIDs distintos das mensagens elegíveis e carrega os pedidos da Loja em uma única
consulta. Revalida tipo, cliente, acesso atual e URL de cada pedido, preservando a contagem por aviso e evitando uma
consulta de origem por mensagem.

Validação: Unit Messenger + Integration Unit LMS/Wiki/Loja/Doações **224/1.670 PASS**; PHPCS Drupal/DrupalPractice
PASS. Runtime não executado.

## 0.1.1-dev.192 — rejeitar contagem negativa de adapter

O dispatcher considera contagem negativa uma falha do provider e não permite que ela diminua o total agregado. O erro é
isolado à origem e registrado sem dados do usuário.

Validação: Unit Messenger + Integration Unit LMS/Wiki/Loja/Doações **223/1.644 PASS**; PHPCS Drupal/DrupalPractice,
PHP lint e `git diff --check` PASS.

## 0.1.1-dev.191 — caracterizar o logger do dispatcher opcional

O contrato do ServiceProvider agora prova que o dispatcher é registrado mesmo sem adapters e recebe locator vazio junto
ao canal `logger.channel.aculta_messenger` esperado pela isolação de falhas.

Validação: `AcultaMessengerServiceProviderTest` **6/36 PASS**; Unit Messenger + Integration Unit LMS/Wiki/Loja/Doações
**222/1.636 PASS**; PHPCS, PHP lint e `git diff --check` PASS.

## 0.1.1-dev.190 — isolar inicialização lazy de adapters

O isolamento também cobre falhas ao resolver/construir um adapter no service locator, antes da chamada de listagem ou
contagem. A origem que falha é registrada por ID e as demais continuam contribuindo.

Validação: Unit Messenger + Integration Unit LMS/Wiki/Loja/Doações **222/1.634 PASS**; PHPCS Drupal/DrupalPractice,
PHP lint e `git diff --check` PASS. Sem Runtime/browser nesta validação.

## 0.1.1-dev.189 — isolar falhas dos adapters na central

Uma exceção durante a montagem de avisos descarta a contribuição parcial do adapter que falhou e não interrompe outras
origens. Falhas de contagem também são isoladas. O log registra somente classe do adapter, operação e classe da exceção,
sem UID ou conteúdo. Resultado vazio é tratado como válido.

Validação: Unit Messenger + Integration Unit LMS/Wiki/Loja/Doações **221/1.619 PASS**; PHPCS Drupal/DrupalPractice,
PHP lint e `git diff --check` PASS. Sem Runtime/browser nesta validação.

## 0.1.1-dev.188 — endurecer remoção de inscrições do Fórum

O teste de submit da privacidade agora tenta remover Flag de outro dono, outro tipo de Flag, alvo não-Node e Node fora
do bundle `aculta_forum_topic`. Só a Flag própria `seguir_topico` de tópico do Fórum pode ser removida.

Validação: `AvisosPrivacyFormTest` **6/61 PASS**; Unit Messenger + Integration Unit LMS/Wiki/Loja/Doações **219/1.597
PASS**; PHPCS Drupal/DrupalPractice, PHP lint e `git diff --check` PASS. Sem Runtime/browser nesta validação.

## 0.1.1-dev.187 — validar dispatch real dos hooks da central

O teste de contrato agora passa pelo HookCollector, instala o mapa produzido no `ModuleHandler` e executa os hooks via
`alter()`. Isso cobre descoberta, resolução do listener e agregação de avisos/contadores por adapters opcionais, além
dos testes diretos do dispatcher.

Validação: Unit Messenger + Integration Unit LMS/Wiki/Loja/Doações **219/1.597 PASS**; PHPCS Drupal/DrupalPractice,
PHP lint e `git diff --check` PASS. Sem Runtime ou navegador nesta validação.

## 0.1.1-dev.186 — registrar hooks de adapters na central

O Drupal só descobre hooks PHP em `src/Hook`; os quatro hooks antes armazenados em `src/Integration/*/Hook` não eram
chamados. Um dispatcher central descoberto pelo HookCollector agrega avisos pessoais e contadores dos adapters LMS,
Wiki, Loja e Doações quando presentes. O contrato compartilhado agora é `PersonalNoticeAdapterInterface`; os adapters
continuam opcionais e não adicionam armazenamento.

Validação: Unit Messenger + Integration Unit LMS **197/1.380 PASS**, incluindo execução do HookCollector real e
adapters múltiplos; PHPCS Drupal/DrupalPractice e PHP lint PASS. Sem Runtime ou navegador nesta validação.

## 0.1.1-dev.185 — preservar o aviso Wiki após opt-out de e-mail

Kernel MariaDB usa Queue API, Message e conta reais para confirmar que o modo de e-mail `off` cancela somente a entrega
direta, não chama Mailer e preserva o aviso obrigatório no site sem marcá-lo como lido.

Validação: `WikiNoticeEmailQueueKernelTest` **2/54 PASS** no MariaDB dedicado; duas deprecações de annotations do Core
para `message` e `message_template`. PHPCS/PHP lint PASS. Runtime e SMTP não executados.

## 0.1.1-dev.184 — caracterizar workers de adapters opcionais

Teste de contrato verifica que o hook de filas remove os workers de LMS, Wiki, Loja e Doações quando seus módulos estão
desabilitados e preserva os workers de integrações ativas e filas não relacionadas.

Validação: `OptionalQueueWorkerDefinitionsTest` **1/1 PASS**. Não executa cron nem altera filas persistidas.

## 0.1.1-dev.183 — exigir categoria válida no digest

O agregador rejeita linhas de providers sem categoria obrigatória antes de aplicar preferências por tipo. Um provider
malformado não pode contornar o modo de e-mail escolhido para a categoria; sua linha inválida é descartada do canal de
e-mail sem remover o aviso do site.

Validação: suíte Unit do Messenger + Integration Unit LMS **194/1.358 PASS**; PHPCS Drupal/DrupalPractice e PHP lint
PASS. Nenhum Runtime ou transporte de e-mail real foi executado.

## 0.1.1-dev.182 — estabilizar a correlação do digest entre adapters

Teste de regressão confirma que o mesmo lote de avisos produz a mesma correlação idempotente quando a ordem de
registro dos providers opcionais muda. Isso protege a deduplicação contra alterações de composição do container.

Validação: `LearningNoticeDigestIntegrationTest` **16/124 PASS**. Nenhum Runtime ou transporte de e-mail real foi
executado.

## 0.1.1-dev.181 — inventariar a integração editorial da Revista

O roadmap M5/MAG 0.5-H agora registra os pontos reais do Magazine (`revista`, workflow, `uid`, `co_authors`) e a
ausência de assinatura/publicador. A transição salva para `published` é o único evento candidato nesta análise; a
seleção de destinatários aguarda definição editorial. Não foi criado consumer, categoria paralela, inbox ou conversa.

Validação: revisão cruzada dos contratos Magazine/Messenger e do workflow versionado; `RevistaEditorialAccessKernelTest`
e `RevistaPublicationsTest` **13/506 PASS**; suíte Unit Messenger 158/1.058 PASS e Kernels de persistência
LMS/Wiki/Loja/Doações 18/289 PASS. O Kernel confirma workflow/access existente, não integração de avisos. Sem Runtime
e sem entrega de e-mail da Revista.

## 0.1.1-dev.180 — cobrir a política administrativa de e-mail entre categorias

O teste do formulário de privacidade verifica que todas as categorias integradas obedecem à mesma política de modos
permitidos pelo Messenger. Desabilitar envio direto na administração remove essa opção de cada categoria sem remover o
opt-out individual, digest ou os avisos obrigatórios no site.

Validação: formulário e serviços de preferência/configuração **12/76 PASS**; suíte Unit inteira do Messenger **158/1.058 PASS**; PHPCS Drupal/DrupalPractice, PHP lint, índice de status e `git diff --check` PASS. O smoke visual e a aplicação da configuração no Runtime continuam pendentes.

## 0.1.1-dev.179 — cobrir idempotência dos digests da Loja e Doação

Os Kernels de Loja e Doação verificam replay do evento, leitura repetida sem consumo e confirmação seletiva por
`delivery_id`. O cenário de Doação mantém o pedido criado e duas cobranças aprovadas como entregas distintas; confirmar
uma linha deixa as outras duas disponíveis.

Validação: Kernels Commerce/Donation **8/136 PASS**; Kernels LMS/Wiki/Commerce/Donation **18/289 PASS**; testes de
resumo combinado Forum/Mailer **58/1.001 PASS**. PHPCS Drupal/DrupalPractice, PHP lint e índice de status PASS.
Core/contrib emite 2 deprecações de descoberta de Message/Message Template; os Kernels dos quatro adapters registram
7 notices PHPUnit. SMTP e Runtime não executados.

## 0.1.1-dev.178 — deduplicar replay no digest Wiki

O Kernel Wiki agora reenvia o mesmo `MessengerNoticeEvent` de revisão antes da leitura do digest e prova que a API
reutiliza o Message persistido. A linha aparece uma vez e mantém seu `delivery_id`, completando a cobertura de replay,
acknowledgement e revogação do e-mail quando a revisão é publicada antes da entrega.

Validação: `WikiNoticePersistenceKernelTest` **6/106 PASS**; PHPCS Drupal/DrupalPractice e PHP lint PASS. Permanecem
2 deprecações de annotations Core/contrib e 3 notices PHPUnit; cron, SMTP e Runtime não foram executados.

## 0.1.1-dev.177 — validar digest e revogação na Wiki

O Kernel da integração Wiki agora prova o caminho de e-mail em modo digest com Node e Message reais: revisão em estado
`review` e permissões atuais aparecem como uma linha com `delivery_id`, e o acknowledgement marca somente o registro
selecionado. Se uma nova revisão for publicada antes do digest, o provider não devolve a linha e a descarta do canal de
e-mail. O aviso obrigatório no site permanece no Message.

Validação: `WikiNoticePersistenceKernelTest` **6/105 PASS**; PHPCS Drupal/DrupalPractice e PHP lint PASS. O Kernel reporta
2 deprecações conhecidas de annotations Core/contrib e 3 notices PHPUnit. Cron, SMTP e Runtime não foram executados.

## 0.1.1-dev.176 — cobrir IDOR nas ações por membro

O teste do controller usa os métodos de opções e relação por membro para confirmar que IDs manipulados não abrem ações
para uma conta externa à thread nem para o próprio usuário. Os métodos respondem com 404 antes de processar ações ou
carregar formulários.

Validação: `MessagesControllerMemberAccessTest` **1/16 PASS**; PHPCS Drupal/DrupalPractice e PHP lint PASS. A validação
HTTP com sessão e roteador completo permanece pendente.

## 0.1.1-dev.175 — exercitar autorização Core na resposta de conversa

O Kernel carrega a definição de produção `aculta_messenger.drawer_reply`, constrói a rota pelos access checks do Drupal
e usa entidades Private Message reais. Confirma que CSRF válido e associação à thread são necessários: membro com token
passa; token ausente/inválido e pessoa externa com token válido são negados. A rota permanece limitada a POST. O teste
não executa o roteador HTTP completo nem Runtime.

Validação: `PrivateMessageRouteAccessKernelTest` **1/12 PASS**; PHPCS Drupal/DrupalPractice e PHP lint PASS. O Kernel
emite 7 deprecações conhecidas de annotations/validators do Core/contrib.

## 0.1.1-dev.174 — provar rollback da retenção em banco real

O Kernel MariaDB agora injeta uma falha ao excluir o aviso Message após a exclusão de thread/mensagens ter começado.
O rollback confirma que os três registros permanecem no banco. Com a prova de sucesso da dev.173, a suíte cobre os dois
resultados do ciclo transacional em storages reais, sem executar limpeza no Runtime.

Validação: `PrivateMessageRetentionKernelTest` **2/29 PASS**; PHPCS Drupal/DrupalPractice e PHP lint dos fixtures PASS.
O Kernel emite 10 deprecações conhecidas de annotations/validators do Core/contrib. Runtime segue pendente.

## 0.1.1-dev.173 — caracterizar retenção em storage real

Adicionado Kernel MariaDB para a retenção de conversas com entidades reais de Private Message e Message. O ciclo do
Private Message remove thread e mensagens; o Messenger remove os avisos que apontam para mensagens excluídas, mantendo
threads, mensagens e avisos recentes. Os timestamps antigos são configurados apenas no banco isolado de teste porque
o campo `changed` do contrib atualiza `updated` ao salvar a thread. Nenhuma limpeza foi executada em dados reais.

Validação: `PrivateMessageRetentionKernelTest` **1/16 PASS**; PHPCS Drupal/DrupalPractice e PHP lint PASS. O Kernel
emite 10 deprecações conhecidas de annotations/validators do Core/contrib. Rollback em banco real e Runtime seguem
pendentes.

## 0.1.1-dev.172 — evitar starvation na retenção de conversas

A retenção paginada agora guarda um cursor de ID em State API. A ordenação ascendente avança além de threads que falham e reinicia quando chega ao fim, permitindo que lotes seguintes prossigam e que falhas sejam tentadas novamente no próximo ciclo. Cada thread e seus avisos Message continuam protegidos pela transação existente; nenhuma limpeza foi executada em dados reais.

Validação: `PrivateMessageRetentionHooksTest` 3/70 e Unit Messenger 156/1.036 PASS; PHPCS Drupal/DrupalPractice, PHP lint, índice e gate Drupal 11+ (1.155 checks) PASS. Kernel com storage real/Runtime ainda não executados.

## 0.1.1-dev.171 — validar CSRF Core nos quatro caminhos POST

A cobertura de `CsrfAccessCheck` agora constrói as rotas pelos quatro registros POST do YAML real, substitui seus parâmetros e verifica token ausente, inválido e válido para cada caminho. Mantém explícito que a execução HTTP completa ainda não foi exercitada.

Validação: `PostRouteCsrfRequirementTest` 2/35 e Unit Messenger 155/993 PASS; PHPCS Drupal/DrupalPractice e PHP lint focal, índice e gate Drupal 11+ (1.155 checks) PASS. HTTP/Runtime não executados.

## 0.1.1-dev.168 — exercitar verificação de CSRF do Core

O teste das quatro rotas POST agora também exercita `CsrfAccessCheck` do Drupal: token ausente e inválido são negados; token válido para o caminho com parâmetro de thread é permitido e não cacheável. Isso complementa a validação declarativa do YAML. Request HTTP/Runtime ainda pendentes.

Validação: `PostRouteCsrfRequirementTest` 2/10 PASS; PHPCS Drupal/DrupalPractice e PHP lint PASS.

## 0.1.1-dev.162 — carregar estados de Site Alerts em lote — 2026-10-24

A central filtra os Site Alerts por acesso antes de consultar Flags pessoais. Leitura e dispensa agora vêm de uma única
chamada `loadIsFlaggedMultiple()` para todas as entidades visíveis, em vez de duas consultas por alerta. A lista de
cache tags de Flagging permanece ligada à resposta; visitantes anônimos não consultam estado pessoal.

Validação: `NoticeFlagStateLoaderTest` **3/10 PASS**; `AcultaMessengerServiceProviderTest` + loader **7/24 PASS**; suíte Unit Messenger **147/914 PASS**.
PHPCS dos arquivos novos PASS; `MessagesController` passou de 96 para 95 erros PHPCS preexistentes, com avisos
inalterados (65). PHP lint, gate Drupal 11+ (1.155 checks), índice de status e `git diff --check` PASS. Runtime visual
não executado nesta microfase.

## 0.1.1-dev.161 — caracterizar Flood e lock de mensagens no Kernel — 2026-10-24

O novo Kernel usa o Flood de banco do Drupal, contas reais e backends persistentes independentes. Confirma o limite
configurado após dez submissões registradas e que uma segunda validação não alcança Flood enquanto a primeira mantém
o lock; depois que a primeira registra e libera o lock, uma nova validação pode prosseguir.

Validação: `PrivateMessageFloodKernelTest` **2/21 PASS**; PHPCS Drupal/DrupalPractice e PHP lint PASS. O teste exercita
serviços Kernel, sem enviar HTTP, criar mensagens privadas ou validar CSRF da rota.

## 0.1.1-dev.157 — carregar tópicos seguidos em lote — 2026-10-24

A lista de assinaturas e seu cancelamento múltiplo agora carregam os nodes com `loadMultiple()` da Entity API após
revalidar Flag, tipo e proprietário. Títulos e rotas continuam condicionados ao acesso atual; nodes ausentes ou de bundle
inesperado são ignorados. Isso evita um load individual por Flag sem adicionar storage ou contornar a API de remoção.

Validação: `AvisosPrivacyFormTest` **5/55 PASS**, incluindo uma única carga em lote para múltiplos tópicos na listagem e
na remoção; PHPCS Drupal/DrupalPractice e PHP lint PASS. Runtime visual ainda pendente.

## 0.1.1-dev.156 — serializar o rate limit de mensagens privadas — 2026-10-24

O formulário nativo mantém um lock persistente por remetente do início da checagem Flood até a persistência da mensagem.
Isso impede que envios concorrentes observem o mesmo contador antes de cada registro. Bloqueio de lock retorna a mensagem
genérica de limite; rejeição Flood e falha do backend liberam o lock, assim como o submit após persistência. O contador só
é registrado no submit já existente. Sem tabela nova ou alteração na política configurada.

Validação: `PrivateMessageFormValidatorTest`, `NewConversationRecipientIdsTest` e `PrivateMessageFormHooksTest`
**17/72 PASS**, cobrindo contenção, liberação em Flood negado/exceção e registro após persistência; PHPCS Drupal/
DrupalPractice e PHP lint PASS. Runtime e transporte real não usados.

## 0.1.1-dev.155 — recuperar digests aceitos mesmo sem adapter ativo — 2026-10-24

O cron inclui na descoberta de destinatários os snapshots de digest já aceitos pelo Mailer. Assim, a recuperação verifica a
correlação mesmo quando não há aviso pendente de outra origem. Se o adapter opcional do manifesto estiver desativado, o
snapshot é preservado e nenhuma segunda entrega nem confirmação de linhas alheias ocorre; quando o adapter voltar, o
manifesto pode ser confirmado sem reenviar o e-mail. A consulta usa `user.data` pelo nome exato da chave, sem novo storage.

Validação: Integration Unit de digest 13/100; suíte Messenger Unit + Integration Unit 195/1.353; Kernel MariaDB
`ForumNoticeIdempotencyKernelTest` **33/745 PASS**, incluindo `user.data` real e adapter indisponível; PHPCS
Drupal/DrupalPractice, PHP lint, gate Drupal 11+ (1.155 checks), índice e `git diff --check` PASS. Runtime/SMTP não usados.

## 0.1.1-dev.154 — gerenciar tópicos seguidos nas preferências — 2026-10-24

A página de privacidade lista os tópicos seguidos pela pessoa usando a Flag `seguir_topico` e permite parar de seguir
itens selecionados. Títulos e links só aparecem quando a pessoa ainda pode ver o tópico; itens indisponíveis aparecem
com rótulo genérico e continuam removíveis. O submit dedicado usa a API Flag, revalida proprietário/tipo/ID e não
invoca os handlers do formulário de perfil nem altera o acesso ao conteúdo.

Validação: `AvisosPrivacyFormTest` **5/49 PASS**; PHP lint, PHPCS Drupal/DrupalPractice, gate Drupal 11+ (1.155 checks), índice e `git diff --check` PASS. Validação via Symfony YAML não se aplica ao services.yml com `!tagged_iterator`; o container foi coberto pelo gate Drupal. Runtime visual não executado nesta microfase.

## 0.1.1-dev.153 — bloquear digests concorrentes durante transporte — 2026-10-24

Teste de integração reentra em um segundo `AvisosResumo::enviarResumos()` enquanto o primeiro está no transporte. O
lock persistente do destinatário impede a segunda entrega; o primeiro confirma seu lote e libera o lock.

Validação: `LearningNoticeDigestIntegrationTest` **12/92 PASS**, incluindo a execução concorrente no transporte;
PHPCS Drupal/DrupalPractice e PHP lint PASS. A contenção multiprocesso do backend segue coberta separadamente pelo
Kernel; dois boots completos de Drupal em processos concorrentes e transporte lento ainda não foram homologados.

## 0.1.1-dev.152 — identidade do digest como extensão compatível — 2026-10-24

A identidade estável passou para `AvisoDigestProviderIdentityInterface`, subinterface opcional. Providers existentes que
implementam somente `AvisoDigestProviderInterface` continuam válidos e usam fallback por classe; os quatro adapters
ACULTA declaram IDs estáveis. O teste cobre entrega por provider legado sem a nova extensão.

Validação: suíte focal Unit/Kernel MariaDB **47 testes / 889 assertions PASS**; PHPCS Drupal/DrupalPractice, PHP lint, YAML, `git diff --check` e índice de status PASS. Runtime/SMTP não usados.

## 0.1.1-dev.151 — provar recuperação após refactor do adapter — 2026-10-24

O Kernel agora simula uma implantação entre o aceite do e-mail e a confirmação: a segunda execução usa outra classe
PHP com o mesmo ID estável `wiki`, confirma o manifesto original sem reenvio e deixa o aviso posterior pendente.

Validação: `ForumNoticeIdempotencyKernelTest` **32/725 PASS**; PHP lint, PHPCS Drupal/DrupalPractice e diff check PASS.
Banco MariaDB dedicado de testes; Runtime e transporte real não usados.

## 0.1.1-dev.150 — identidade estável dos adapters no digest — 2026-10-24

Manifestos e correlações do digest agora identificam adapters por chave estável de origem (`lms`, `wiki`, `commerce`,
`donation`), em vez do nome da classe PHP. Refatorar ou proxyar a implementação não muda a identidade do lote nem
impede a recuperação de um aceite já registrado. A leitura ainda reconhece o campo `class` de snapshots já persistidos
antes desta versão, enquanto novos snapshots gravam somente `provider_id`.

Validação: Unit digest e Kernel MariaDB do Fórum/Mailer em conjunto, **46 testes / 886 assertions PASS**; PHPCS Drupal/DrupalPractice, PHP lint, `git diff --check` e índice de status PASS. Runtime/SMTP não usados.

## 0.1.1-dev.147 — estado vazio acessível na central — 2026-10-24

Avisos filtrados sem resultados agora usam a apresentação neutra `messenger-state` com `role="status"`, preservando a
mensagem orientada aos filtros. A central mantém decisão de vazio e consulta no módulo; tema mantém CSS e apresentação.

Validação: script visual focado no Firefox ESR visível (Runtime de teste): **4/4 verificações PASS**; PHP lint PASS.
MessagesController permanece no baseline PHPCS documentado (97 erros e 65 avisos preexistentes).

## 0.1.1-dev.146 — manter respostas em threads existentes — 2026-10-24

`Somente amigos` volta a valer somente para a criação de novas conversas. A visualização de threads existentes não
oculta mais o compositor quando a amizade termina; o bloqueio bilateral continua impedindo respostas. Removido o
estado visual de amizade que criava um bloqueio adicional fora do contrato.

Validação: suíte Unit Messenger 140 testes/857 assertions PASS, incluindo estado aberto após fim da amizade e bloqueio
por qualquer lado; testes existentes do formulário de nova conversa 11/39 PASS. PHPCS e PHP lint do serviço/teste
novos PASS. Runtime e Kernel de interface não executados.

## 0.1.1-dev.145 — reconciliar manifesto aceito do digest — 2026-10-24

Antes de enviar um digest, o Messenger persiste apenas correlação, instante e IDs/contadores da remessa, sem endereço ou
conteúdo. Se o Mailer registrou o aceite e uma confirmação de origem falhou, o retry confirma o manifesto original
atomicamente antes de considerar novos itens pendentes. Assim, novos eventos recebidos na janela de recuperação
permanecem para um resumo posterior e não alteram a correlação do e-mail já entregue. Se um adapter opcional estiver
indisponível, o manifesto fica retido e não causa reenvio duplicado.

Validação: `ForumNoticeIdempotencyKernelTest` 32/725 PASS, incluindo crescimento do lote após aceite e rollback;
Unit Messenger 138/848 PASS; Kernel Mailer 5/26 e Unit Mailer 157/317 PASS; índice PASS. Runtime e transporte real
não testados. O aceite externo anterior ao registro local continua fora da garantia.

## 0.1.1-dev.144 — digest exige idempotência persistida — 2026-10-24

O ACULTA Mailer agora mantém envios com correlação idempotente em estado temporário enquanto o log de aceite estiver
desativado. Isso preserva os itens de digest para retry e evita tratar um envio não deduplicável como sucesso.

Validação: Kernel do Mailer 5 testes/24 asserções PASS; `AvisosResumoProviderPreferenceTest` 4/63 PASS confirma que
falha temporária não descarta nem confirma IDs do provider; PHPCS e PHP lint do teste PASS. Kernel do Messenger e
Integration digest não foram executados neste incremento; Runtime e transporte externo não testados.

## 0.1.1-dev.143 — testar lock do digest entre processos — 2026-10-24

O Kernel MariaDB agora inicia processos PHP independentes contra o mesmo prefixo isolado e comprova o lock persistente do resumo: enquanto um processo mantém o lock, outro não consegue adquiri-lo; após a liberação, um terceiro processo consegue. A verificação usa apenas o banco dedicado de testes e não executa envio nem toca no Runtime.

Validação: `ForumNoticeIdempotencyKernelTest` 32 testes / 723 assertions PASS, incluindo o cenário multiprocesso; PHPCS Drupal/DrupalPractice e PHP lint dos dois arquivos novos/alterados PASS. O teste não simula dois digests completos disputando o transporte, lease expirada, SMTP real ou execução de cron.

## 0.1.1-dev.138 — distinguir gerações do digest do Fórum — 2026-10-24

A correlação do resumo do Fórum agora inclui a geração monotônica do tópico, calculada em uma consulta agrupada ao ledger de eventos já existente. Um evento novo no mesmo tópico e tipo recebe outra correlação mesmo quando a contagem pendente volta a 1; replay do mesmo evento continua usando a geração anterior. Não há tabela nova nem update de schema.

Validação: Kernel MariaDB `ForumNoticeIdempotency` 30/677 PASS, incluindo aprovação do Comment mais antigo depois do novo, com contagem pendente repetida e correlações distintas entre os dois digests. Unit Messenger 133/791 e Integration Messenger 64/672 PASS; PHPCS Drupal/DrupalPractice e PHP lint dos arquivos da implementação PASS. Integration reporta 2 deprecações de annotations Core/contrib e 7 notices PHPUnit. Gate Drupal 11+, índice e `git diff --check` PASS. Sem Runtime, transporte externo ou stress de processos independentes.

A central também ganhou caminhos positivos de leitura/releitura para Wiki e LMS no Kernel: ambos passam pelo `NoticeReadStateManager`, persistem o campo do `Message` real e negam alteração depois da revogação da fonte. Wiki usa revisão de Node real; LMS isola os objetos de matrícula/curso, mantendo manager, dispatcher, usuário e armazenamento `Message` reais. A suíte Integration após estes testes: 64/672 PASS, com 2 deprecações Core/contrib e 7 notices PHPUnit. Não houve Runtime/browser.

## 0.1.1-dev.137 — filtrar leitura antes do limite da central — 2026-10-24

A central agora encaminha os filtros normalizados aos adapters e aplica o estado lido/não lido na consulta antes do limite para Fórum, LMS, Wiki, Loja e Doações. Comunicados persistentes percorrem lotes access-aware até preencher a página de 50 itens correspondentes; alertas do Site Alert continuam usando seu estado nativo. A ordem atual da central foi preservada enquanto a preferência de ordenação permanece em aberto.

Validação: Unit Messenger 133/791, Kernel MariaDB ForumNoticeIdempotency 29/655 e Integration Messenger 62/622 PASS. PHPCS Drupal/DrupalPractice dos arquivos novos/alterados, lint e gate Drupal 11+ (1155 checks) PASS; MessagesController tem baseline preexistente de 98 erros/65 avisos e ficou em 97/65 após a alteração. Integration reporta 2 depreciações Core/contrib e 7 notices PHPUnit. Sem Runtime/navegador.

## 0.1.1-dev.136 — recuperar enqueue de avisos de amizade — 2026-10-24

Avisos de amizade agora possuem bundle aculta_msg_friendship e campos declarados na configuração de instalação; o update aditivo 10007 instala somente configurações ausentes. A fila registra tentativas de enqueue separadas das tentativas de e-mail, marca recusas como failed e o cron recupera registros pendentes/failed há pelo menos dez minutos, com lote de 50 e teto de cinco tentativas. Lock por Message evita enqueue simultâneo. O replay idempotente continua reutilizando o mesmo aviso.

Validação: FriendshipNoticeSubscriberTest 6 testes / 59 assertions, FriendshipNoticeQueueTest 3/50 e FriendshipBundleUpdateTest 1/3 PASS. Update ainda não aplicado no Runtime; falha após a quinta recusa fica visível como failed sem retry automático adicional. Suíte Unit Messenger 130/767, Kernel MariaDB ForumNoticeIdempotency 28/633, PHPCS dos arquivos alterados, PHP lint, parse de YAML, gate Drupal 11+ (1155 checks), PROJECT-STATUS-INDEX e git diff --check PASS. Runtime/update não executados.

## 0.1.1-dev.135 — recuperar fila recusada de amizade — 2026-10-24

O consumer de amizade agora verifica a resposta da Queue API. Se a fila recusar o item ou lançar erro, a mensagem
persistente passa a `failed` com zero tentativas, o Messenger registra o ID técnico e propaga a falha. O replay do mesmo
evento, sob a chave idempotente existente, encontra a mensagem e reenvia somente o item da fila, sem criar aviso
adicional. A falha por interrupção do processo entre salvar a mensagem e criar o item ainda não tem reconciliador
periódico.

Validação: `FriendshipNoticeSubscriberTest` 7/82 e suíte Unit Messenger 127/737 PASS; PHPCS Drupal/DrupalPractice e
PHP lint PASS. Regressão do digest no MariaDB: ForumNoticeIdempotency Kernel 28/633 PASS; deduplicação persistida
do Mailer: AcultaMailer Kernel 3/15 PASS. Sem Kernel específico da fila de amizade ou Runtime nesta microfase.

## 0.1.1-dev.134 — alinhar lease do digest ao transporte — 2026-10-24

O lock persistente por destinatário do digest agora usa lease de 180 segundos, alinhada aos workers de e-mail direto e
superior ao timeout de socket local conhecido de 60 segundos. Isso reduz a possibilidade de uma segunda execução
reivindicar o mesmo destinatário enquanto o transporte e a confirmação do lote ainda estão em andamento. Configurações
de transporte com duração superior a 180 segundos ainda exigem harmonização; lease não substitui teste com processos
concorrentes.

Validação: Unit Messenger 125/708 e `LearningNoticeDigestIntegrationTest` 9/74 PASS; PHPCS Drupal/DrupalPractice, PHP lint e gate Drupal 11+ PASS. Kernel multiprocesso e Runtime pendentes.

## 0.1.1-dev.133 — distinguir novos lotes de digest — 2026-10-24

A caracterização do resumo agora confirma que a mesma remessa pendente mantém sua correlação no retry após falha de
confirmação, enquanto um novo lote com `delivery_id` diferente recebe outra correlação. Isso permite ao ACULTA Mailer
suprimir somente a entrega já aceita, sem confundir eventos posteriores com o mesmo resumo.

Validação: `LearningNoticeDigestIntegrationTest` 9/74 PASS; PHPCS Drupal/DrupalPractice e PHP lint PASS. O Kernel
MariaDB do digest/Forum mantém cobertura anterior de lock entre instâncias, confirmação transacional e retry; não houve
prova com processos independentes nem envio por transporte real nesta microfase.

## 0.1.1-dev.132 — tornar retenção de conversas recuperável — 2026-10-24

A exclusão de uma conversa inativa e dos avisos Message que apontam para suas mensagens agora ocorre em uma transação
na conexão padrão do Drupal. O worker captura os IDs antes do ciclo de exclusão do Private Message, remove primeiro a
thread e só então apaga os avisos relacionados; falhas revertem a transação e deixam os avisos intactos. O acesso ao
campo `updated` usa a API de entidade, sem depender de método não declarado pela interface da thread. Nenhuma limpeza
foi executada em dados reais.

Validação: Unit Messenger 125/708 PASS, incluindo ordem de exclusão e rollback; PHPCS Drupal/DrupalPractice e PHP lint
nos arquivos alterados PASS. Não houve execução de cron, Kernel de banco real nem homologação Runtime.

## 0.1.1-dev.131 — validar elegibilidade de destinatários de conversa — 2026-10-24

A busca da gaveta e o envio de primeira mensagem agora usam a mesma política: o destinatário precisa estar ativo e ter
as permissões `use private messaging system` e `access user profiles`. O endpoint de início pelo perfil também revalida
essas condições; IDs enviados manualmente não contornam a validação. Bloqueios e preferência de amizade continuam sendo
validados separadamente.

Validação: Unit Messenger 123/681 PASS, incluindo busca, envio com ID manipulado e permissões ausentes; PHPCS Drupal/
DrupalPractice limpo nos novos testes e em `ConversationPolicy`; PHP lint e gate Drupal 11+ (1155 checks) PASS. PHPCS
dos arquivos maiores de formulário/controlador continua apontando erros e avisos preexistentes fora das linhas alteradas.
Sem homologação Runtime.

## 0.1.1-dev.130 — manter filtros únicos nos links da central — 2026-10-24

Ao abrir um aviso e voltar à central, a origem e o estado de leitura selecionados agora substituem parâmetros de filtro
anteriores na URL, preservando os demais parâmetros, sem acumular `origem` ou `leitura` duplicados.

Validação: `NoticeListFilterTest` 4/7 PASS; suíte Unit Messenger 120/661 PASS; PHP lint e PHPCS Drupal/DrupalPractice nos arquivos alterados PASS.

## 0.1.1-dev.129 — correlação idempotente para retries do digest — 2026-10-24

O Messenger identifica um digest pelo UID, HMAC do destinatário e IDs/tipos/quantidades do lote, sem incluir títulos
ou texto de usuário na chave. Se o Mailer já registrou a mesma entrega lógica como aceita, um retry retorna sucesso
sem enviar novamente. O Messenger confirma todos os adapters e o intervalo do resumo dentro de uma transação da
conexão padrão do Drupal; falha parcial reverte a confirmação inteira e preserva o mesmo lote/correlação para retry.

Validação: teste de retry após falha de confirmação mantém a correlação; Kernel Mailer confirma que correlação aceita
não é transportada duas vezes; Forum Kernel comprova no MariaDB rollback entre dois storages e retry com a mesma
correlação. Unit Messenger 120/660, Integration MariaDB 61/616, Forum Kernel 28/633, Mailer Unit 157/317 e
Mailer Kernel 42/193 PASS. A chave usa HMAC do `hash_salt` para não expor endereços nem IDs em correlações. A proteção depende do log do Mailer
ativo e persistido; não cobre perda do registro após aceitação no provedor. Integration registra 2 deprecações
Core/contrib e 7 notices PHPUnit.

## 0.1.1-dev.128 — robustecer destinatários de grupo e retry do digest — 2026-10-24

Quando um provider falha ao montar suas linhas, o resumo ainda entrega as demais origens, confirma somente os itens
entregues e não atualiza o intervalo global da conta. Assim, a origem com falha pode ser tentada no próximo cron,
sem aguardar 20 horas nem perder os avisos no site. A entrada de destinatários de conversas em grupo também passa por
validação estrita de inteiros positivos e remoção de duplicatas, sem coerções de valores malformados.

Validação: Unit Messenger 120/660 e Integration Messenger 60/602 PASS; Integration inclui 2 deprecações de
annotations Core/contrib e 7 notices PHPUnit. PHPCS Drupal/DrupalPractice do teste novo, PHP lint, índice de status e
`git diff --check` PASS. PHPCS do formulário completo ainda aponta violações preexistentes fora do trecho alterado.

## 0.1.1-dev.127 — provar isolamento de titularidade na leitura LMS/Wiki — 2026-10-24

Kernels MariaDB persistem avisos LMS e Wiki em `Message` e confirmam que outra conta não consegue alterar seus campos
nativos de leitura. A negativa ocorre antes de resolver a fonte de conteúdo, exercitando a proteção de titularidade;
os fluxos positivos de leitura com fonte/permissões reais seguem pendentes para esses dois adapters.

Validação: LMS Kernel 3/30; Wiki Kernel 3/31; Integration Messenger 60/602 PASS. PHPCS Drupal/DrupalPractice e
PHP lint PASS. A suíte Integration reporta 2 deprecações de annotations Core/contrib e 7 notices PHPUnit.

## 0.1.1-dev.126 — comprovar persistência de leitura dos avisos da Loja — 2026-10-24

Kernel MariaDB cobre `CommerceOrderNoticeManager::setRead()` com Message persistido: marca e desmarca o campo nativo,
revalida titularidade e acesso ao pedido, e rejeita alteração por outra conta sem modificar o estado. A fixture usa
`DomainPurposeManager` e `CheckoutSettings` inicializados com dependências Drupal, sem objeto parcialmente inicializado.

Validação: Kernel Comércio 3/40; Integration Messenger 58/586; PHPCS Drupal/DrupalPractice, PHP lint e
`git diff --check` PASS. A suíte Integration reporta deprecações de annotations Message no Core/contrib e notices
PHPUnit (2 deprecações e 6 notices na suíte completa). Runtime visual e Kernels LMS/Wiki continuam pendentes.

## 0.1.1-dev.125 — comprovar persistência de leitura de avisos de Doações — 2026-10-24

Kernel MariaDB cobre `DonationNoticeManager::setRead()` com a entidade Message persistida: marca e desmarca o campo
nativo de leitura, revalida pedido e destinatário, e rejeita outra conta sem alterar o registro. A fixture agora usa
`DomainPurposeManager` com dependências reais/mockadas, sem reflection em objeto parcialmente inicializado.

Validação: teste Kernel Doações 3/35 (inclui 1/13 para leitura), Integration Messenger 57/569,
PHPCS Drupal/DrupalPractice, PHP lint e `git diff --check` PASS. Duas deprecações de annotations Message e um notice
Integration reporta duas deprecações do Core/contrib e cinco notices PHPUnit. Runtime visual e demais adapters continuam pendentes.

## 0.1.1-dev.124 — isolar falhas de providers e destinatários no digest — 2026-10-24

Uma falha na descoberta de destinatários ou na montagem do digest de um adapter não interrompe o cron
para os demais providers e contas. O Messenger registra somente o tipo da exceção e do provider, sem destinatário,
conteúdo ou mensagem da exceção. As pendências da origem com falha permanecem recuperáveis. Se o Mailer aceitar o
e-mail e a confirmação posterior do provider falhar, permanece a janela inevitável de retry duplicado; isso requer
idempotência/consulta de entrega compartilhada pelo Mailer para ser eliminada.

Validação: `AvisosResumoProviderPreferenceTest` — 3 testes / 41 assertions PASS; suíte Unit Messenger 118/648;
PHPCS Drupal/DrupalPractice e
`git diff --check` PASS. Ainda não exercitados Mailer real, múltiplos processos ou Runtime.

## 0.1.1-dev.123 — tornar falha permanente terminal também em replay — 2026-10-24

- Workers diretos LMS, Wiki, Loja e Doações persistem `failed_permanent`; os
  estados temporários `failed` continuam retryáveis. Itens duplicados e eventos
  repetidos não reabrem falhas permanentes. O valor foi adicionado ao campo
  `list_string` compartilhado pelo `update_10006`, idempotente, e à configuração
  de instalação. O Fórum já usa estado terminal próprio no outbox.
- Unit 117/637, Integration MariaDB 56/556 e Kernel MariaDB 27/609 PASS;
  PHPCS Drupal/DrupalPractice PASS. A Integration suite reporta 2 deprecações Core/contrib e 4 notices
  PHPUnit. Não houve Runtime, SMTP real ou stress multiprocesso.

## 0.1.1-dev.122 — respeitar falhas permanentes nas filas diretas — 2026-10-24

- Workers diretos de Fórum, LMS, Wiki, Loja e Doações encerram como `failed`
  erros permanentes do Mailer sem consumir retries restantes. Cancelamentos
  continuam terminais; erros temporários/exceções mantêm retry limitado.
- Unit Messenger 116/626 e Integration 52/532 PASS; Kernel 27/609 PASS na
  rodada anterior da mesma frente. PHPCS Drupal/DrupalPractice PASS. Integração
  mantém 2 deprecações Core/contrib e 4 notices PHPUnit. Sem SMTP real.

## 0.1.1-dev.121 — encerrar digest após falha permanente — 2026-10-24

- Erro permanente retornado pelo ACULTA Mailer encerra a pendência somente no
  canal de e-mail, assim como cancelamento por política. Contadores obrigatórios
  de aviso no site permanecem intactos. Falhas temporárias continuam retryable.
- Unit 115/606, Kernel MariaDB 27/609 e Integration 48/476 PASS; PHPCS
  Drupal/DrupalPractice PASS. Integração registra 2 deprecações Core/contrib e
  4 notices PHPUnit. Sem SMTP real ou teste de processos independentes.

## 0.1.1-dev.120 — comprovar retry do digest combinado entre adapters — 2026-10-24

- Teste integrado combina linhas LMS e Wiki num único resumo. Uma falha
  temporária preserva as duas pendências; no retry bem-sucedido, somente então
  cada provider confirma seu próprio `delivery_id`.
- Integrações Messenger: 47 testes / 471 assertions PASS, com duas deprecações
  de descoberta de entidades Message no Core/contrib e quatro notices PHPUnit.
  PHPCS Drupal/DrupalPractice do teste PASS. Não usa SMTP nem processos separados.

## 0.1.1-dev.119 — permitir alterar leitura na central — 2026-10-24

- A central de Avisos oferece ações para marcar como lido/não lido mensagens de
  LMS, Wiki, Loja e Doações. Cada adapter revalida destinatário e acesso e usa
  seu campo de leitura existente; adapters opcionais ausentes falham fechados.
  Fórum, comunicados e Site Alert mantêm suas ações nativas. Sem storage novo.
- Unit direcionado: 11 testes / 63 assertions PASS; PHPCS Drupal/DrupalPractice
  dos serviços e testes novos PASS. Kernel de persistência e revisão visual no
  Runtime ainda pendentes. A suíte Kernel regressiva `ForumNoticeIdempotency` passou (26/587), mas não exercita especificamente a persistência do toggle.

## 0.1.1-dev.118 — verificar retry do e-mail Wiki pela fila Drupal — 2026-10-24

- Kernel MariaDB combina `WikiNoticeEmailWorker` com `DatabaseQueue`: exceção do
  Mailer mantém o item, o claim espera a lease e, expirado o item de teste, a
  nova tentativa confirma `sent` e remove a fila. A chamada é direta ao worker;
  o loop do Core Cron e concorrência entre processos ainda não foram testados.
- Kernels Messenger/adapters: 35 testes / 712 assertions PASS, com 2
  deprecações de descoberta Message e 4 notices PHPUnit. Unit: 144/888 PASS;
  PHPCS Drupal/DrupalPractice, PHP lint e `git diff --check` PASS. Sem SMTP real.

## 0.1.1-dev.117 — caracterizar exceções de privacidade para equipe — 2026-10-24

- Testes Unit garantem que administradores e editores podem iniciar conversas
  com membros que restringiram novas conversas a amizades e que a exceção não
  permite contornar um bloqueio bilateral. Unit Messenger + integrações: 144
  testes / 888 assertions PASS; PHPCS Drupal/DrupalPractice, PHP lint e
  `git diff --check` PASS. Sem Kernel/Runtime de formulário.

## 0.1.1-dev.116 — uniformizar lease dos workers de e-mail direto — 2026-10-24

- Os workers diretos de Fórum, amizade, mensagens privadas, LMS, Wiki, Loja e
  Doações usam lease compartilhada de 180 s, acima do timeout de socket PHP
  local (60 s) e com margem para persistir o resultado antes de outro worker
  poder enviar a mesma mensagem. O valor evita que locks curtos de 30/60 s
  expirem enquanto o transporte ainda está bloqueado.
- Unit Messenger + integrações: 142 testes / 876 assertions PASS; Kernel
  regressivo dos adapters: 34 testes / 677 assertions PASS (2 deprecações do
  Core/contrib e 4 notices). PHP lint e `git diff --check` PASS. PHPCS apontou violações preexistentes de comentários
  na classe de mensagens privadas, sem relação com as linhas alteradas. Sem
  transporte real, workers concorrentes de SO ou Runtime.

## 0.1.1-dev.115 — testar retry e liberação do lock após exceção — 2026-10-24

- O teste integrado provoca uma exceção no primeiro envio, verifica liberação
  do lock e confirma que a mesma linha é enviada e reconhecida na tentativa
  seguinte. Unit Messenger + adapters: 142 testes / 876 assertions PASS;
  PHPCS Drupal/DrupalPractice e PHP lint PASS. Transporte permanece controlado.

## 0.1.1-dev.114 — cobrir lock do digest entre requisições — 2026-10-24

- Kernel MariaDB usa um segundo `PersistentDatabaseLockBackend` sobre a mesma
  tabela semaphore durante a chamada ao Mailer. A execução concorrente não envia
  outro resumo, e o evento novo recebido durante o primeiro envio permanece
  pendente. ForumNoticeIdempotency: 26 testes / 587 assertions PASS.
- Ainda não é teste com processos de SO independentes; o stress multiprocesso e
  o transporte lento/real permanecem pendentes.

## 0.1.1-dev.113 — exigir ID estável nos itens do digest — 2026-10-24

- O Messenger não envia linhas de providers sem `delivery_id` inteiro positivo;
  descarta a pendência de e-mail inválida para evitar repetição a cada digest.
  O aviso obrigatório no site continua independente.
- Unit Messenger + adapters: 142 testes / 867 assertions PASS. PHPCS
  Drupal/DrupalPractice e `git diff --check` PASS. Kernel não repetido nesta
  microfase; Runtime e transporte real não testados.

## 0.1.1-dev.112 — manter o digest recuperável após exceção do Mailer — 2026-10-24

- Exceções lançadas pelo transporte não interrompem o restante do cron nem
  confirmam pendências; o lock por destinatário é liberado e as linhas ficam
  prontas para retry. O log registra somente a classe da exceção, sem endereço,
  conteúdo do aviso ou mensagem do transporte.
- Unit Messenger + adapters: 141 testes / 862 assertions PASS; PHP lint,
  PHPCS Drupal/DrupalPractice, YAML de serviços e `git diff --check` PASS.
  Nenhum envio SMTP real ou Runtime.

## 0.1.1-dev.111 — confirmar apenas os itens incluídos no digest — 2026-10-24

- O contrato dos digest providers inclui IDs dos Messages efetivamente incluídos
  no resumo; após sucesso, cada adapter confirma somente esses IDs e valida
  proprietário/tipo. Mensagens que chegam durante o transporte permanecem para
  o próximo digest. Fórum recarrega os agregados dentro do lock por destinatário
  e desconta o lote entregue sob o mesmo lock de escrita do agregado, evitando
  perder incrementos concorrentes. Opt-out e revogação limpam somente a
  pendência de e-mail, preservando os avisos do site.
- Unit Messenger + adapters: 140 testes / 856 assertions PASS. Kernels
  Messenger/adapters em MariaDB isolado: 34 testes / 681 assertions PASS,
  incluindo evento Fórum recebido durante a entrega; 2 deprecações de descoberta
  de entidades Message no Core/contrib. Runtime e transporte real não testados.

## 0.1.1-dev.110 — filtros de origem e leitura na central — 2026-10-24

- A aba Avisos oferece filtros GET de origem e estado de leitura sobre a lista
  agregada; links preservam o outro filtro. O Messenger aplica-os depois dos
  adapters, mantém todos os itens sem filtro na gaveta e informa resultado vazio
  específico. LMS, Wiki, Loja e Doações marcam origem/leitura no render array.
- Unit Messenger + adapters: 140 testes / 852 assertions PASS, incluindo 4 testes
  / 6 assertions do filtro. PHPCS limpo para classes novas; controllers mantêm
  baseline preexistente sem aumento de violações. PHP lint, YAML e status index
  PASS. Sem Runtime visual.

## 0.1.1-dev.109 — política administrativa dos modos de e-mail — 2026-10-24

- Configurações do Messenger agora permitem restringir por categoria os modos
  de resumo diário e envio direto. A opção individual “sem e-mail” permanece
  disponível e os avisos no site não podem ser desativados. Preferências antigas
  de modo bloqueado ficam inativas enquanto a política o proíbe e voltam a valer
  quando o administrador liberar o modo. Unit Messenger + adapters: 136 testes /
  846 assertions PASS; Kernel ForumNoticeIdempotency: 26 / 581 PASS. Os 12 testes
  direcionados de preferências/digest somaram 102 assertions. PHPCS Drupal/
  DrupalPractice, lint PHP, parse de YAML e índice de status PASS. Runtime não
  alterado; falta smoke administrativo no Runtime.

## 0.1.1-dev.108 — serializar criação dos avisos de amizade — 2026-10-24

- `FriendshipNoticeSubscriber` usa lock pela chave idempotente antes da busca e
  criação do Message. Replays simultâneos aguardam, adquirem o lock e observam
  o registro criado pela primeira execução. O lock também é injetado pelo
  serviço Drupal; Unit do subscriber: 5 testes / 53 assertions.
- Unit Messenger + adapters: 132 testes / 837 assertions PASS. PHPCS
  Drupal/DrupalPractice, PHP lint e `git diff --check` PASS. Runtime/fila não
  homologados.

## 0.1.1-dev.107 — serializar persistência dos avisos LMS — 2026-10-24

- `LearningNoticeManager::record()` adquire lock pela tupla destinatário, tipo,
  origem e versão antes da consulta/criação e do enqueue. Replays de itens
  pendentes não enfileiram e-mails novamente; falha de enqueue pode ser
  reprocessada sem criar outro Message.
- Unit do lock: 1 teste / 8 assertions; Unit Messenger + adapters: 131 / 821
  PASS. Kernel LMS: 2 / 22 PASS, incluindo enqueue único após replay, com duas
  deprecações de descoberta de entidades `message` do Core/contrib. PHPCS,
  PHP lint e `git diff --check` PASS. Runtime e fila operacional não homologados.

## 0.1.1-dev.106 — serializar e-mail de amizade no worker da fila — 2026-10-24

- `FriendshipNoticeEmailWorker` agora adquire lock por aviso antes de carregar o
  Message. Replays concorrentes leem estado e tentativas atuais, impedindo dois
  e-mails para o mesmo evento; falhas mantêm o limite de três tentativas.
- Unit do worker: 5 testes / 61 assertions; Unit Messenger + adapters:
  130 testes / 813 assertions PASS. PHPCS Drupal/DrupalPractice, PHP lint e
  `git diff --check` PASS. Runtime e fila operacional não homologados.

## 0.1.1-dev.105 — encerrar digest suspenso pela política administrativa — 2026-10-24

- `AvisosResumo` agora usa o resultado tipado do ACULTA Mailer. Quando a política
  cancela o envio, remove somente as pendências de e-mail do resumo e preserva
  os avisos obrigatórios no site; falhas temporárias continuam pendentes para
  retry. Integração cobre cancelamento, falha temporária e entrega aceita.
- Unit Messenger + adapters: 129 testes / 806 assertions PASS. Kernel
  `ForumNoticeIdempotency`: 26 testes / 581 assertions PASS. PHPCS
  Drupal/DrupalPractice, PHP lint e `git diff --check` PASS. Nenhum Runtime,
  configuração de produção ou transporte real foi alterado.

## 0.1.1-dev.104 — persistir correlação UUID do e-mail direto do Fórum — 2026-10-24

- O outbox do Fórum gera um UUID v4 ao reivindicar pela primeira vez uma
  tentativa e o preserva para replays. `ForumNoticeEmailWorker` envia esse
  correlation ID aceito pelo contrato do ACULTA Mailer; exceção do transporte
  atualiza o estado retryable/terminal conforme o contador do ledger existente.
- Update aditivo `10005` cria a coluna no ledger atual, sem nova tabela. Unit do
  worker: 2 testes / 33 assertions; Kernel de update idempotente e correlação
  estável entre claims: 2 / 39 PASS. Kernel completo ForumNoticeIdempotency:
  26 testes / 581 assertions; Units Messenger + adapters: 126 / 765 PASS.
  Runtime não atualizado.

## 0.1.1-dev.103 — encerrar retries de LMS e Doações com estado terminal — 2026-10-24

- Exceções de transporte do Mailer agora consomem tentativas em LMS e Doações.
  A fila reprocessa falhas somente abaixo do limite; após a última tentativa,
  o estado fica `failed` terminal. O worker de Doações também encerra itens
  legados que permaneçam `pending` com o limite já esgotado.
- Unit Messenger + adapters: 125 testes / 744 assertions PASS. PHPCS
  Drupal/DrupalPractice, PHP lint e `git diff --check` PASS. Runtime, Queue API
  operacional e transporte real não foram homologados.

## 0.1.1-dev.102 — limitar retries e impedir replay de e-mail direto — 2026-10-24

- Workers LMS, Wiki e Loja agora adquirem o lock antes de carregar o Message;
  itens duplicados leem o estado persistido atualizado. Estado `sent` e limite
  de tentativas esgotado encerram o processamento sem novo envio.
- Loja e Wiki passam a contabilizar exceções do Mailer antes de reencaminhar
  falhas transitórias; após o limite, o aviso do site permanece e o e-mail fica
  `failed`. Unit Messenger + integrações: 123 testes / 714 assertions PASS.
  PHPCS Drupal/DrupalPractice, PHP lint e `git diff --check` PASS. Runtime,
  SMTP real e concorrência em processos separados não foram testados.

## 0.1.1-dev.101 — serializar o resumo combinado por destinatário — 2026-10-24

- `AvisosResumo` adquire um lock persistente individual antes de verificar a
  janela de envio; uma execução concorrente ignora o UID já reivindicado. O
  lock é liberado em `finally`, inclusive quando o Mailer falha. Lease de 120 s
  reduz duplicação em cron concorrente; não prova exclusão entre servidores
  após expiração da lease nem garante exactly-once no transporte.
- Unit de preferência/contenda e integração LMS: 4 testes / 30 assertions
  PASS. Kernel ForumNoticeIdempotency MariaDB: 25 testes / 560 assertions
  PASS. PHPCS Drupal/DrupalPractice, PHP lint, `git diff --check` e índice de
  status PASS. Runtime e transporte real não executados.

## 0.1.1-dev.100 — endurecer valores do envelope de eventos — 2026-10-24

- `MessengerNoticeEvent` agora rejeita caracteres ASCII de controle nos UUIDs
  de origem, revisões/versões, chaves de idempotência e valores textuais de
  metadados, além dos limites de tamanho e formato já existentes.
- Teste Unit cobre LF, CR e NUL nos campos do contrato. Messenger Unit e
  publishers LMS, Doação, Loja e Wiki: 105 testes / 491 assertions PASS.
  PHPCS Drupal/DrupalPractice, PHP lint e `git diff --check` PASS. Runtime não
  executado.

## 0.1.1-dev.99 — integrar eventos de doação ao núcleo — 2026-10-24

- `aculta_messenger` registra o consumer opcional de Doações, persistindo
  `donation.order_placed` e `donation.payment_approved` em Message compartilhada,
  com dedupe, central, contador, leitura/revalidação e Mailer direto/digest.
  Repetição do mesmo pagamento não cria segundo aviso; pagamentos recorrentes
  distintos geram avisos próprios. A categoria de e-mail `doacao` permite
  desligado, direto ou resumo, sem desligar avisos do site.
- Worker direto usa o UUID v4 do Message como correlação exigida pelo Mailer e
  revalida preferência, conta confirmada e acesso atual antes do envio.
- Kernel MariaDB de Doações: 2 testes / 22 assertions PASS para ambos eventos,
  dedupe, múltiplas cobranças e rejeição de pedido inacessível. Kernels Messenger (Forum/Wiki/Donation): 29/605
PASS; 2 deprecações de discovery Message Core/contrib. Units Messenger 92/455,
Donation 24/84 e worker direto 2/27 PASS. PHPCS, lint, YAML e status index
  PASS. Sem operação no Runtime, envio real ou sandbox financeiro.

## 0.1.1-dev.98 — provar transferência persistente de avisos do Fórum — 2026-10-24

- `ForumNoticeIdempotencyKernelTest` agora altera um Comment persistido para o
  tópico de destino, dispara `ForumCommentSubtreeMovedEvent` pelo subscriber
  compartilhado e comprova mesclagem do agregado, watermark e dedupe no ledger.
- Kernel MariaDB: suíte do ledger 25 testes / 560 assertions PASS; cenário
  focal 1/27; PHPCS Drupal/DrupalPractice PASS. A prova não substitui integração
  do mover completo nem valida outbox/e-mail.

## 0.1.1-dev.97 — centralizar consumidores de avisos — 2026-10-24

- Consumidores, managers, hooks, rotas de abertura, workers de e-mail e
  configurações Message para LMS, Wiki e Loja foram movidos para
  `aculta_messenger`. Os publishers e a seleção de destinatários permanecem
  nos módulos que possuem os respectivos conteúdos e workflows.
- O núcleo registra adapters apenas quando encontra serviços dos módulos
  produtores. O update aditivo `10003` instala defaults centralizados sem
  sobrescrever configuração existente. Não foi executado no Runtime.
- Os antigos submódulos `*_messenger` tiveram serviços, rotas, classes, testes e
  configurações removidos após a migração. Permanecem manifests ocultos sem
  execução para preservar a identidade das extensões já habilitadas; não há
  uninstall/alteração de dados no Runtime.
- Unit integrada: 126 testes / 657 assertions PASS, incluindo provider e rotas
  de adapters opcionais. Kernels MariaDB de LMS, Wiki e Loja: 6 testes / 64 assertions
  PASS. PHPCS Drupal/DrupalPractice nos arquivos migrados PASS; PHP lint e
  `git diff --check` PASS. Runtime recebeu rebuild de cache e smoke HTTP após correção; não homologado
  como Alpha.
- Decisão normativa: Messenger centraliza mensagens, avisos e orquestração de
  e-mail; Mailer permanece dono do transporte e infraestrutura de envio.

## 0.1.1-dev.96 — entrega direta configurável para avisos do Fórum — 2026-10-24

- A preferência de cada categoria do Fórum agora seleciona sem e-mail, resumo
  diário ou envio direto; os avisos na central continuam obrigatórios. O resumo
  geral continua controlando o digest, e o ACULTA Mailer aplica as políticas
  administrativas de entrega.
- O outbox direto reutiliza o ledger idempotente existente e registra estado,
  tentativas e lease na mesma tabela por update aditivo. O QueueWorker revalida
  conta ativa/confirmada, preferência, assinatura, publicação, autoria, Domain
  e acesso antes de enviar; não cria aviso, entidade ou tabela paralelos.
- O consumidor da Loja (`aculta_commerce_messenger`) permanece incluído na
  matriz de adapters junto com LMS, Wiki e Fórum. `store.order_placed` usa
  Message/central, digest ou envio direto via Mailer e fallback do recibo
  Commerce conforme contrato do adapter. Unit 6/43 para Loja foi registrado na
  implementação dev.1; Kernel e Runtime da Loja permanecem pendentes.
- Unit Messenger: 86 testes / 412 assertions PASS; PHPCS Drupal/DrupalPractice
  passou após correções; lint dos arquivos PHP do outbox/worker passou. Kernel
  MariaDB do ledger: 23 testes / 515 assertions, mais o teste do update `10002`
  em 1 teste / 18 assertions; ambos PASS. Gates de configuração e Runtime seguem
  pendentes.

## 0.1.1-dev.95 — revalidar preferências de categoria no digest — 2026-10-24

- O digest compartilhado confere o modo atual de cada categoria fornecida por
  adapters. Se a pessoa mudou de resumo para direto/desativado, pendências da
  categoria são descartadas sem remover o aviso obrigatório do site.
- Unit: `AvisosResumoProviderPreferenceTest` verifica opt-out depois da
  enfileiração; Loja revalida também a categoria antes de expor linhas.
- Unit Messenger: 82 testes / 386 assertions PASS; PHPCS Drupal/DrupalPractice e PHP lint no serviço/teste PASS. Kernel não executado (SIMPLETEST_DB ausente); Runtime recebeu rebuild de cache e smoke HTTP após correção; não homologado
  como Alpha.

## 0.1.1-dev.93 — usar relógio Drupal na fila de avisos do Fórum — 2026-10-24

- `MessageSubscribeEventWorker` usa `datetime.time` para aplicar o limite de
  uma semana de tópico sem alias, sem depender do relógio global do PHP. O
  produtor é consumido pela interface pública, facilitando testes isolados.
- Resumo diário envia sempre em `pt-br`, conforme o idioma único da plataforma;
  o idioma preferido do perfil não seleciona outro locale.
- Unit direcionado: 2 testes / 23 assertions cobrem requeue para item recente e
  descarte/log de item vencido; suíte Unit Messenger: 81 testes / 379
  assertions. PHPCS Drupal/DrupalPractice e PHP lint: PASS.
- Kernel SQLite: 23 testes / 515 assertions PASS, incluindo o resumo combinado
  pelo Mailer mockado em `pt-br`; essa execução local não substitui validação
  MariaDB nem Runtime.
  Nenhum item de fila foi processado no Runtime.

## 0.1.1-dev.85 — adaptar eventos do Fórum ao publisher Messenger — 2026-10-24

- `ForumAvisoHooks` e o worker de revisões de tópico agora publicam eventos
  mínimos por destinatário via `MessengerNoticePublisherInterface`. O consumer
  confere origem, propósito e chave canônica, resolve Comment por UUID ou a
  revisão publicada de Node/ForumPost e encaminha ao agregador `aculta_aviso`.
- O agregador revalida assinatura e acesso no consumo e registra no ledger
  transacional existente; não há storage paralelo nem segundo disparo. Autores
  não recebem aviso da própria revisão de tópico.
- Kernel MariaDB do ledger/event pipeline: 18 testes / 353 assertions,
  incluindo publicação e replay de ForumPost pelo EventDispatcher; Unit
  Messenger: 70 / 334; Unit LMS Messenger: 21 / 83. PHPCS Drupal/DrupalPractice,
  PHP lint e YAML dos serviços: PASS. Runtime recebeu rebuild de cache e smoke HTTP após correção; não homologado
  como Alpha.

## 0.1.1-dev.84 — respeitar unfollow antes da entrega — 2026-10-24

- Kernel cobre evento entregue após remoção do acompanhamento: sem aviso e sem
  chave no ledger para o antigo seguidor. Confirma a resolução atual de
  destinatários no processamento. MariaDB: 16 testes / 310 assertions.
- PHPCS Drupal/DrupalPractice, PHP lint e Unit Messenger: PASS. Sem Runtime.

## 0.1.1-dev.83 — caracterizar espera e retry do lock — 2026-10-24

- O Kernel simula lock ocupado no agregado: aquisição falha, o writer espera,
  retenta, persiste um único aviso e libera o lock. A prova exercita o caminho
  de retry com LockBackend mock; não simula processos concorrentes. MariaDB:
  15 testes / 294 assertions. PHPCS Drupal/DrupalPractice e PHP lint: PASS.

## 0.1.1-dev.82 — provar a migração do ledger em lotes — 2026-10-24

- O Kernel de idempotência agora migra 501 chaves legadas em duas chamadas:
  comprova que o primeiro lote de 500 preserva a tabela antiga e que a última
  chave é migrada antes da remoção. Kernel MariaDB: 14 testes / 271 assertions.
- Sem mudança de comportamento de produção; post-update segue não aplicado no
  Runtime. PHPCS Drupal/DrupalPractice e PHP lint do teste: PASS.

## 0.1.1-dev.81 — serializar e deduplicar todos os avisos do Fórum — 2026-10-24

- O ledger passou a registrar Comment, revisão de `ForumPost` e revisão de
  tópico com chave única por destinatário/tipo/ID. Eventos em ordem inversa não
  são descartados; watermarks continuam como máximos históricos.
- Lock por destinatário/tópico/tipo serializa updates do agregado compartilhado;
  inserção do evento e incremento do aviso são transacionais. Cron remove chaves
  após seis meses em lote.
- Post-update migra em lotes as chaves Comment do dev.80 ao ledger unificado e
  só remove a tabela antiga após concluir a cópia. Kernel MariaDB: 14 testes /
  266 assertions, incluindo migração e ordem inversa de revisão de tópico/post.
  Unit Messenger: 64 / 297; PHPCS Drupal/DrupalPractice: PASS. Sem Runtime.

## 0.1.1-dev.80 — deduplicar aprovação tardia de Comments — 2026-10-24

- O marcador de CID agregado não identificava eventos individuais: uma aprovação
  tardia com CID menor que outro Comment já publicado era descartada. Criada a
  tabela mínima `aculta_messenger_forum_comment_notice`, com unicidade por
  destinatário/Comment, transação junto do incremento e watermark mantido como
  maior CID histórico. Sem migração de conteúdo.
- Install schema cobre instalações novas; post-update idempotente instala a
  tabela em existentes. Cron remove chaves vencidas em lote após seis meses,
  alinhado à retenção dos avisos. Post-update não foi executado no Runtime.
- Kernel MariaDB Simpletest: 12 testes / 220 assertions, incluindo CID fora de
  ordem, replay, watermark e expiração. Unit Messenger: 64 / 297. PHPCS Drupal
  e DrupalPractice nos arquivos PHP alterados: PASS.

## 0.1.1-dev.79 — publicar envelope mínimo de eventos — 2026-10-24

- Adicionados `MessengerNoticeEvent` e `MessengerNoticePublisherInterface`,
  publicados pelo EventDispatcher do Drupal, sem criar tabela ou engine de
  notificações. O envelope valida identificadores, destinatário e metadados
  planos; consumidores são responsáveis por revalidar acesso, deduplicar e
  escolher o storage existente.
- M2 permanece parcial: adapters e consumidores ainda não migraram para o
  publisher. Unit do contrato: 4 testes / 16 assertions PASS; Unit Messenger
  completo: 64 / 297; LMS Messenger: 21 / 83.

## 0.1.1-dev.78 — alinhar privacidade e conversas voluntárias — 2026-10-24

- A preferência “somente amigos” volta a restringir apenas a criação de novas
  conversas; respostas em threads existentes continuam sujeitas aos bloqueios
  bilaterais e ao acesso nativo do Private Message.
- Removida a regra de retenção especial `course`, que representava o antigo chat
  automático de turma. Conversas diretas e grupos voluntários seguem a política
  configurada sem migração de dados.
- Documentação normativa substitui chats contextuais e componentes de
  participação por avisos no site/e-mail; preserva os textos superados como
  histórico onde aplicável.
- PHPUnit Unit Messenger: 60 testes / 282 assertions PASS; LMS Messenger: 18 /
  88 PASS. Testes Kernel não iniciaram por ausência de SIMPLETEST_DB. Runtime
  não homologado.

## 0.1.1-dev.77 — busca de membros com região viva nomeada

- Prévia de membros agora é uma região `aria-live` com rótulo; seus resultados
  são links que se percorrem com Tab, não opções selecionáveis de combobox.
- Runtime e smoke autenticado do drawer permanecem pendentes; nenhum dado ou
  mensagem foi criado.

## 0.1.1-dev.76 — caracterizar idempotência dos avisos do Fórum — 2026-10-22

- XK-C2: novo teste Kernel `ForumNoticeIdempotencyKernelTest` (8 testes / 148
  assertions) percorre os eventos reais de primeira publicação de post e de
  atualização de tópico. Confirma um aviso por destinatário e por revisão,
  entrega repetida sem novo aviso, contadores intactos, exclusão do autor e de
  quem perdeu acesso, e isolamento entre respostas e atualizações.
- Fixture `tests/modules/aculta_messenger_aviso_test` registra a entidade de
  produção `Aviso` por Reflection, sem habilitar as integrações do Messenger.
- Nenhum serviço, hook, campo, tipo de aviso ou schema foi alterado; não há
  mudança de comportamento nem de Runtime. Documentação da caracterização em
  `docs/MESSENGER-PART-0.1-C2-CHARACTERIZATION.md`.

## 0.1.1-dev.75 — contar aprovação tardia de posts revisionáveis — 2026-10-22

- `ultimo_post` guarda agora o ID da revisão publicada, em vez do ID estável do
  post. Um post criado antes mas aprovado depois de outro passa a contar no
  aviso; repetir o mesmo evento continua bloqueado pelo marcador.
- A integração só registra a transição da primeira publicação, usando o campo
  imutável `first_published`; retirar e republicar um post não cria outra
  resposta.
- Nenhum campo foi adicionado. Testes de persistência e Runtime permanecem
  pendentes; não houve alteração no Runtime nesta entrega.
- PHPDoc das classes `AvisoService` e `Aviso` foi alinhado ao padrão Drupal;
  PHPCS Drupal/DrupalPractice passa nos arquivos dessas classes e no hook de
  integração. Sem mudança de comportamento.

## 0.1.1-dev.74 — avisos de resposta para posts revisionáveis — 2026-10-22

- Posts do `aculta_forum_post` contam no aviso agregado do tópico seguido ao
  serem publicados pela primeira vez; edições posteriores não geram nova
  resposta. Autor, sincronizações e destinatários sem acesso atual são
  excluídos. Comentários legados mantêm marcador independente
  (`ultimo_comentario`).
- Adicionado campo base com post-update. O update não foi executado; validação
  de persistência e Runtime seguem pendentes.

## 0.1.1-dev.73 — valida transferência de agregados de resposta — 2026-10-21

- Extrai `ForumNoticeAccessPolicyInterface` para permitir cobertura isolada do
  serviço e testa a mesclagem do agregado completo de respostas: somas dos
  contadores, maior ID de comentário e exclusão do registro de origem.
- Unit focal: 1 teste / 13 assertions PASS. Sem alteração de Runtime.

## 0.1.1-dev.69 — aviso persistente de e-mail não confirmado — 2026-10-21

- Modelo de aviso `aculta_msg_email_pendente` (config em `config/sync`): aviso persistente de quem não confirmou o e-mail, no sistema de avisos (entidade `message`). Sem dados pessoais.
- `EmailPendingAvisoHooks`: garante o aviso ao exibir o aviso de atenção (`aculta_portal_email_notice_alter`) e remove o aviso quando o e-mail é confirmado (`user_update`).
- `aculta_messenger.info.yml` 0.1.1-dev.69. RUNTIME STATUS: DEFERRED (teste no X11 registrado no roteiro).

## 0.1.1-dev.71 — texto do aviso de e-mail pendente em uma única origem — 2026-10-21

- Título e corpo do aviso vêm do modelo `aculta_msg_email_pendente` (delta 0 e 1). `EmailPendingAviso::title()` e `body()` leem o modelo e devolvem texto puro (sem `<p>` nem Markup).
- A lista de avisos e a caixa de atenção de Minha conta usam o mesmo ponto (`aculta_portal_email_notice`, alimentado por `EmailPendingAvisoHooks`). Nenhum dos dois guarda cópia do texto.
- Sem o modelo, nenhum item é listado e a caixa mostra só o rótulo do link.
- `aculta_messenger.info.yml` 0.1.1-dev.71. Portal: a caixa de Minha conta (`EmailConfirmationNotice`) deixou de ter texto próprio; o marcador de versão do Portal fica para quem fecha a linha do Portal.

## 0.1.1-dev.70 — aviso permanente na lista de avisos; texto padronizado — 2026-10-21

- `EmailPendingAviso` (serviço `aculta_messenger.email_pending_aviso`): cria, lê e remove o aviso de e-mail pendente. O hook e a página de avisos usam o mesmo serviço.
- A página de avisos (`/avisos-mensagens`, `MessagesController::alertsBuild`) lista o aviso como item persistente (sem "×"), com o estado "Confirmação pendente". O item aponta para o reenvio de confirmação.
- Texto padronizado com o aviso de atenção de Minha conta: "Enquanto isso, você não pode realizar cursos nem editar a wiki, nem comentar, comprar na loja ou apoiar. Clique aqui para solicitar outro link."
- Confirmação: o aviso sai pelo ponto `aculta_portal_email_confirmed` (não por `user_update`).
- `aculta_messenger.info.yml` 0.1.1-dev.70. RUNTIME STATUS: DEFERRED (confirmação real por IMAP pendente; ver CHANGELOG do aculta_user).

## 0.1.1-dev.68 — remoção do notificador de mensagens privadas com corpo — 2026-10-21

- `private_message_notify` estava ativo ao lado do Messenger, combinação que o próprio guard do módulo proíbe (o template padrão expõe o corpo da mensagem privada). Foi desinstalado do Runtime, sem avisos nem tabelas próprias. O código continua no pacote `drupal/private_message`.
- Documentação de integração e arquitetura atualizada. RUNTIME STATUS: DEFERRED.

## 0.1.1-dev.67 — verificação de amizade e entrega de e-mail — 2026-10-21

- Endurecimento verificado no servidor de testes: pedido a si mesmo e a conta inativa bloqueados; terceiro não aceita nem cancela pedido alheio; solicitante não aceita o próprio pedido; aceite repetido bloqueado; limite de 10 pedidos por hora (o 11º e o 12º bloqueados). Dados de teste removidos.
- Caminho de falha do e-mail verificado: endereço não entregável gera nova tentativa, marca a entrega como `failed` na terceira tentativa e não repete depois. Nenhum e-mail real foi enviado (domínio `.invalid`).
- Envio com destinatário real e aviso visível na lista de notificações continuam pendentes. RUNTIME STATUS: DEFERRED.

## 0.1.1-dev.66 — cobrir entrega e retentativas do aviso de amizade — 2026-10-21

- `FriendshipNoticeEmailWorkerTest` confirma envio com URL da conta, marcação
  `sent`, deduplicação após sucesso e limite de três tentativas quando Message
  Notify falha. Não há transporte SMTP real nos testes.
- Teste focal: 4 testes / 54 assertions; Unit Messenger: 55 testes / 257
  assertions PASS. PHPCS Drupal/DrupalPractice PASS. Smoke autenticado e revisão
  visual continuam pendentes. Runtime smoke anterior confirmou Message e fila,
  sem envio de e-mail. RUNTIME STATUS: PARTIAL.

## 0.1.1-dev.65 — cobrir falhas seguras do worker de amizade — 2026-10-21

- `FriendshipNoticeEmailWorkerTest` confirma que payload malformado é ignorado
  e que ausência da conta/Domain registra `failed` sem chamar Message Notify:
  2 testes / 11 assertions PASS. Nenhum e-mail é enviado pelo teste.
- Unit Messenger: 53 testes / 214 assertions PASS. Entrega bem-sucedida,
  retentativas do transporte e revisão visual autenticada ainda faltam.

## 0.1.1-dev.64 — cobrir transições e deduplicação de amizade — 2026-10-21

- `FriendshipNoticeSubscriberTest` valida que pedidos notificam o destinatário,
  aceites notificam quem iniciou, transições repetidas não duplicam Message/fila
  e contas inativas não recebem avisos: 4 testes / 33 assertions PASS.
- `FriendRequestsFormTest` confirma que a aba de Mensagens mostra ações para
  pedidos recebidos e revalida o destinatário antes de aceitar: 2/12. Unit
  Messenger: 51/203 PASS.
- O smoke Runtime documentado na dev.63 continua limitado à criação do aviso e
  item de fila; transporte real e revisão visual autenticada permanecem pendentes.

## 0.1.1-dev.63 — eventos de amizade: aviso persistente e e-mail mínimo — 2026-10-21

- Pedido e aceite de amizade geram um aviso persistente (`aculta_msg_friendship`) para quem recebe o aviso, com dedução por amizade, evento e instante da transição. Pedido avisa quem recebeu; aceite avisa quem pediu.
- E-mail mínimo por fila (`aculta_msg_friendship`), sem nome nem conteúdo: só o link para a conta. Tentativas limitadas e estado de entrega registrado.
- Consome `aculta_user.friendship_changed` (aculta_user 0.4.0-dev.54); o aculta_user não conhece o Messenger.
- Verificado no servidor de testes: pedido cria 1 aviso e 1 item de fila; pedido repetido não duplica; aceite cria o aviso para quem pediu; dados de teste removidos. Envio real do e-mail ainda não executado (RUNTIME STATUS: DEFERRED).

## 0.1.1-dev.62 — idioma preferido no resumo diário — 2026-10-21

- Usa o idioma preferido da conta ao construir `MailMessage`; o idioma Drupal
  `und` não atende ao contrato BCP 47 do Mailer e fazia o envio do resumo falhar.
- O adapter LMS cobre entrega, reenvio após falha e opt-out pelo serviço real de
  resumo diário. Runtime e revisão visual continuam DEFERRED.

## 0.1.1-dev.61 — providers de resumo diário por tagged iterator — 2026-10-20

- Permite que módulos forneçam linhas ao resumo existente sem substituir
  `AvisosResumo`; o cron envia uma mensagem combinada e confirma os itens só
  depois do envio bem-sucedido.
- Mantém opt-in global, confirmação de e-mail, janela entre envios e limpeza de
  pendências após opt-out. `AvisoDigestProviderInterface` é a extensão pública.
- O adapter LMS publica conclusão neste resumo e preserva Avisos no site.
  Runtime: DEFERRED.

## 0.1.1-dev.60 — integração de contagem e lista de avisos pessoais — 2026-10-20

- Expõe pontos de alteração para módulos adicionarem avisos pessoais à gaveta
  multidomínio e ao contador, mantendo `aculta_aviso` como implementação dos
  avisos do Fórum.
- `AvisoService` aceita `module_handler` opcional para compatibilidade de
  construção, e o contador da barra soma a extensão dos módulos integradores.
- Integração LMS via `aculta_lms_messenger`; Runtime: DEFERRED.

## 0.1.1-dev.59 — subaba Mensagens na aba Pessoas do painel ACULTA — 2026-10-20

- Subaba **Mensagens** em `/painel-administrativo/aculta/mensagens`, dentro da aba Pessoas. Avisos continuam no menu de conteúdo.

## 0.1.1-dev.58 — configuração do chat no painel ACULTA — 2026-10-20

- Limites e limpeza do chat em `/painel-administrativo/aculta/mensagens`, com item na aba ACULTA. Avisos continuam no menu de conteúdo.
- Caminhos antigos `/painel-administrativo/configuracoes/aculta/...` respondem 404, sem redirecionamento.

# Changelog

## 0.1.1-dev.57 — resumo de avisos pelo aculta_mailer — 2026-10-20

- `AvisosResumo` envia pela API `AcultaMailerInterface` (tipo `avisos_resumo`), sem `plugin.manager.mail`.
- Texto do resumo em `AvisosResumoMailType` (tag `aculta_mailer.type`); o `hook_mail` saiu de `AvisosResumoHooks`, que mantém o cron.
- `MailMessage` passou a aceitar listas de linhas escalares (até dois níveis) para o resumo; objetos e entidades continuam recusados.
- Dependência `aculta_portal:aculta_mailer`. Teste unitário do texto.

Mudanças relevantes do ACULTA Messenger.

## 0.1.1-dev.56 — remover preprocess do Forum contribuído — 2026-10-20

- Removido `ForumTopicCounterHooks`, que alterava `preprocess_forums` e
  pressupunha as linhas da listagem do módulo Drupal Forum. A descoberta do
  ACULTA é baseada em Views Core e não consome esse preprocess. Os serviços de
  aviso e contagem agregada continuam disponíveis para integração própria.
- Verificação de sintaxe, PHPCS e referências do hook: PASS. Runtime sem
  mudança. RUNTIME STATUS: DEFERRED.

## 0.1.1-dev.55 — evitar avisos duplicados ao mover comentários — 2026-10-19

- `ForumAvisoHooks::commentUpdate()` usa `CommentInterface::getOriginal()` no
  lugar da propriedade mágica `$comment->original`, depreciada no Drupal 11.2 e
  removida no Drupal 12.
- `ForumAvisoHooksTest` confirma que editar/mover um comentário já publicado
  não registra aviso duplicado e que aprovar um comentário pendente ainda entra
  no fluxo de registro. Messenger Unit: 43 testes / 151 assertions PASS, com
  2 testes / 6 assertions específicos do hook.
- Runtime `toca`: cache rebuild PASS e service container encontra
  `ForumAvisoHooks`. `aculta_forum.settings` permanece ausente; o mover continua
  fechado. Nenhum conteúdo/aviso foi criado e nenhum e-mail foi enviado.
- RUNTIME STATUS: PARTIAL.

## 0.1.1-dev.54 — testes de política de acesso dos avisos — 2026-10-19

- `ForumNoticeAccessPolicyTest` exercita a checagem com acesso permitido no
  Domain Fórum, bloqueio para outra atribuição de domínio e negação de Node
  access. Messenger Unit: 41 testes / 145 assertions PASS. Sem Runtime writes.
- RUNTIME STATUS: PARTIAL (smoke read-only registrado em dev.53).

## 0.1.1-dev.53 — avisos do Fórum com revalidação de acesso — 2026-10-19

- Seguir tópico usa o bundle público ACULTA `aculta_forum_topic` e comentários
  `aculta_discussion_post`; removido o consumidor morto que ainda referenciava
  o bundle do Drupal Forum contribuído.
- `ForumNoticeAccessPolicy` revalida conta ativa, domínio, publicação e acesso
  atual antes de criar/mostrar/contar avisos e antes do resumo diário. Comentário
  histórico e tópico com `field_autor_legado` não geram aviso. Eventos de
  sincronização são excluídos da fila.
- A fila registra o ID da revisão; criação, aprovação e cada nova revisão
  publicada entram uma vez no fluxo. O worker carrega essa revisão específica,
  confirma que ela e o tópico atual seguem publicados, e a deduplicação existente
  impede repetir a mesma revisão.
- Messenger Unit: 38 testes / 116 assertions PASS. PHPCS no novo policy/teste e
  lint PASS; as classes preexistentes tocadas já tinham violações PHPCS na base.
  Portal Drupal 11+ (970), fronteira (1371), mapa (154) e `git diff --check`
  PASS. PHP CLI 8.4.26.
- Runtime de teste: cache rebuild PASS; container resolve policy, serviço,
  resumo e worker. A política permitiu 8/8 tópicos publicados para uid 5; a
  listagem/contagem read-only retornou 0 avisos e 0 tópicos não lidos. Nenhum
  aviso foi criado nem e-mail enviado; validação autenticada/browser pendente.
- RUNTIME STATUS: PARTIAL.

## 0.1.1-dev.48 — Bloquear e Desfazer amizade por pessoa, não por conversa — 2026-10-19

- Amizade e bloqueio são por pessoa. A conversa tem uma caixa por outro membro (`OptionsDisclosure`, carregada ao abrir em `…/membro/{user}/opcoes`), e cada ação posta em `…/membro/{user}/relacao`. Antes, o menu da conversa só cobria uma pessoa e ficava vazio em conversas com mais de duas.
- O menu "Opções" da conversa tem só "Limpar histórico". "Bloquear" e "Desfazer amizade" saem dele.
- Modal do Drupal: o contêiner da pergunta não é destruído ao fechar (o `close` padrão do Drupal dispara `detachBehaviors('unload')`, e o jQuery UI então remove o elemento). O atributo `hidden` é tirado antes de abrir, porque a regra `[hidden]` do Bootstrap usa `!important`.
- Validação no navegador (Firefox, sessão X11, conta de teste): caixas por membro, diálogos de "Bloquear" e "Desfazer amizade" com título e pergunta, Cancelar fecha e reabre, "Desbloquear" e "Bloquear" (clique em "Sim") gravam e voltam à conversa com "Atualizado.". O clique em "Sim" só funcionou após a correção de `0.1.1-dev.49`/tema `0.6.0-dev.89`.
- RUNTIME STATUS: APLICADO no Runtime de teste (cache rebuild). Estado de teste restaurado: sem bloqueios, amizade preservada.

## 0.1.1-dev.52 — pedidos de amizade na aba Mensagens

- Regressão corrigida: os pedidos de amizade recebidos voltam a aparecer, com Aceitar e Recusar, no topo da aba Mensagens de `/avisos-mensagens/mensagens`. A rota `/mensagens/notificacoes` tinha sido removida e `FriendRequestsForm` ficou sem uso, mas a barra continuava contando os pedidos.
- `AvisosMensagensController::conteudoMensagens()` renderiza `FriendRequestsForm` só quando há pedidos pendentes (`FriendshipManagerInterface::getIncomingRequests()`).
- Verificado no Runtime de teste (2026-10-19): pedido pendente de outra conta aparece na aba; Aceitar confirmou a amizade ("Amizade confirmada") e a relação ficou `accepted`.
- RUNTIME STATUS: APLICADO no Runtime de teste (cache rebuild); validação visual autenticada pendente.

## 0.1.1-dev.51 — duplicação entre as ações do "Opções" e da caixa de membro

- `submitRelationshipForm()`: o núcleo comum (POST, `form_build_id` fora do input, `setUserInput`, construção do formulário com os serviços) sai de `runRelationshipAction` e `runMemberAction`. `resultMessage()` lê a mensagem do resultado nas duas.
- Cada ação mantém só o que é dela: modo "notice" e passo de "Limpar histórico" na conversa; o alvo do membro na caixa.
- Validado no Firefox (X11): "Sim" de bloqueio gravou pelo caminho refatorado; resultado na caixa; "Desbloquear" restaurou (sem bloqueios, amizade preservada).
- RUNTIME STATUS: APLICADO no Runtime de teste (cache rebuild).

## 0.1.1-dev.50 — revisão do /code-review: resultado na caixa de quem agiu e limpeza

- O resultado de "Bloquear", "Desbloquear" e "Desfazer amizade" aparece na caixa do membro que agiu, aberta, e não no menu "Opções" da conversa (`memberPanel`, `memberBoxes`).
- `menuFragment()`: resposta HTML do menu (antes duplicada entre `drawerOptions` e `memberOptions`).
- `threadFormState()` só calcula os outros membros no modo "notice".
- RUNTIME STATUS: APLICADO no Runtime de teste (cache rebuild). Validado no Firefox: "Desbloquear" mostra "Atualizado." na caixa de `teste.b`; estado de teste restaurado.

## 0.1.1-dev.49 — registro da validação do diálogo (sem mudança de PHP)

- Versão para registrar a correção do diálogo de confirmação no tema `0.6.0-dev.89`: o "Sim" passa a enviar a ação. Sem mudança de código neste módulo.

## 0.1.1-dev.47 — confirmação de Bloquear e Desfazer amizade em modal do Drupal — 2026-10-19

- `ConfirmDialog` deixa de usar `<dialog>` nativo. A pergunta é um contêiner oculto em `data-aculta-dialog`, e o tema abre o diálogo com `Drupal.dialog` (regra de `POPUP-PADRAO.md`).
- Sem mudança de texto nem de ação: "Sim" continua submetendo o formulário (o diálogo fica dentro do form, por `appendTo`). Validação no navegador: pendente.
- RUNTIME STATUS: DEFERRED.

## 0.1.1-dev.46 — docblocks de Opções corrigidos — 2026-10-19

- Docblock "Opções" movido de `drawerOptions()` para `drawerRelationship()`, que é o método que o descreve. Sem mudança de comportamento.

## 0.1.1-dev.45 — avisos só para contas — 2026-10-17

- O ícone de avisos e a contagem não aparecem para visitante anônimo (decisão do responsável).
- A prévia de avisos (`/avisos/resumo`) exige conta: visitante recebe 403.
- RUNTIME STATUS: APLICADO no Runtime de testes (página inicial anônima sem ícone de aviso; prévia 403 para anônimo).

## 0.1.1-dev.44 — "Ver todos os avisos" como botão no rodapé da gaveta (F5.2) — 2026-10-16

Classificação: PATCH (ajuste de apresentação e de URL; sem regra nova).

- O link "Ver todos os avisos" sai da lista e vira botão no rodapé fixo da gaveta de avisos da barra, abaixo de todos os avisos.
- A URL é absoluta no purpose `account` (`DomainPurposeManager::canonicalRouteUrl`), e não mais relativa ao host atual.
- O rodapé só aparece para quem está logado.
- Gaveta de mensagens: botão "Ver todas as mensagens" no mesmo rodapé, apontando para a aba Mensagens da conta (`all_url` em `aculta_messenger`).
- Gaveta de avisos: a lista rola dentro da gaveta e o rodapé fica fora da rolagem (verificado no Firefox com lista longa).
- Teste unitário `PersistentNoticeAccessTest` corrigido: o construtor de `MessengerBarHooks` agora recebe `AvisoService` e `DomainPurposeManager`. Os 33 testes do módulo passam.

## 0.1.1-dev.43 — rótulo Lido/Não lido nos alertas do site (F5.2) — 2026-10-16

Classificação: MINOR (novo flag de leitura e rótulo nos alertas do site).

- Alertas do site (não persistentes) mostram "Lido" ou "Não lido", à direita da caixa. Avisos persistentes não têm rótulo.
- Novo flag `aculta_site_alert_read` por pessoa (config em `config/install` e na configuração do projeto). Abrir o alerta marca como lido, só quando a própria pessoa abre (sem pré-busca).
- Post-update `aculta_messenger_post_update_site_alert_read_flag` cria o flag no Runtime.
- Regra de vida útil documentada em `docs/AVISOS-E-MENSAGENS.md`, seção 8.2.

## 0.1.1-dev.42 — rótulo Lido/Não lido à direita, com cor de fonte por estado (F5.2) — 2026-10-16

Classificação: PATCH (ajuste de apresentação do rótulo criado em dev.41).

- O rótulo sai do lado esquerdo e fica à direita da caixa, no topo, fora do link (a caixa inteira continua clicável).
- Lido e Não lido têm cores de fonte diferentes; o fundo fica só atrás do texto do rótulo.

## 0.1.1-dev.41 — rótulo Lido/Não lido e retenção de 6 meses dos avisos do Fórum (F5.2) — 2026-10-16

Classificação: MINOR (nova regra de retenção e novo rótulo de estado).

- Caixas de conversa e avisos do Fórum mostram o rótulo "Lido" ou "Não lido" no topo, à esquerda. Não há mais marcação por cor nem "N novas" nessas caixas.
- Ler o tópico zera o contador do aviso e troca o rótulo para "Lido". Abrir a conversa faz o mesmo.
- Avisos do Fórum (`aculta_aviso`, tipos de resposta e atualização) são excluídos automaticamente seis meses após a criação (cron, `AvisoRetencaoHooks`).
- Fora da regra: avisos persistentes (`aculta_notice`) e alertas do site (`site_alert`), enviados pelo sistema administrativo, não têm rótulo de lido/não lido nem regra temporal. Os avisos do LMS também ficam fora: não são `aculta_aviso` e não têm tipo de Fórum, então não são excluídos nem recebem o rótulo. Conversas seguem a própria política de retenção.
- Avisos do Fórum não recebem mais a cor do tipo "Informação".
- Testes: `AvisoRetencaoTest` (limite de seis meses e tipos sujeitos à regra).

## 0.1.1-dev.39 — cliques nas abas da central abrem o item no lugar (F5.2) — 2026-10-16

- A área das abas Avisos e Mensagens é um contêiner da gaveta (`data-aculta-drawer-url`/`-content`), e os cliques abrem o aviso ou a conversa no mesmo lugar, como na barra.

## 0.1.1-dev.38 — aba Mensagens com a lista de conversas da gaveta (F5.2) — 2026-10-16

- A aba Mensagens da conta mostra a mesma lista de conversas da gaveta da barra (`conversasBuild`), no lugar do link "Abrir conversas".

## 0.1.1-dev.37 — aba Avisos sem "Ver todos" (F5.2) — 2026-10-16

- A aba Avisos da conta não mostra o link "Ver todos", porque já é a lista completa. A gaveta da barra mantém o link.

## 0.1.1-dev.36 — aba Avisos da conta com a mesma lista da gaveta da barra (F5.2) — 2026-10-16

- A aba Avisos da conta mostra a mesma lista da gaveta (comunicados, avisos da pessoa e alertas), com os mesmos itens e ações.
- A montagem da lista saiu da resposta HTTP da gaveta (`alertsBuild`), para ser reutilizada.

## 0.1.1-dev.35 — central Avisos/Mensagens em Minha conta (F5.2) — 2026-10-16

- Central "Avisos/Mensagens" em Minha conta, com abas Avisos e Mensagens no padrão da conta.
- Prévia da barra: avisos da própria pessoa seguidos do link "Ver todos os avisos".
- Correção: o controller da central não redeclara a propriedade `$currentUser` da classe base.
- Item "Avisos/Mensagens" na navegação de Minha conta (conta.aculta), com badge de não lidos, pelos pontos de extensão do shell da conta. A barra usa os ícones do aculta_messenger.

## 0.1.1-dev.34 — avisos por pessoa: atualizações, contador do ícone, controles e resumo diário (F5.2) — 2026-10-16

- Atualização de tópico seguido vira aviso `forum_atualizacao` (worker reescrito), sem mensagem.
- Contador do ícone de avisos soma os avisos por pessoa não lidos.
- Bloco "Avisos" na privacidade da conta: resumo diário por e-mail (opt-in) e tipos incluídos.
- Resumo diário por e-mail pelo cron, agrupado por tópico, só com novidade e e-mail confirmado.
- Pendente: página "Meus avisos" e teste de entrega real de e-mail.

## 0.1.1-dev.33 — avisos por pessoa para respostas em tópico seguido (F5.2) — 2026-10-16

- Entidade `aculta_aviso` (uma por pessoa e tópico) com contadores do site e do e-mail e marca do último comentário.
- `Aviso\AvisoService`: conta cada comentário publicado uma vez; autor não recebe aviso do próprio comentário; leitura zera o contador do site.
- Hooks `comment_insert` e `comment_update` (aprovação) e leitura pela rota canônica do tópico.
- Entrega por mensagem de tópico seguido desligada (`MessageSubscribeHooks::getSubscribers` retorna vazio) — F-D21.
- Documentação: `docs/AVISOS-E-MENSAGENS.md` separa mensagens e avisos e descreve cada função do módulo.
- Pendente: aviso de atualização de tópico, contador na barra, resumo diário por e-mail.

## 0.1.1-dev.32 — avisos não interrompem perfis anônimos — 2026-10-15

Classificação PATCH. A dispensa de avisos pertence a contas autenticadas: contagem
na barra e prévia não consultam Flag com uid 0 sem sessão. Corrige erro 500
reproduzido em perfil público durante a verificação do Game. Sem mudança de
permissões, conteúdo ou Core/contrib.

Verificação: HTTP anônimo do perfil e 17 verificações HTTP do Game passaram após
a correção, incluindo ocultação/reexibição e cache em outra sessão. API conferida
no Flag 5.1.1 instalado (`populateFlaggerDefaults`); referência upstream:
https://www.drupal.org/project/social/issues/2925907.

## 0.1.1-dev.31 — correção dos menus e da suíte de navegador; grupos e confirmações — 2026-10-15

Classificação: PATCH (correções encontradas pela suíte de navegador e pela auditoria; sem mudança de regra de negócio).

- Conversa em grupo tem linha própria na lista (antes era absorvida pela caixa de um dos membros) e menu "Opções" com "Limpar histórico" em duas etapas.
- Menu "Opções": a exibição não processa a entrada nem carrega formulário em cache; a ação é construída de novo com as dependências. Evita o envio repetido e a propriedade não inicializada.
- Classes de apresentação saíram do módulo: cada uma é um token `data-portal-ui` (mapa em `PortalUiHooks`). Links com token usam `PortalLinkElement`; a caixa "Opções" usa `OptionsDisclosure` (details do Core) e a confirmação usa `ConfirmDialog`.
- Aviso de bloqueio do Private Message em português (`private_message.settings`, exportado em `config/sync`).
- Gate de fronteira: a regra de classes varre todos os `src/` do módulo (submódulos inclusos); dívida de `aculta_gallery` registrada como `DT-BOUNDARY-GALLERY`.
- Validação: suíte de navegador (`tests/browser/aculta-messenger/`) 70 de 70 no runtime de teste.
- Base dos tipos de conversa: `Service\ConversationPolicy` (tipo, limite de participantes e retenção), usada pela retenção; documentação em `docs/portal/MESSENGER-CONVERSATION-KINDS.md`. Criação de grupo pela gaveta e integrações ainda não existem.
- Lista de conversas: o menu "Opções" de cada linha é só o ícone de três traços; o painel abre sobre a linha, alinhado à direita da caixa (a conversa aberta mantém a caixa completa).
- Aviso persistente não tem botão de dispensar: cartão em cor de aviso (`notice-summary-persistent`), e a leitura é o link "Marcar como lido" no aviso completo. Avisos do site (dispensáveis) ganham a permissão `flag aculta_site_alert_dismissed` no papel autenticado, que faltava.
- Avisos na gaveta: cada aviso (persistente ou do site) é um resumo em caixa de botão; o clique abre o aviso completo na mesma gaveta (rotas `aculta_messenger.notice_detail` e `aculta_messenger.alert_detail`, com "← Todos os avisos").
- Lista de conversas: o menu "Opções" de cada linha não é montado na lista; a linha guarda o endereço do menu e o carrega na primeira abertura (rota `aculta_messenger.drawer_options`).
- Revisão de código (medium), mesma PR: destinatário da nova conversa vem da rota; ação de Opções no perfil mostra o resultado; IDs de título dos diálogos únicos; contagem de avisos para visitantes zerada; fila de aviso de fórum deixa de reenfileirar para sempre; script de preparação recusa outro banco que não aculta_runtime.
- RUNTIME STATUS: ACTIVE no teste.

## 0.1.1-dev.30 — confirmação para bloquear e botões Cancelar/Voltar — 2026-10-15

Classificação: PATCH (confirmação antes de bloquear em todos os locais e botões da nova conversa).

- "Bloquear" (menu Opções das conversas e do perfil) abre a janela "Você quer mesmo bloquear <usuário>?" com "Sim" e "Cancelar". Só o "Sim" bloqueia.
- "Desfazer amizade" (mesmo menu) também pede confirmação: "Você quer mesmo desfazer a amizade com <usuário>?", com "Sim" e "Cancelar".
- Nova conversa: "Cancelar" e "Voltar para o início" ficam acima de "Enviar", cada um com sua cor (`PortalLinkElement`).
- Validação: no runtime de teste, perfil de teste: abrir, cancelar, bloquear pelo "Sim", desbloquear; desfazer amizade pelo "Sim" e recriar a amizade. Gaveta: abrir e cancelar (sem enviar "Sim" na conta real). O "Sim" pela gaveta não foi exercido.

## 0.1.1-dev.29 — menu "Opções" nas conversas e no perfil — 2026-10-15

Classificação: PATCH (troca do menu quebrado das conversas pelo menu "Opções" e inclusão do menu no perfil).

- Conversas: os três pontos antigos saem; cada linha da gaveta mostra o "Opções" (amizade, limpar histórico em duas etapas, bloquear).
- Perfil `/u/…`: caixa "Opções" no canto superior direito do cartão, com "Iniciar conversa" no lugar de "Limpar histórico". "Iniciar conversa" abre a gaveta de mensagens já na etapa de nova conversa com o membro.
- Rota nova `aculta_messenger.profile_relationship` (POST) aplica as ações do perfil e volta ao perfil.
- Validação: capturas de tela no runtime de teste (painéis abertos, perfil e linha). A suíte de 52 verificações não foi reexecutada depois desta mudança; ver `docs/portal/TESTES-NAVEGADOR.md`.

## 0.1.1-dev.28 — correções da revisão no navegador: resposta, limpeza, desbloqueio — 2026-10-15

Classificação: PATCH (correções de fluxo encontradas pela suíte de navegador).

- Resposta enviada pela gaveta: o formulário nativo do Private Message redirecionava para a própria página (`/private-messages/…`, que não existe neste domínio). A gaveta agora envia por `fetch` para `aculta_messenger.drawer_reply`, que aplica as mesmas regras do compositor (bloqueio, só amigos, limite de envios) e grava a mensagem. O token do formulário é validado como o Form API faz.
- "Limpar histórico": o link apontava para uma rota do domínio da conta (404 aqui). Agora é ação em duas etapas dentro do menu: primeiro pede confirmação, depois limpa só para quem confirma. A confirmação some quando outra ação é feita.
- Depois da limpeza a resposta é a lista de conversas, com "Histórico limpo para você.".
- Desbloquear pelo link do aviso: os botões do aviso são identificados por um campo oculto `mode=notice`, porque o rótulo do aviso é diferente do rótulo do menu.
- O menu de Opções fica aberto depois de uma ação, para que o resultado seja visto.
- Teste de navegador: 52 de 52 verificações passaram (ver `docs/portal/TESTES-NAVEGADOR.md`, seção U).
- Pendências: conversas em grupo (mais de duas pessoas) não têm "Limpar histórico" no menu; o aviso nativo do bloqueio segue em inglês (configuração do contrib).

## 0.1.1-dev.27 — aviso no lugar da caixa de resposta para privacidade "só amigos" — 2026-10-15

Classificação: PATCH (texto e apresentação do aviso).

- Em conversas com quem só recebe mensagens de Amigos, quem não é amigo vê o aviso no lugar da caixa de mensagem e do botão Enviar: "Essa pessoa só recebe mensagens de Amigos, clique aqui para enviar um pedido de Amizade."
- Se o pedido já foi enviado: "Essa pessoa só recebe mensagens de Amigos, você já enviou pedido de amizade, aguarde."
- O bloqueio continua com os avisos próprios ("Você bloqueou essa pessoa, Clique aqui para desbloquear" e "Essa pessoa bloqueou você, por isso não pode enviar ou receber mensagens").
- Verificado no runtime de testes: aviso padrão, aviso após o pedido, e o texto na tela.

## 0.1.1-dev.26 — bloquear mantém a conversa visível e impede o envio — 2026-10-15

Classificação: MINOR (muda o comportamento de bloqueio do Private Message nesta instalação).

- Quem bloqueia um membro continua vendo a conversa na lista, e pode desbloquear pelas "Opções". Antes, o Private Message escondia a conversa de quem bloqueou, e o desbloqueio ficava inacessível pela gaveta.
- A troca é feita por `AcultaMessengerServiceProvider`, que troca a classe do serviço `private_message.mapper` por `MessengerPrivateMessageMapper`. A subclasse só muda `getBannedThreads()` (não exclui conversas por bloqueio). O serviço, os argumentos e a consulta de envio do contrib continuam os mesmos.
- O envio é recusado nos dois sentidos: quem bloqueou não envia (o formulário de resposta fica desabilitado, como já fazia o Private Message), e o bloqueado não envia ao bloqueador (regra que já existia). `PrivateMessageFormValidator::validateBlockedRecipients` ganhou a recusa para quem bloqueou, com mensagem em português.
- Verificado por POST no runtime de testes: a conversa segue na lista do bloqueador; o envio é recusado; "Desbloquear" restaura o formulário de resposta.
- Pendência: o aviso que o Private Message mostra a quem bloqueou, "User is unable to receive your message", vem da configuração `private_message.settings:ban_message`, que não é exportada no repositório. Trocar o texto exige decisão sobre a configuração versionada.

## 0.1.1-dev.25 — "Opções" da conversa: amizade, bloqueio e menu — 2026-10-15

Classificação: MINOR (novas ações na conversa: amizade e bloqueio; menu novo).

- "Opções" é um menu em sanfona, à direita da barra da conversa, na mesma linha de "← Todas as conversas". No celular aparece só o ícone de hambúrguer.
- Ações do menu, conforme o estado da relação com o outro membro: Adicionar amigo; Cancelar pedido de amizade (pedido enviado por mim); Aceitar pedido de amizade e Rejeitar pedido de amizade (pedido recebido); Desfazer amizade; Bloquear e Desbloquear.
- Ordem do menu: ações de amizade, "Limpar histórico", e por último Bloquear/Desbloquear.
- Cada ação roda por POST na rota `aculta_messenger.drawer_relationship` e a gaveta recebe a conversa atualizada, com a mensagem do resultado (por exemplo, o limite de pedidos).
- O formulário de Opções entra nas exceções do Turnstile (`aculta_messenger_conversation_relationship`, ponto desativado em `config/sync`).
- Correções encontradas no caminho: o botão de ação só roda se foi enviado (o Form API cai no primeiro botão quando o enviado não é encontrado); a ação é processada com a entrada da requisição e não com o cache de formulário.
- Testado no Firefox headless em 1280 e 390 px (ordem do menu, alinhamento da barra, cada ação) e por POST com `curl` como o membro que envia e como o que recebe.
- Pendência: uma conversa com um membro bloqueado some da lista de conversas do Private Message, então "Desbloquear" não aparece pela gaveta; a conversa abre pelo endereço direto. Decisão pendente sobre onde desbloquear.

## 0.1.1-dev.24 — textos do Private Message em português — 2026-10-15

Classificação: PATCH (rótulos e placeholder).

- "Clear history" vira "Limpar histórico" (alter do link da conversa, `entity_view_alter`).
- O rótulo do campo de membros ("Member(s)") vira "Participantes", no próprio campo base (`entity_base_field_info_alter`), então a conversa e os formulários mostram o mesmo nome.
- O placeholder do campo de resposta ("Message") vira "Escreva sua mensagem". O `text_format` copia o placeholder para o textarea no `#process`, por isso ele é definido no elemento de origem.
- Testado no Firefox headless em 390 e 1280 px.

## 0.1.1-dev.23 — "← Todas as conversas" no topo da gaveta; erros do envio visíveis — 2026-10-15

Classificação: PATCH (posição do link de volta e exibição de erros na gaveta).

- O link "← Todas as conversas" fica no topo, à direita, abaixo do X, em todas as etapas: conversa aberta, Nova conversa, compor e resposta de envio. A conversa aberta não o coloca mais depois do "Clear history" (o hook de entidade deixou de adicioná-lo; a gaveta o põe antes da conversa).
- A etapa de compor lista os erros do formulário (por exemplo o limite de envio) dentro da própria gaveta, com `role="alert"`. Antes o erro só aparecia como borda vermelha na caixa de mensagem.
- Os formulários do Messenger (`aculta_messenger_new_conversation` e `private_message_add_form`) estão nas exceções do Turnstile do Portal (ver `aculta_portal` 0.4.0-dev.24).
- Testado no Firefox headless em 1280 e 390 px: conversa aberta, lista, Nova conversa, compor, envio e erro de limite.
- Pendência: o widget do Turnstile não era montado no fragmento da gaveta; com a exceção o desafio não aparece mais nesses formulários.

## 0.1.1-dev.22 — "← Todas as conversas" alinhado à direita, abaixo do X — 2026-10-15

Classificação: PATCH (posição do link de volta na gaveta).

- O link "← Todas as conversas" da conversa aberta e o da resposta de envio ficam alinhados à direita, logo abaixo do X da gaveta, com o mesmo tamanho de fonte.
- A resposta de envio usa a mesma classe `aculta-messenger-back-link` que a visão da conversa já usava.
- Estilo em `components/content/messenger-item/messenger-item.css` do tema (alteração do tema sem versão própria, ver pendência do tema).
- Medido no Firefox headless: borda direita do link a 8 px da borda do X em 1280 e 390 px, tamanho de fonte inalterado (13 px).

## 0.1.1-dev.21 — "Iniciar nova conversa" continua visível ao voltar da conversa — 2026-10-15

Classificação: PATCH (correção de um caminho de navegação da gaveta).

- O "Voltar" de uma conversa carrega a lista pela rota `drawer_conversations`, que não tinha o botão "Iniciar nova conversa" (só a primeira janela, `preview`, tinha). Os dois caminhos agora usam a mesma ação, em `newConversationAction()`.
- Testado no Firefox headless (geckodriver): abrir a barra, abrir uma conversa, voltar, e o botão aparece. Captura da lista após voltar conferida.

## 0.1.1-dev.20 — "Iniciar nova conversa" funcionando de ponta a ponta — 2026-10-15

Classificação: PATCH (correção do fluxo da 0.1.1-dev.19, que não tinha sido testado no navegador).

- O botão "Iniciar nova conversa" é um botão de verdade (elemento de botão com classes do tema), e não um texto de link.
- O campo de busca de membros recebe a classe que o script de busca procura, e o script passa a ser carregado em todas as páginas de membros por `hook_page_attachments` (antes era anexado a um array de região, que não chegava à página).
- Envio da primeira mensagem por `fetch` dentro da gaveta: o AJAX do Form API não funciona em fragmento carregado por `fetch`, porque depende de `drupalSettings`. O formulário desativa o redirecionamento do Form API, e a gaveta recebe a conversa como resposta.
- Correção no envio: o Private Message recebe entidades de usuário em `getThreadForMembers()`, não IDs.
- Cancelar e Voltar para o início são elementos de link com os atributos que a gaveta usa; o `toRenderable()` antigo descartava esses atributos e fazia a gaveta carregar a página inteira.
- A rota de compor aceita POST (`methods: [GET, POST]`).
- Tema: `messenger-drawers.js` trata o envio do formulário de nova conversa e aceita o botão como ação de abrir a etapa. A alteração do tema não tem versão própria neste commit (ver o changelog do tema).
- Testado no Firefox headless (geckodriver) nas larguras de 1280 e 390 px: passos completos do fluxo, Cancelar, Voltar para o início e envio. Roteiro e resultados em `docs/portal/TESTES-NAVEGADOR.md`, seção T.
- Pendências: a visão nativa da conversa mostra "Clear history" e "Member(s)" em inglês (já existia); toque em aparelho real e leitor de tela não testados.

## 0.1.1-dev.19 — "Iniciar nova conversa" na barra de mensagens — 2026-10-15

- Novo botão "Iniciar nova conversa" na primeira janela da barra de mensagens. Ele abre, por AJAX, um campo de busca de membros que funciona como a busca do cabeçalho: resultados interativos a partir de 3 caracteres, o mesmo atraso e a mesma dica de caracteres mínimos, sem abrir a página de busca completa.
- Ao escolher um membro, a etapa seguinte mostra a caixa de mensagem com "Enviar mensagem", "Cancelar" e "Voltar para o início". O envio usa o formulário nativo do Private Message, com as mesmas validações de bloqueio e privacidade de novas conversas.
- Rotas: `aculta_messenger.new_conversation` (`/mensagens/nova`), `.new_conversation_search` (`/mensagens/nova/buscar`) e `.new_conversation_compose` (`/mensagens/nova/{user}/painel`). Exigem membro com permissão de mensagens e acesso a perfis.
- Busca de membros: o `NewConversationController` limita a 10 resultados e exige 3 caracteres. A paridade exata de limite e de atraso com a busca do cabeçalho ainda não foi comparada lado a lado no navegador.
- O script de busca do cabeçalho (`aculta_portal/search-preview`) passa a ser carregado nas páginas de membros, porque a nova etapa o reutiliza.
- RUNTIME STATUS: rotas verificadas por HTTP no runtime de testes (etapa de busca com 0, 2 e 5 caracteres; etapa de compor). Interação no navegador e envio ainda não verificados; ver `docs/portal/TESTES-NAVEGADOR.md`.

## 0.1.1-dev.18 — quem pode iniciar conversas em Privacidade; e-mail só em notificações — 2026-10-15

- A pergunta "Quem pode iniciar uma conversa com você?" e a nota de bloqueio saem de Notificações por e-mail e vão para Privacidade, no grupo "Mensagens privadas". O Messenger acrescenta o grupo ao formulário de privacidade por `form_alter`; o `aculta_user` não conhece essa regra.
- A preferência é gravada pelo serviço do Messenger (`aculta_messenger.message_privacy_form`), no mesmo dado que a validação de novas conversas lê.
- Notificações por e-mail ficam só com o controle de e-mail e o texto de assinaturas de tópicos.
- Correções encontradas no caminho: o atributo `Autowire` usado nos hooks novos vinha de um namespace que não existe no Core (`Drupal\Core\DependencyInjection\Attribute`); trocado pelo namespace do Symfony. A aba "Notificações por e-mail" passa a aparecer sempre (para quem pode enviar mensagens), mesmo com notificações desligadas no site.
- RUNTIME STATUS: ACTIVE no runtime de testes (salvamento verificado pelo navegador e pelo banco).

## 0.1.1-dev.17 — mensagens só pela barra; preferências de e-mail em Configurações — 2026-10-15

- Páginas nativas do Private Message fora da experiência do membro: a caixa de entrada redireciona para a página inicial da conta e a página de conversa responde 404. Os formulários de excluir, limpar histórico e bloquear continuam; a página de nova mensagem (usada pelo botão do perfil) continua até o início de conversa existir na barra.
- Preferências de e-mail voltam como aba "Notificações por e-mail" em Minha conta > Configurações (`/configuracoes/email`). A aba aparece para quem pode mensagens, e o formulário mostra os controles desabilitados quando as notificações estão desligadas no site.
- Notificações de e-mail seguem desligadas no runtime de teste (`enable_notifications` ausente). Ligar exige decisão, porque envia e-mails reais.
- RUNTIME STATUS: ACTIVE no runtime de testes.

## 0.1.1-dev.16 — mensagens saem da Minha conta; permissões das rotas corrigidas — 2026-10-15

- Mensagens passam a existir só na barra multidomínio. Saem da Minha conta a seção, a aba de configurações de comunicação, as páginas de conversas, avisos e notificações (rotas removidas) e o formulário de preferências. As preferências de e-mail (`private_message.receive_notification`) continuam salvas.
- O link de volta das conversas aparece só no drawer. O e-mail de nova mensagem leva à página inicial da conta.
- Correção: as rotas de mensagens usavam `, ` em `_permission`, que o Core lê como E com espaços. Membros com as permissões recebiam 403 em Mensagens. Corrigido para a sintaxe do Core.
- Teste de ownership das notificações removido com a página que testava.
- RUNTIME STATUS: ACTIVE no runtime de testes.

## 0.1.1-dev.15 — correções da revisão de código do Messenger — 2026-10-15

Classificação: PATCH (correções; sem API nova).

- Janelas de início e fim de avisos são lidas em UTC (`StorageDateTime`). Antes, o fuso do usuário mudava o instante em até 3 h.
- Visitantes voltam a ver o gatilho de Site Alerts; só o gatilho de mensagens é restrito a membros.
- Regra única de não lida (`UnreadThreadCounter::isUnread`) para badge da conta, cabeçalho, lista e indicador da thread. Threads sem atualização desde o último acesso não carregam mensagens.
- `MessengerCounts`: contagem de alertas e de avisos não lidos em um só serviço; avisos contados por consulta.
- Aviso de tópico sem alias é reenfileirado com registro, em vez de descartado.
- Retenção de seis meses remove as notificações das mensagens antes de apagar a conversa. A política de apagar para os dois membros não mudou.
- Leitura de conversa pelo painel só acontece em requisição do próprio site; cross-site e prefetch recebem 204.
- Painel de mensagens (tema): listeners do menu são removidos ao fechar e o offcanvas desanexa comportamentos antes de substituir o conteúdo.
- Validação: 23 testes unitários do Messenger OK; verificações no runtime de teste (home anônima, conta, avisos, prévia). Interação do menu no navegador pendente. RUNTIME STATUS: ACTIVE no runtime de testes.
- Entrega: PR de pendências do Messenger (chore/messenger-pendencias).

## 0.1.1-dev.14 — validação visual da central de avisos — 2026-10-15

Classificação: PATCH (marcação de apresentação do módulo; sem mudança de acesso).

- A descrição de cada item da visão geral de Avisos é um parágrafo próprio, e não texto solto após o link. Antes ficava colada ao título no desktop e no mobile.
- Validação visual no runtime de teste com Chromium headless (desktop 1280 e mobile 390): visão geral, avisos temporários, persistentes, criação de persistente e caixa de mensagens da conta. Sem erro de PHP nas páginas.
- Pendência menor: os botões "Criar" da visão geral ficam com alinhamento levemente diferente no mobile (margem do tema).
- RUNTIME STATUS: ACTIVE no runtime de testes.

## 0.1.1-dev.13 — consolidação com a main e central administrativa de avisos — 2026-10-15

- Consolida o trabalho preservado do checkout de teste sobre a main (PR #284) e porta a central Avisos em `/painel-administrativo/avisos`, com abas temporários (Site Alert nativo) e persistentes (`aculta_notice`).
- Correções de carregamento: propriedades readonly que colidiam com `ControllerBase`/`FormBase`; tipos inexistentes no Core (`path_alias`, `Component` TimeInterface, Social FriendshipManager, `QueueFactory`); aliases de autowire para serviços de contrib.
- Regressões da linha antiga restauradas: proteção de desinstalação de comunicados e recibos; trava de texto puro e rótulo de envio; acesso a comunicados fail-closed e cache até a validade; permissões de mensagens no papel authenticated.
- Nomes de campos e bundles até 32 caracteres (`aculta_msg_*`), alinhados ao runtime.
- Testes unitários versionados (17 testes, 65 asserções, OK).
- Atualização `aculta_user` 10002 (tabela `aculta_friendship`) aplicada no runtime após backup.
- Entrega: PR #284 (consolidação) e PR #285 (central de avisos). RUNTIME STATUS: ACTIVE no runtime de testes; validação autenticada do fluxo de mensagens pendente.

## 0.1.1-dev.12 — navegação de conversas no painel lateral — 2026-10-15

- Adiciona endpoints same-origin para carregar a lista e uma conversa no painel lateral.
- Acesso direto à thread exige permissão e associação do usuário à conversa.
- O formulário de resposta continua sendo o formulário nativo de Private Message; foi incluído retorno à lista no drawer.
- Verificações estáticas: PHP lint e `git diff --check` PASS; gates/runtime pendentes.
- RUNTIME STATUS: DEFERRED.

## 0.1.1-dev.11 — resposta e data na caixa de mensagens — 2026-10-14

- O modal de conversa mantém o histórico nativo do Private Message e garante o formulário de resposta nativo quando a exibição ativa não o inclui.
- A lista da barra lateral mostra a data e hora da mensagem mais recente dentro do botão do remetente, em markup explícito e fonte pequena.
- A data usa o timestamp da última mensagem; o timestamp da thread é fallback.
- `git diff --check`: PASS. PHP lint, gates e validação visual: não executados nesta etapa.
- RUNTIME STATUS: DEFERRED.

## 0.1.1-dev.10 — data da última mensagem no contato — 2026-10-14

- Cada botão de remetente mostra a data e hora da última atividade da conversa acima do nome, em tipografia pequena.
- A data usa o timestamp atualizado do Private Message; nenhuma dimensão do botão foi ampliada intencionalmente.
- Validação visual e gate Drupal: pendentes nesta etapa.
- RUNTIME STATUS: DEFERRED.

## 0.1.1-dev.9 — contatos compactos no Messenger — 2026-10-14

- A gaveta mostra uma caixa clicável por usuário com avatar circular e nome somente; abre a conversa mais recente.
- Ação contextual de limpar histórico e estado não lido permanecem disponíveis.
- CSS da caixa passa a ser carregado pela biblioteca da gaveta, inclusive no conteúdo AJAX sem renderizar o SDC de mensagem.
- Capturas visuais revisadas em desktop (1440×960) e mobile (390×844) com a resposta autenticada renderizada pelo checkout local.
- RUNTIME STATUS: visual local PASS; interação real da gaveta ainda DEFERRED.

## 0.1.1-dev.8 — menu contextual e retenção de conversas

- Inclui menu de contexto no clique direito e atalho acessível para abrir a confirmação nativa de limpeza do histórico pessoal.
- Preserva a exclusão por participante do Private Message; esconde da lista a ação global de apagar thread.
- O Cron remove até 20 conversas sem atividade há seis meses por execução, usando o ciclo de vida da entidade nativa.
- Não executado Cron nem exclusão no runtime durante esta alteração.
- Validação no checkout local: `drush cr`, prévia autenticada e gates Portal/Foundation PASS; interação no navegador e expiração real ainda pendentes.
- RUNTIME STATUS: DEFERRED até sincronizar a nova versão com o checkout local; purge DEFERRED até execução futura do Cron.

## 0.1.1-dev.7 — conversas agrupadas por remetente

- A prévia organiza conversas em caixas por remetente e prioriza a conversa atualizada mais recentemente.
- O nome do remetente abre sua conversa mais recente; as linhas de conversa continuam abrindo os respectivos históricos nativos.
- A ordem é baseada no timestamp `updated` do Private Message; não há armazenamento paralelo.
- `php -l` e validação funcional autenticada: pendentes nesta etapa.
- RUNTIME STATUS: DEFERRED até aplicação/teste do commit no checkout local.

## 0.1.1-dev.6 — caixa visual de conversas e confirmação de avisos

- A gaveta de mensagens limpa a contagem de conversas não lidas ao abrir, usando o last-check nativo do Private Message.
- O item da inbox abre a thread autorizada em modal Drupal com histórico e Form API nativa para responder.
- Avatar autorizado e direção de envio chegam ao tema como render array e estado neutro; o SDC apresenta balões de entrada/saída.
- Site Alerts e comunicados podem ser dispensados individualmente; Flag mantém o estado por usuário e a contagem acompanha a dispensa.
- RUNTIME STATUS: DEFERRED nesta branch sem Runtime instalado; validar atualização de config e interação modal no Homelab.

## 0.1.1-dev.4 — fragmentos AJAX para gavetas Bootstrap

- Rotas same-origin de prévia retornam somente HTML renderizado e têm cache privado/no-store;
  a consulta ocorre ao abrir a gaveta, sem atualizações em segundo plano.
- RUNTIME STATUS: DEFERRED — conferir DI, cache e interação no Homelab.

## 0.1.1-dev.5 — contadores atualizados ao abrir as gavetas

- Prévia de conversas retorna o total atual de threads não lidas; prévia de avisos retorna Site Alerts visíveis mais comunicados persistentes ainda não lidos.
- O tema aplica a contagem e o rótulo acessível da barra na resposta da mesma requisição, sem polling.
- RUNTIME STATUS: DEFERRED — comportamento e contagem por conta precisam de teste HTTP autenticado.

## 0.1.1-dev.3 — gavetas de mensagens e avisos na barra

- A prévia privada passa a exibir até 50 conversas recentes dentro do painel e remove o link de saída
  para a caixa de mensagens.
- Avisos persistentes visíveis passam a exibir até 50 itens no painel junto com Site Alerts ativos.
- Os dois gatilhos permanecem restritos a contas autenticadas.
- Os endpoints dos painéis deixam explícito o cache desativado para que cada abertura Ajax consulte o estado atual.
- RUNTIME STATUS: DEFERRED — validação visual e funcional no Homelab pendente.

## 0.1.1-dev.2 — pedidos de amizade e privacidade de novas conversas

- `aculta_user` implementa amizade como entidade privada por par de membros, com estados pendente, aceita, recusada, cancelada e removida; API de serviço com DI, lock do Core e ID canônico único.
- Perfis públicos autenticados oferecem solicitar, cancelar e remover amizade por Form API/POST; `/mensagens/notificacoes` mostra pedidos recebidos e permite aceitar/recusar. O contador da barra inclui pedidos pendentes.
- Flood API limita novos pedidos de amizade a dez por membro por hora.
- Completa o índice do contrato Portal/tema para tokens de Messenger e leitura já usados pela integração.
- Preferência individual Todos/Somente amizades confirmadas salva em User Data pelo `aculta_messenger`; aplica-se somente ao início de thread. Administradores e editores ignoram essa preferência, nunca os bloqueios nativos.
- Atualização `aculta_user_update_10002()` instala a nova entidade em sites existentes.
- Composer audit revelou os advisories SA-CORE-2026-014/015/016 no Core 11.4.8; após dry-run, `composer.json`/lock atualizaram Core e `core-dev` para 11.4.10 e componentes Symfony compatíveis. `composer audit --locked`: PASS, sem advisories.
- Verificado: PHP lint das classes alteradas, YAML de metadados/serviços/permissões, Drupal 11+ gate (696), environment-address gate (1.595), composer validate e `composer audit --locked` sem advisories. O gate de slugs não iniciou: este worktree não contém `vendor/autoload.php`.
- RUNTIME STATUS: DEFERRED — sem instalação/atualização no Homelab, sem teste funcional entre usuários e sem envio de e-mails reais.

## 0.1.1-dev.1 — pesquisa e decisão de arquitetura

- Pesquisados Friendship, User Relationships, Flag, Private Message, Message e
  Message Notify em fontes oficiais; versões e código instalado do lock foram
  conferidos na branch local.
- Friendship declara compatibilidade com Drupal 11, mas não possui cobertura
  da política de advisories; sua implementação não atende diretamente à
  central privada de pedidos e notificações do ACULTA. User Relationships é
  legado para Drupal 7. Não instalar dependência adicional.
- Decidido implementar o workflow de amizade em `aculta_user` como entidade
  Drupal própria e serviço público; `aculta_messenger` consumirá essa API.
  Message/Message Notify mantêm notificações e Private Message mantém threads;
  Flag não será usado como máquina de estados. Os casos de uso do Friendship
  serão adaptados para os contratos DI, Entity API, access e Form API do Portal.
- Definida a preferência individual `todos`/`somente amigos`: limita somente
  novas conversas; respostas em threads existentes permanecem liberadas.
  Administradores e editores ignoram a preferência, mas não bloqueios nativos.
- RUNTIME STATUS: DEFERRED — somente documentação/pesquisa nesta subfase;
  implementação e validação seguem em dev.2/dev.3.

## 0.1.0-dev.10 — modais da barra e estado de leitura

- Adiciona modal same-origin de conversas e de avisos ativos na barra
  multidomínio, com ícones Bootstrap do tema e contador.
- A caixa de mensagens usa o estado nativo lido/não lido por conversa do
  Private Message. O resumo marca cada conversa acessivelmente e o contador
  continua sendo o total nativo de threads não lidas do membro.
- Cria o tipo editorial `aculta_notice`, segmentação OR pelos papéis de conta
  existentes, validade inicial/final e leitura individual pelo Flag não global
  `aculta_notice_read`. Acesso é verificado novamente ao listar e renderizar.
- Para membros, o contador do sino soma Site Alerts ativos e comunicados
  persistentes não lidos; para visitantes, conta Site Alerts ativos. Site Alert continua temporário e
  sem estado de leitura individual.
- Verificações estáticas: PHP lint, YAML parse, Drupal 11+ gate, SDC schema,
  fronteira módulo/tema, design foundations e endereços de ambiente: PASS.
- Gate de slug reporta apenas duas rotas upstream preexistentes do
  Message Subscribe. Composer audit: FAIL no Drupal Core 11.4.8, com três
  advisories publicados em 2026-10-14; sem atualização de Core nesta subfase.
- RUNTIME STATUS: DEFERRED; revisar AJAX/modal, cache e estado entre contas no
  Homelab.

## 0.1.0-dev.9 — integração visual ACULTA420

- O ACULTA420 apresenta listas, mensagens, notificações, composição nativa,
  Site Alert, estado vazio e contador de não lidas por componentes SDC.
- Formularios e entidades continuam sendo render arrays nativos do Drupal; o
  tema só os envolve e apresenta, sem autorização, storage ou lógica funcional.
- Componentes experimentais, claros/escuros por tokens compartilhados e sem
  JavaScript próprio. RUNTIME STATUS: DEFERRED; validação visual no Homelab
  ainda pendente.
- Drupal 11+ gate (696), SDC schema (21 componentes/334 checks), ACULTA420
  design foundations (1.816), module/theme boundary (907), unique paths,
  Portal UI docs, environment addresses (1.561) e PHP lint: PASS.
- Foundation gate que depende de bootstrap Drupal, Homelab, responsividade e
  acessibilidade visual: DEFERRED nesta cópia sem settings.

## 0.1.0-dev.8 — preferências de comunicação

- Adiciona `/configuracoes/mensagens` no purpose ACCOUNT e a aba Mensagens para
  contas com acesso nativo ao Private Message e avisos por e-mail habilitados.
- O opt-out lê e grava `private_message.receive_notification` através de
  `user.data`; o worker consulta a mesma preferência no momento do envio.
- O e-mail continua usando a ferramenta instalada no site, sem SMTP/transporte
  novo e sem conteúdo da conversa.
- Bloqueios continuam sendo ações nativas por membro; assinaturas permanecem
  por tópico na flag existente `seguir_topico`. Não foi criado storage paralelo.
- Preferências de comunicados persistentes, restrição global de mensagens e
  controles de eventos ainda inexistentes aguardam mecanismos e política.
- RUNTIME STATUS: DEFERRED — sem execução no Homelab ou teste entre contas.

## 0.1.0-dev.5 — assinaturas de tópicos do fórum

- Reutiliza a flag existente `seguir_topico` em Nodes `forum`; não cria outra
  flag e não ativa `message_subscribe_ui`.
- Enfileira a primeira publicação; o worker revalida publicação atual e gera
  link pelo purpose `forum` via `DomainPurposeManager`. Sem alias português, não
  envia link com slug técnico.
- Seleção de destinatários usa a API pública de Flag; Message Subscribe aplica
  status de conta e acesso atual do Node.
- Evento Message registra Node, revisão, estado e tentativas. Lock e chave de
  deduplicação protegem reprocessamento; erros deixam evento pendente para retry.
- A entrega passa pelo notifier configurado e ferramenta de e-mail já instalada
  via Drupal Mail; nenhum transporte SMTP novo é criado.
- Group permanece fora: `gnode` não está habilitado no projeto.
- RUNTIME STATUS: DEFERRED — teste de assinatura, access e transporte controlado
  aguardam Homelab; sem mudança de banco ou Config Sync.
- Verificações locais: PHP lint, YAML parse, gate Drupal 11+ (696 checks),
  endereços de ambiente (1.518 arquivos) e `git diff --check`: PASS.
- `validate-public-slugs.php`: FAIL pelas mesmas duas rotas de
  `message_subscribe_ui`; gate e allowlist permanecem intactos.

## 0.1.0-dev.6 — integração administrativa de Site Alert

- Adiciona a instância do bloco nativo Site Alert à região Destaques do ACULTA420,
  disponível nos purposes que usam esse tema. Agenda e severidade vêm da
  entidade Site Alert; o intervalo de atualização é 300 segundos.
- Criação, edição e remoção continuam nos formulários/permissões nativos do
  Site Alert; as rotas administrativas permanecem sob o purpose `main`.
- Nenhuma role recebe permissão de Site Alert automaticamente; não há grants
  atuais às permissões nativas na configuração exportada.
- Comunicados persistentes Node/Views não foram criados: os tipos atuais não
  fornecem segmentação de acesso verificável nem fluxo de revisão atribuído para
  esse propósito. Evita-se publicar um aviso como conteúdo público sem contrato.
- `config/sync/core.extension.yml` habilita `datetime_range` e `site_alert`, e
  `config/sync/block.block.aculta_messenger_site_alert.yml` coloca o bloco.
- RUNTIME STATUS: DEFERRED — o Config Sync não foi importado; bloco, agenda e
  apresentação ainda exigem teste no Homelab. Banco inalterado.
- Verificações locais: PHP lint, YAML parse, gate Drupal 11+ (696 checks),
  endereços de ambiente (1.519 arquivos) e `git diff --check`: PASS.
- `validate-public-slugs.php`: FAIL pelas mesmas duas rotas de
  `message_subscribe_ui`; gate e allowlist não foram alterados.

## 0.1.0-dev.7 — seção Mensagens na Minha Conta

- Cria `/mensagens`, `/mensagens/conversas`, `/mensagens/avisos` e
  `/mensagens/notificacoes`, todas no purpose `account`, com acesso autenticado
  e permissões nativas do Private Message.
- Conversas encaminha para a inbox contrib por URL ACCOUNT confiável. Contagem
  de não lidas usa `PrivateMessageServiceInterface`; a navegação só inclui a
  seção quando a conta já tem as permissões exigidas.
- Atualiza o link dos e-mails privados para a rota central `/mensagens` no
  purpose `account`.
- Notificações lista apenas Messages `sent` do bundle privado cujo owner UID é a
  conta atual. Query `accessCheck(FALSE)` justificada pelo ausência de handler
  de ownership no Message; UID é restrição obrigatória e resposta privada.
- Portal adiciona alters neutros de seções/subrotas ao `AccountShellBuilder`;
  rotas nativas Private Message são classificadas em `account`. Formulários,
  inbox e armazenamento permanecem contrib.
- Avisos é estado vazio até a implementação de comunicados persistentes.
- Verificações: PHP lint, YAML, Portal gate (696), module/theme boundary (873),
  unique paths, portal UI docs (167 tokens/132 usados/41 atributos) e
  environment addresses (1.523 arquivos): PASS.
- `validate-public-slugs.php`: FAIL pelas mesmas duas rotas do
  `message_subscribe_ui`. `validate-aculta-portal-conta-root.php` não inicia
  porque procura diretório `css/` ausente também em `origin/main`.
- Shell/Domain gates exigem bootstrap Drupal e não rodaram sem settings/runtime.
- RUNTIME STATUS: DEFERRED — fluxo de conta, acesso entre purposes e Config
  Sync aguardam Homelab; nenhum import foi executado.

## 0.1.0-dev.4 — avisos privados por Message Notify

- Enfileira eventos do hook nativo de nova mensagem e resolve a thread após o
  salvamento, usando Queue API e lock do Core.
- Grava notificações como entidades Message com referência ao Private Message de
  origem, destinatário e estado; retries consultam a entidade para deduplicação.
- Usa `message_notify.sender` e Drupal Mail já instalado, com assunto genérico,
  corpo sem conteúdo da conversa e link para a raiz do purpose `account`.
- Revalida usuário ativo, permissões, membership/access e bloqueios nas duas
  direções. Limita tentativas de entrega a três.
- Lifecycle guard bloqueia Private Message Notify em conjunto (template
  upstream inclui o corpo privado) e protege uninstall com notificações/fila
  pendentes.
- RUNTIME STATUS: DEFERRED — sem módulo habilitado, envio real ou validação no
  Homelab. Sem mudanças de banco ou Config Sync.
- Verificações estáticas: PHP lint, class loading/interface, YAML de instalação,
  gate Drupal 11+ (696 checks), endereços de ambiente (1.506 arquivos) e
  `git diff --check`: PASS.
- `validate-public-slugs.php`: FAIL por duas rotas de `message_subscribe_ui`
  dentro do pacote contrib recém-instalado. O submódulo de UI não é dependência
  nem será habilitado por esta fase; gate e allowlist permanecem intactos. A
  incompatibilidade precisa de solução antes da homologação/merge.

## 0.1.0-dev.3 — integração inicial de mensagens privadas

- Acrescenta callback OOP `#[Hook('form_alter')]` ao formulário nativo de
  criação/resposta do Private Message. O Form API contrib continua controlando
  destinatários, validação, salvamento e renderização.
- Acrescenta rate limit pelo serviço Flood do Core: dez envios válidos por UID
  em uma hora; validações que já falharam não registram envio.
- Mantém bloqueio, threads, entidades, caixa de entrada, contagem de não lidas e
  handlers de acesso no Private Message. Nenhuma permissão é concedida a role.
- Aplica bloqueio nos dois sentidos no formulário por meio do serviço de ban
  nativo, sem revelar a identidade do usuário bloqueador.
- RUNTIME STATUS: DEFERRED — módulo não habilitado; fluxo, fallback sem JS,
  isolamento entre usuários e callback Flood aguardam validação no Homelab.

## 0.1.0-dev.2 — módulo integrador e dependências

- Adiciona `aculta_messenger.info.yml` como submódulo opcional do Portal, com
  dependências explícitas nos cinco módulos integrados.
- Adiciona ao Composer as versões estáveis resolvidas: Private Message 3.0.7,
  Message 1.9.0, Message Notify 1.5.0, Message Subscribe 2.1.0 e Site Alert
  1.4.0. O dry-run indicou cinco instalações, nenhuma atualização/remoção.
- Código-fonte foi instalado em `web/modules/contrib` somente pelo Composer e
  não será versionado. Não ativou módulos, executou import/export de config ou
  alterou banco/runtime.
- Confirma o inventário upstream nos arquivos efetivamente instalados; ver
  `docs/INTEGRATIONS.md` e `docs/ARCHITECTURE.md`.
- E-mail reutiliza Drupal Mail com Symfony Mailer/Mailer Plus existente, pela
  ferramenta de entrega já instalada. Não instala SMTP nem cria transporte.
- Verificações: `composer validate` válido (com warnings preexistentes de
  constraints exatas), `composer audit` sem advisories, Drupal extension
  discovery PASS,
  gate do Portal PASS (692 checks), metadata YAML PASS e links Markdown PASS.
  Instalação Drupal em banco/runtime e PHP 8.5 permanecem pendentes.
- `git diff --check`: PASS.
- RUNTIME STATUS: DEFERRED.

## 0.1.0-dev.1 — fundação arquitetural

- Documenta fontes de verdade, contratos de Domain, públicos, privacidade,
  dependências candidatas e plano incremental.
- Registra que as cinco dependências ainda não estão no lock nem instaladas.
- Nenhuma mudança funcional ou instalação Composer.
- STATIC: PASS — `git diff --check` e links Markdown internos (nove arquivos).
- RUNTIME STATUS: DEFERRED (sem código funcional).

### Classes e hooks da integração de amizade

- `FriendRequestsForm`: lista pedidos direcionados à conta atual e trata aceitação/recusa por Form API.
- `MessagesController::notifications()`: agrega os pedidos recebidos e notificações Message privadas existentes.
- `MessengerBarHooks::preprocessPage()`: soma pedidos pendentes ao contador privado da barra.
- `PrivateMessageFormHooks::formAlter()` e `PrivateMessageFormValidator::validateNewConversationPrivacy()`: aplicam a preferência apenas ao criar conversa.
## 0.1.1-dev.72 — transfere agregados de respostas ao mover comentário — 2026-10-21

- Assina `aculta_forum.comment_subtree_moved` e transfere registros completos `forum_resposta` da origem para o tópico destino. Ao colidir com registro do mesmo usuário/tipo no destino, soma contadores e mantém o maior `ultimo_comentario`. Unit do assinante: 2 testes / 4 assertions PASS.
- A transferência ocorre na transação do mover; tópicos de destino não publicados, incompatíveis ou inacessíveis não recebem os registros. A decisão funcional aceita que o agregado inclua respostas não movidas.
- Dependência explícita de `aculta_forum`. Nenhum envio retroativo de e-mail é disparado.

## 0.1.1-dev.77 — busca de membros com região viva nomeada

- Prévia de membros agora é uma região `aria-live` com rótulo; seus resultados
  são links que se percorrem com Tab, não opções selecionáveis de combobox.
- Runtime e smoke autenticado do drawer permanecem pendentes; nenhum dado ou
  mensagem foi criado.

## 0.1.1-dev.86 — cobrir o round-trip dos eventos de Fórum — 2026-10-24

- Kernel passa a exercer Comment e revisão de tópico pelo publisher e consumer
  compartilhados, validando replay idempotente, agregação em `aculta_aviso` e
  exclusão do autor. A cobertura do pipeline passa a 20 testes / 458 assertions.
- Unit Messenger: 70 / 334; Unit LMS Messenger: 21 / 83. Runtime continua sem
  homologação.

## 0.1.1-dev.87 — adaptar eventos de amizade ao contrato compartilhado — 2026-10-24

- `aculta_user` mantém a propriedade do workflow; o novo producer converte
  pedido e aceite em `MessengerNoticeEvent`. O consumer revalida a amizade por
  UUID, status e destinatário, preserva a chave armazenada no `Message` atual e
  continua enfileirando o e-mail transacional mínimo.
- Unit Messenger: 72 testes / 345 assertions; PHPCS Drupal/DrupalPractice:
  PASS. A prova Kernel do pipeline User e Runtime não foram executados.

## 0.1.1-dev.88 — provar revogação no consumo de eventos do Fórum — 2026-10-24

- Kernel cobre acesso revogado depois da publicação do envelope e antes do
  consumo: o agregador não persiste aviso nem chave no ledger.
- A fixture instala o schema de Comment antes da configuração que cria seu
  campo de corpo, removendo a depreciação Core do Drupal 11.2. Kernel MariaDB:
  22 testes / 489 assertions, sem depreciações. Comparação Kernel confirma que
  o fluxo direto e o envelope compartilham o agregado existente sem duplicar
  a linha; Unit Messenger: 72 / 345.
- PHPCS Drupal/DrupalPractice, PHP lint e YAML dos serviços: PASS. Sem Runtime.

## 0.1.1-dev.89 — provar o resumo combinado do Fórum — 2026-10-24

- Kernel mistura registro direto e evento compartilhado no mesmo agregado e
  verifica uma linha de resumo com quantidade 2. O ACULTA Mailer é mockado; o
  reprocessamento não envia outro e-mail.
- O fixture usa só o idioma padrão `pt-br`. Kernel MariaDB: 23 testes / 515
  assertions, sem depreciações.

## 0.1.1-dev.90 — corrigir leitura dos avisos no tópico — 2026-10-24

- O Router do Drupal converte o parâmetro canônico `node` em `NodeInterface`
  antes do subscriber de requisição. `ForumAvisoReadSubscriber` agora aceita a
  entidade convertida e o ID numérico legado, delegando a revalidação de acesso
  ao `AvisoService` por um contrato mínimo de leitura.
- Unit Messenger: 75 testes / 352 assertions; PHPCS Drupal/DrupalPractice e
  PHP lint nos arquivos alterados: PASS. Runtime recebeu rebuild de cache e smoke HTTP após correção; não homologado
  como Alpha.

## 0.1.1-dev.91 — aplicar F-D21 no alter de destinatários — 2026-10-24

- O hook aditivo `message_subscribe_get_subscribers` não removia os destinatários
  padrão do contrib; retornar `[]` apenas não acrescentava outros candidatos.
  Substituído por `message_subscribe_get_subscribers_alter`, que remove a entrega
  Message Subscribe somente quando o conteúdo-alvo é tópico ACULTA. Outros
  contextos preservam os candidatos existentes; os avisos do Fórum seguem pelo
  envelope compartilhado e `aculta_aviso`.
- Unit Messenger: 78 testes / 355 assertions, sem depreciações; PHPCS
  Drupal/DrupalPractice e PHP lint: PASS. Kernel e Runtime não executados nesta
  revisão.

## 0.1.1-dev.92 — cobrir Comments hospedados em tópicos do Fórum — 2026-10-24

- F-D21 agora também reconhece o contexto de Comment cujo host é um tópico
  `aculta_forum_topic`. Isso impede que Flags do Node anfitrião adicionem
  destinatários Message Subscribe quando a Message é enviada sobre um Comment.
- Unit Messenger: 79 testes / 356 assertions, sem depreciações; PHPCS e PHP
  lint: PASS. Kernel e Runtime não executados nesta revisão.

## 0.1.1-dev.94 — completar estados de entrega de e-mail — 2026-10-24

- O campo compartilhado Message `field_aculta_msg_delivery` admite `cancelled`
  para opt-out/restrição administrativa em workers de canal direto. Configuração
  nova inclui o estado; update `10001` o adiciona de forma aditiva em instalações
  existentes. Não altera avisos no site nem registros históricos.
- O adapter Wiki e o LMS usam a preferência central off/resumo/direto; Kernel do
  update não executado (SIMPLETEST_DB ausente). PHPCS/lint/YAML são registrados
  no incremento que consome esse estado.

## 0.1.1-dev.139 — reforçar idempotência concorrente do digest — 2026-10-24

O ACULTA Mailer serializa entregas com correlação UUID v5 sob lock persistente compartilhado. Uma segunda execução
concorrente retorna falha temporária sem chamar o transporte; um retry após aceite registrado volta a ser suprimido.
O digest continua usando seu lock por destinatário e confirmação transacional dos avisos. A janela abrupta entre aceite
externo e persistência local permanece limitada pelo contrato do SMTP/provedor.

Validação: `AcultaMailerKernelTest` 4/20 PASS, incluindo lock ocupado por backend persistente separado; digest
`ForumNoticeIdempotency` permanece coberto por Kernel MariaDB e retry com correlação estável. Sem Runtime, SMTP real ou
processos independentes.

## 0.1.1-dev.140 — revalidar lease na recuperação do outbox — 2026-10-24

A recuperação cron do e-mail direto do Fórum agora revalida `email_updated` e os estados vencidos no próprio UPDATE,
evitando que uma linha reclamada por um worker entre a consulta de IDs antigos e a gravação seja rebaixada para
pendente. O comportamento mantém a fila existente e não adiciona schema.

Validação: teste focal `testOutboxCronRecoversExpiredQueueLeases` 1/23 e Kernel completo
`ForumNoticeIdempotencyKernelTest` 31/700 PASS; PHPCS Drupal/DrupalPractice, PHP lint e índice de status PASS. Sem
Runtime ou concorrência real entre processos.

## 0.1.1-dev.141 — contar apenas mensagens privadas persistidas — 2026-10-24

A verificação de flood continua na validação, mas o contador só é registrado depois do salvamento bem-sucedido da
mensagem. O caminho nativo de resposta registra após os handlers nativos, a criação de conversa em grupo registra após
salvar mensagem e thread, e a resposta pela gaveta registra após persistir ambos. Assim, rejeições de validações
posteriores e falhas de persistência não consomem a cota horária.

Validação: Unit focal de privacidade/hooks 12/41 e suíte Unit Messenger 135/794 PASS; PHP lint dos arquivos alterados
PASS. PHPCS limpo nos validators e teste novo; classes maiores mantêm avisos/erros anteriores não relacionados.
Kernel de persistência de conversa e Runtime não executados nesta microfase.


## 0.1.1-dev.142 — validar versão persistida dos eventos de amizade — 2026-10-24

O adapter do ACULTA User e o consumer do Messenger agora confirmam que `changed` no evento corresponde à versão persistida da amizade. Eventos internos obsoletos ou com versão divergente não são publicados nem criam aviso, preservando a deduplicação da transição original.

Validação: `FriendshipNoticeEventProducerTest` e `FriendshipNoticeSubscriberTest`, 10 testes / 91 assertions PASS; suíte Unit Messenger completa, 137 testes / 820 assertions PASS; PHPCS Drupal/DrupalPractice e PHP lint dos arquivos alterados PASS. Kernel integrado User/Messenger, Runtime e envio de e-mail não executados nesta microfase.
## 0.1.1-dev.148 — confirmar destinatário no retry do digest — 2026-10-24

A reconciliação do snapshot do digest agora confirma que o Mailer aceitou correlação, tipo e destinatário atuais. O Mailer compara o endereço recebido apenas pelo HMAC armazenado no log. Isso impede que um snapshot incompatível ou corrompido confirme pendências de outra conta; o snapshot continua sem endereço.

Validação: Kernel Mailer com destinatário correto/incorreto; Integration LMS confirma o destinatário recebido na recuperação. Demais gates pendentes nesta microfase.

## 0.1.1-dev.149 — filtrar avisos na gaveta multidomínio — 2026-10-24

A gaveta de avisos agora permite filtrar origem e leitura, preservando a combinação selecionada ao carregar o fragmento. Sem JavaScript, os destinos levam à central da Conta no alias do ambiente. A atualização mantém contagem no ícone correto e estados de carregamento/erro específicos de avisos.

Validação: PHP lint, JS syntax, `NoticeListFilterTest` 5/17 PASS, Firefox ESR/X11 no Runtime de teste: gaveta 6/6 em viewport real 1270 px e 6/6 em viewport real 500 px; central-empty-state 4/4 após migrar o estado vazio para tokens. O pedido de 390 px foi limitado pelo Firefox a 500 px. O primeiro smoke encontrou TypeError no rótulo traduzido e HTTP 500; conversão corrigida e o smoke subsequente passou. MessagesController ficou abaixo do baseline PHPCS (95 erros/65 avisos contra 97/65). Os atributos e tokens do Messenger desta fase foram incluídos no contrato visual; `validate-portal-ui-docs.php` passou de 70 para 62 gaps de outras frentes.

## 0.1.1-dev.158 — caracterizar pipeline Kernel de avisos de amizade — 2026-10-24

O Kernel MariaDB agora percorre o `FriendshipManager` real, despacha os eventos de pedido e aceite, executa o producer e
subscriber do Messenger, persiste Messages e verifica o encaminhamento à fila. O replay de um pedido pendente permanece
idempotente. O fixture registra a entidade de amizade pelo hook de teste, sem instalar nem assumir propriedade do
workflow de `aculta_user`.

Validação: `FriendshipNoticePipelineKernelTest` **1/14 PASS** em MariaDB dedicada; PHPCS Drupal/DrupalPractice e PHP
lint PASS. PHPUnit reportou duas deprecações do Core/contrib para plugins Message baseados em annotations. Runtime,
SMTP e transporte real não foram usados.

## 0.1.1-dev.160 — provar lease expirada sem perda de eventos — 2026-10-24

O Kernel MariaDB simula a expiração da lease dentro do transporte, permite que outro backend processe o digest e cria
um evento após a captura do segundo lote. O worker antigo falha ao renovar a posse e não confirma o próprio contador;
o terceiro evento permanece pendente. O teste confirma a proteção com persistência real e lock compartilhado no banco.

Validação: `ForumNoticeIdempotencyKernelTest` **34/776 PASS**, `FriendshipNoticePipelineKernelTest` **1/14 PASS** e
Unit/Integration de digest **18/195 PASS**; PHPCS e PHP lint PASS. O teste usa dois backends no mesmo processo; dois
boots Drupal concorrentes e transporte SMTP permanecem pendentes.

## 0.1.1-dev.159 — renovar lock antes de confirmar digest — 2026-10-24

Após o transporte, o worker renova a lease do destinatário antes de confirmar ou descartar linhas. Se outro processo
assumiu o lock expirado, o worker antigo não altera os avisos nem o snapshot; a confirmação é deixada para a execução
que detém o lock. Isso protege o contador do Fórum e os acknowledgements dos adapters contra uma confirmação concorrente
por um worker obsoleto.

Validação: `AvisosResumoProviderPreferenceTest` + `LearningNoticeDigestIntegrationTest` **18/195 PASS**, incluindo
concorrência durante transporte e perda do lock renovado; Kernels MariaDB de idempotência e amizade **34/759 PASS**, incluindo contenção do lock entre processos. Dois workers Drupal completos disputando o
transporte em processos independentes e suspensão acima da lease seguem pendentes. PHPCS Drupal/DrupalPractice PASS. Duas deprecações de plugins Message via annotations foram reportadas pelo Kernel de
amizade. Sem Runtime ou SMTP.

## 0.1.1-dev.163 — exigir CSRF de rota nas ações POST — 2026-10-24

As quatro rotas POST-only de resposta e ações de relacionamento agora exigem também o `_csrf_token` do Core. Os formulários
continuam usando seus tokens/validações próprios; essa camada valida a origem da requisição no roteamento. GET/POST de
composição não receberam esse requisito, pois precisam permitir navegação GET e já passam pelo Form API no POST.

Validação: `PostRouteCsrfRequirementTest` **1/5 PASS**, PHP lint, PHPCS, gate Drupal 11+ e índice de status PASS. O teste
verifica o contrato YAML das rotas; POST HTTP integrado e Runtime continuam pendentes.

## 0.1.1-dev.164 — revalidar idempotência do digest — 2026-10-24

Revalidação regressiva conjunta do resumo do Fórum, recuperação de snapshots aceitos e deduplicação persistente do
ACULTA Mailer. Não foi necessária alteração na lógica de entrega nesta microfase.

Validação: `ForumNoticeIdempotencyKernelTest` **34/776 PASS**; `AcultaMailerKernelTest` **5/28 PASS**;
`AvisosResumoProviderPreferenceTest` + `LearningNoticeDigestIntegrationTest` **18/195 PASS**. Sem SMTP externo ou Runtime;
dois ciclos completos do digest em boots Drupal concorrentes continuam pendentes.

## 0.1.1-dev.165 — preservar manifesto durante transporte concorrente — 2026-10-24

Se uma lease expirar enquanto a chamada de transporte ainda está em andamento, outro worker não apaga o snapshot não
aceito para montar um manifesto maior com nova correlação. Ele aguarda até o fim da janela de 180 s; o worker original
que perdeu a lease não confirma linhas, e o cron seguinte reconcilia o aceite persistido do Mailer. Se não houve aceite
ao fim da janela, o retry reconstrói o lote pendente. Isso evita sobreposição de e-mails durante o timeout de transporte
configurado, preservando o storage existente em `user.data`.

Validação: `ForumNoticeIdempotencyKernelTest` **34/773 PASS**; Unit/Integration de digest **18/195 PASS**; PHPCS e PHP
lint PASS. O teste injeta uma lease expirada enquanto o transporte está ativo, confirma que o novo worker não envia um
segundo manifesto e verifica recuperação posterior sem nova entrega.

## 0.1.1-dev.166 — agrupar avisos relacionados na central — 2026-10-24

A lista agrupa avisos Message de LMS, Wiki, Loja e Doações quando compartilham a mesma origem e UUID do conteúdo. O grupo preserva a ordem e mantém links, estado de leitura e formulários de leitura individuais. A chave é um hash transitório de render e é removida antes da saída. Fórum permanece com sua agregação nativa por tópico; armazenamento e deduplicação não mudam.

Validação: `NoticeRelatedGrouperTest` 2/13 PASS; PHPCS Drupal/DrupalPractice dos arquivos novos e adapters tocados PASS; lint PHP focal PASS; smoke visual pendente.

## 0.1.1-dev.167 — deduplicar linhas de digest por delivery ID — 2026-10-24

O Messenger agora remove linhas duplicadas emitidas pelo mesmo provider antes de montar o corpo e o manifesto do digest. Um `delivery_id` representa uma única notificação persistida e aparece/é confirmado uma só vez, mesmo se uma consulta do provider duplicar o registro.

Validação: `LearningNoticeDigestIntegrationTest` 15/118 PASS; suíte LMS Integration Unit + `AvisosResumoProviderPreferenceTest` 38/371 PASS; Kernel MariaDB de recuperação/lease 2/54 PASS; Unit Messenger 150/932 PASS; PHPCS Drupal/DrupalPractice e PHP lint PASS. Sem Mailer real ou Runtime.

## 0.1.1-dev.169 — falhar fechado em categoria de e-mail desconhecida — 2026-10-24

`AvisoPreferencias` agora permite modos somente para o catálogo compartilhado Fórum, LMS, Wiki, Loja e Doações. Categoria desconhecida recebe apenas `off`, não lê nem grava preferência individual e não pode habilitar digest ou envio direto. Avisos no site e os contratos dos produtores válidos não mudam.

Validação: preferências, formulário e digest focalizados 14/149 PASS; Unit Messenger 152/941 PASS; PHPCS Drupal/DrupalPractice e PHP lint PASS. O gate exigiu regenerar o índice após a atualização de versão.

## 0.1.1-dev.170 — reutilizar flags e acesso no contador de Site Alerts — 2026-10-24

O contador do header agora carrega estados pessoais de dispensa em lote, após filtrar alertas pelo acesso. A central compartilha com o contador os alertas visíveis e estados de Flag já consultados, sem refazer a busca, autorização e lookup individual para contar. Critérios de visibilidade, cache tags e comportamento anônimo são preservados.

Validação: `MessengerCountsTest` 3/27 PASS; combinado com `PersistentNoticeAccessTest` 8/38 PASS; Unit Messenger 155/968 e `AcultaMessengerServiceProviderTest` 4/14 PASS; PHPCS dos arquivos novos/serviço, PHP lint, índice e gate Drupal 11+ (1.155 checks) PASS. O PHPCS de `MessagesController` mantém baseline anterior; Runtime/navegador não executados.

## 0.1.1-dev.206 — paginar avisos do Fórum na central da Conta

A central oferece navegação anterior/seguinte quando a origem Fórum está selecionada, permitindo consultar históricos além dos 100 agregados por página. A consulta aplica o estado de leitura antes do limite, ordena com desempate estável e revalida acesso aos tópicos antes de renderizar. Gaveta e origens com adapters próprios mantêm seu fluxo; paginação unificada das demais origens ainda está pendente.

Validação: `ForumNoticeIdempotencyKernelTest` **36 testes / 834 assertions PASS** em MariaDB dedicado, incluindo a cobertura de páginas completas e sem sobreposição. PHP lint e PHPCS Drupal/DrupalPractice nos arquivos sem baseline PASS; controllers mantêm seus baselines preexistentes (MessagesController 95/65; AvisosMensagensController 15/12). `git diff --check` e índice de status PASS. Browser, Runtime e paginação das outras origens não executados.

## 0.1.1-dev.207 — preservar retry quando falha a descoberta de destinatários do digest

Quando `pendingRecipientIds()` de qualquer adapter falha, o digest ainda pode entregar fontes independentes, mas não atualiza o intervalo compartilhado de 20 horas. O snapshot persistido conserva a falha e impede que a recuperação de um envio aceito aplique o intervalo antes de a fonte indisponível voltar. A fixture agora declara identidades estáveis distintas, caracterizando o caso sem colisão artificial de classe.

Validação: `AvisosResumoProviderPreferenceTest` **4/79 PASS**, `LearningNoticeDigestIntegrationTest` **21/162 PASS** e `AvisosResumoMailerKernelTest` **2/31 PASS** em MariaDB dedicado. PHPCS Drupal/DrupalPractice, PHP lint e `git diff --check` PASS. Transporte externo e Runtime não executados.

## 0.1.1-dev.208 — manter cooldown aberto durante recovery após falha do adapter

A falha de `pendingRecipientIds()` no cron atual agora também é considerada ao reconciliar um snapshot aceito anteriormente. Assim, a recuperação confirma somente o lote já entregue, remove o snapshot após o acknowledgement e não grava o intervalo compartilhado enquanto uma origem pode continuar sem entregar destinatários pendentes. Uma regressão cobre a falha ocorrida depois de um snapshot antigo com `provider_lookup_failed: false`.

Validação: `LearningNoticeDigestIntegrationTest` **22 testes / 176 assertions PASS**, `AvisosResumoProviderPreferenceTest` **4/79 PASS** e `AvisosResumoMailerKernelTest` **2/31 PASS** em MariaDB dedicado. PHPCS Drupal/DrupalPractice, PHP lint e `git diff --check` PASS. Transporte externo e Runtime não executados.

## 0.1.1-dev.209 — paginar adapters da central por origem

O contrato opcional `PaginatedPersonalNoticeAdapterInterface` permite que LMS, Wiki, Loja e Doações entreguem páginas de 100 itens na central quando a pessoa seleciona uma origem. Os queries mantêm filtro de leitura antes do limite e ordenação por criação/ID; cada manager continua dono da revalidação de acesso e `Message`. A paginação usa offset por origem, links acessíveis anterior/seguinte e não altera os limites antigos da gaveta. Fórum continua usando seu próprio pager. Comunicados/Site Alert e paginação da lista sem filtro ficam fora desta microfase.

Validação: dispatch opcional `PersonalNoticeAdapterHooksTest` **6/65 PASS**; Commerce Unit **5/58**, Wiki Unit **3/45**, Kernel LMS **4/47**, Wiki **7/129**, Commerce **4/67** e Doação **5/80 PASS**. PHPCS Drupal/DrupalPractice limpo nos arquivos novos/adapters; controllers mantêm baseline (MessagesController 95/65; AvisosMensagensController 15/12). PHP lint, `git diff --check` e gate Drupal 11+ PASS. Kernels reportam deprecações de annotations Message Core/contrib; visual/Runtime não executados.

Revalidação de idempotência do digest: `LearningNoticeDigestIntegrationTest` **22/176**, `AvisosResumoProviderPreferenceTest` **4/79**, `AvisosResumoMailTypeTest` **2/7** e Kernels Messenger/Mailer **38/865 em conjunto** PASS no MariaDB dedicado. A execução cobriu recuperação do lote aceito e dois workers Drupal independentes; não usou SMTP real nem Runtime.

Auditoria focal dos modos de e-mail — **42 testes / 445 assertions PASS** para preferências pessoais, opções administrativas e workers diretos de Fórum, LMS, Wiki, Loja e Doações. README e configuração agora descrevem a propriedade do Messenger sobre os modos por categoria (`off`, direta, resumo), a obrigatoriedade do aviso no site e a separação da preferência nativa das conversas Private Message. Sem SMTP real ou homologação Runtime.

## 0.1.1-dev.210 — paginar Comunicados e Alertas do Site por origem

A central da Conta pagina os Nodes de comunicado com access e leitura aplicados antes da seleção de até 100 resultados; a ordenação é estável por criação e ID. Alertas do Site mantêm access e dismissal, ordenação estável por ID e filtro de leitura antes do slice. A navegação anterior/seguinte reutiliza `notice_offset`; prévia da gaveta e lista global conservam o contrato existente.

Validação: `PersonalNoticePageStateTest`, `PersonalNoticeAdapterHooksTest` e `NoticeListFilterTest` **15/93 PASS**. PHPCS Drupal/DrupalPractice limpo nos arquivos novos/testes; `MessagesController` mantém baseline **95/65**. PHP lint e `git diff --check` PASS. Browser visual não executado: `DISPLAY` não está definido; Runtime não homologado.

## 0.1.1-dev.211 — detectar a próxima página de avisos persistentes

A paginação de Comunicados agora percorre o próximo aviso elegível após preencher a página para definir `hasNext`.
Itens inacessíveis e já lidos continuam sem consumir offset ou capacidade. Chamadas sem paginação mantêm o limite anterior.

Validação: `PersistentNoticePaginationTest` **1/44 PASS**; estado da paginação, dispatch de adapters e filtros
**15/93 PASS**; PHPCS Drupal/DrupalPractice do novo teste, PHP lint e `git diff --check` PASS. O PHPCS do controller
continua com o baseline existente de **95 erros / 65 avisos**. Runtime e navegador não executados.

## 0.1.1-dev.212 — preservar aceite de digest além da retenção do Mailer

O snapshot do digest registra `accepted_at` assim que o Mailer confirma o transporte. Snapshots legados recebem o mesmo
marcador quando o Mail Log confirma o aceite. A recuperação passa a usar essa evidência persistida para aguardar adapters
opcionais sem depender da retenção normal do Mail Log nem reenviar o lote já aceito; os acknowledgements continuam
limitados aos IDs do manifesto original.

Validação: `LearningNoticeDigestIntegrationTest` **23/186 PASS**, `AvisosResumoProviderPreferenceTest` **4/87 PASS**,
`AvisosResumoMailerKernelTest` **2/32 PASS**, incluindo recuperação cross-process após remoção do Mail Log, e
`ForumNoticeIdempotencyKernelTest` **36/834 PASS** no MariaDB dedicado;
PHPCS Drupal/DrupalPractice, PHP lint, índice de status e gate Drupal 11+ (**1155 checks**) PASS. SMTP externo e Runtime
não executados.

## 0.1.1-dev.213 — paginar somente avisos ainda acessíveis

A central varre candidatos em ordem estável, mas aplica offset e lookahead depois de revalidar acesso atual para Fórum,
LMS, Wiki, Loja e Doações. `EligibleNoticePage` unifica a varredura dos adapters Message; o Fórum carrega os tópicos
de cada lote uma vez. Filtros de leitura continuam no EntityQuery, e avisos inacessíveis não consomem a página nem
ocultam itens autorizados mais antigos.

Validação: `EligibleNoticePageTest` **2/7 PASS**, `ForumNoticeIdempotencyKernelTest` **37/870 PASS**, conjunto Unit
focal **48/389 PASS** e Kernels LMS/Wiki/Loja/Doações **22/377 PASS**. PHPCS Drupal/DrupalPractice, PHP lint, índice
de status e gate Drupal 11+ (**1155 checks**) PASS. Kernels reportaram deprecações de descoberta Message Core/contrib e
notices PHPUnit existentes. Runtime e navegador não testados.

## 0.1.1-dev.216 — recuperar digest após falha ao gravar aceite

Adicionada regressão para a janela em que o ACULTA Mailer já aceitou o digest, mas o processo falha antes de persistir
`accepted_at`. No retry, o Messenger consulta a correlação determinística no Mailer, confirma somente os IDs do manifesto
original e não realiza uma segunda entrega.

Validação: `LearningNoticeDigestIntegrationTest` **24 testes / 205 assertions PASS**; `AvisosResumoMailerKernelTest`
**2 testes / 32 assertions PASS** com ACULTA Mailer e MariaDB dedicado. Nenhum Runtime ou transporte SMTP real
foi utilizado.

## 0.1.1-dev.217 — provar recuperação do digest com Mailer real

O Kernel agora usa o ACULTA Mailer e seu Mail Log reais, interrompe o fluxo depois do aceite e antes de `accepted_at`, e
reexecuta o digest. O retry reconhece a mesma correlação, confirma o manifesto original e mantém apenas um transporte.

Validação: `AvisosResumoMailerKernelTest` **3 testes / 47 assertions PASS** com MariaDB dedicado; PHPCS e PHP lint PASS.
Runtime e SMTP externo não executados.

## 0.1.1-dev.218 — centralizar novos avisos de Mensagem Privada no Mailer

Novos eventos de mensagem privada marcam sua entrega para o ACULTA Mailer, usam template mínimo sem o corpo privado e
correlação idempotente por mensagem/destinatário. Registros antigos sem marcador permanecem temporariamente no fluxo
Message Notify para não presumir se o transporte já aceitou o e-mail. O update 10008 instala o campo de ownership sem
migrar filas ou apagar dados; a retirada do caminho legado exige evidência operacional de fila drenada.

Validação: Unit Messenger + Integration Unit LMS/Wiki/Loja/Doações **253 testes / 1.932 assertions PASS**;
`PrivateMessageRetentionKernelTest` **2 testes / 29 assertions PASS** no MariaDB dedicado (10 deprecações de discovery e
assinaturas de Message/Private Message Core/contrib); PHPCS Drupal/DrupalPractice e PHP lint PASS. Runtime e e-mail externo
não executados.

## 0.1.1-dev.247 — migrar preferências de e-mail privado para o Messenger

O Messenger passa a ser a fonte única das preferências de e-mail de Mensagens Privadas. O update incremental copia a política administrativa e as escolhas individuais de `private_message.receive_notification`, preserva valores Messenger existentes e remove cada chave individual antiga após a cópia. O formulário administrativo agora define também o padrão para contas sem preferência (somente site ou site e e-mail); opt-out individual e bloqueio administrativo continuam respeitados. O update não foi executado no Runtime.

Validação desta revisão será registrada após executar a suíte Unit Messenger, PHPCS, lint, schema e índice de status. Sem Runtime, SMTP ou operação de produção.

## 0.1.1-dev.247 — centralizar e-mail privado e resumo semanal sem conteúdo

Preferências de Mensagens Privadas passam a ser próprias do Messenger, com migração incremental da configuração e das escolhas legadas do Private Message; escolhas Messenger existentes são preservadas e a chave individual antiga é removida após cópia. A pessoa escolhe aviso genérico direto, resumo semanal ou sem e-mail. O resumo reutiliza os registros Message existentes, mostra remetente e link para a inbox, nunca o conteúdo da conversa, revalida acesso e bloqueios, respeita data âncora administrativa e intervalo de sete dias por conta. O digest privado não depende do opt-in de resumo diário das demais origens. Sem execução do update no Runtime.

Validação: testes Unit focais do worker, configuração administrativa, elegibilidade semanal e migração de padrão **11 testes / 174 assertions PASS**; PHPCS Drupal/DrupalPractice dos arquivos alterados PASS. A suíte completa, validação de configuração e índice de status ainda serão executados. Sem Runtime/SMTP.

## 0.1.1-dev.257 — impedir leitura por requests de recursos incorporados

A guarda de navegação agora rejeita destinos Fetch Metadata de sub-recursos, como `iframe`, `image`, `script` e `object`, evitando que embeds same-site acionem a leitura de uma conversa. Mantém navegação de documento e Fetch com destino `empty`, usado pela gaveta.

Validação: `MemberInitiatedRequestTest` **4/16 PASS**, Unit Messenger **258/1.996 PASS**, PHPCS Drupal/DrupalPractice e PHP lint PASS. Kernel, Browser e Runtime não executados.

## 0.1.1-dev.258 — não armazenar redirects personalizados de avisos

Os endpoints de abertura dos avisos de LMS, Wiki, Loja e Doações agora declaram `Cache-Control: private, no-store` também no redirect autorizado após a revalidação do acesso e a atualização de leitura. Os testes verificam o cabeçalho nas quatro origens.

Validação: `NoticeOpenControllerSafetyTest` **5/33 PASS**, Unit Messenger **258/2.000 PASS**, PHPCS Drupal/DrupalPractice e PHP lint PASS. Kernel, Browser e Runtime não executados.

## 0.1.1-dev.259 — revalidar bloqueios antes de persistir respostas

Após adquirir e recarregar a thread, o envio revalida os bloqueios bilaterais imediatamente antes da submissão. Se o bloqueio tiver sido criado após a primeira validação, a mensagem é rejeitada e os locks de Flood e conversa são liberados. O fluxo de nova conversa também repete a checagem caso resolva uma thread concorrente existente.

Validação: `PrivateMessageFormValidatorTest` **15/77 PASS**, Unit Messenger **259/2.011 PASS** e PHPCS Drupal/DrupalPractice PASS para `PrivateMessageFormValidator` e o teste. `NewConversationForm` mantém baseline PHPCS de **16 erros/15 avisos**, igual ao HEAD anterior; PHP lint dos arquivos alterados PASS. Kernel, Browser e Runtime não executados.

## 0.1.1-dev.260 — exigir sinal explícito para atualizar leitura

Requests que não trazem Fetch Metadata não são mais presumidos como navegação humana. A leitura só pode ser atualizada por Fetch da gaveta (`X-Requested-With: XMLHttpRequest`) ou navegação de documento com ativação explícita (`Sec-Fetch-User: ?1`); destinos de sub-recurso, prefetch e cross-site permanecem bloqueados. Links podem continuar abrindo seu destino, mas sem atualizar leitura quando não há sinal confiável.

Validação: `MemberInitiatedRequestTest` e `NoticeOpenControllerSafetyTest` **10/53 PASS**, Unit Messenger **260/2.015 PASS**, PHPCS Drupal/DrupalPractice e PHP lint PASS. Kernel, Browser e Runtime não executados.

## 0.1.1-dev.261 — exigir same-origin no Fetch da gaveta

O sinal `X-Requested-With` só autoriza atualização de leitura quando o Fetch Metadata declara `same-origin` (ou o navegador não fornece esse header) e o destino é `empty`. Requests marcados como `same-site` entre origens distintas não são aceitos como ação da gaveta.

Validação: `MemberInitiatedRequestTest` **5/21 PASS**, Unit Messenger **260/2.016 PASS**, PHPCS Drupal/DrupalPractice e PHP lint PASS. Kernel, Browser e Runtime não executados.

## 0.1.1-dev.262 — buscar membros pela identidade pública

A busca de destinatários usa apelidos dos perfis, resolve o nome pela API `PublicMemberIdentityInterface` do ACULTA User e exclui identidades ausentes. Não consulta mais `users_field_data.name` nem mostra `getDisplayName()` em chips e títulos de nova conversa.

Validação: `NewConversationControllerTest` + `NewConversationRecipientIdsTest` **5/27 PASS**, Unit Messenger **260/2.022 PASS**; `AuthorIdentityResolverTest` **2/9 PASS**, Unit aculta_user **125/448 PASS** com uma depreciação preexistente de PHPUnit. PHPCS dos novos arquivos e lint PASS. Kernel, Browser e Runtime não executados.

## 0.1.1-dev.263 — bloquear navegação iniciada por origem irmã

- Rejeita navegações ativadas com `Sec-Fetch-Site: same-site` antes de alterar estado de leitura; origens irmãs não equivalem à origem exata do site. Navegação explícita da própria origem e abertura por bookmark continuam permitidas.
- `MemberInitiatedRequestTest` **6/22 PASS**, Unit Messenger **261/2.023 PASS**, PHPCS Drupal/DrupalPractice e PHP lint PASS. Kernel, Browser e Runtime não executados.

## 0.1.1-dev.264 — revalidar privacidade antes de criar conversa

- A criação consulta primeiro o mapper do Private Message. Threads existentes continuam aceitando respostas; antes de criar uma nova thread, a preferência friend-only e a amizade são revalidadas, impedindo persistir uma thread vazia se a autorização mudou desde a validação inicial.
- `NewConversationRecipientIdsTest` + `PrivateMessageFormValidatorTest` **21/108 PASS**; Unit Messenger **263/2.039 PASS**; PHP lint PASS. PHPCS mantém os baselines existentes dos arquivos envolvidos. Kernel, Browser e Runtime não executados.

