# Changelog

Mudanças relevantes do ACULTA Messenger.

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
