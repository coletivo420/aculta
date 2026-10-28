## 0.1.1-dev.404 — avatar padrão no remetente da mensagem (Fase 4)

- O avatar do remetente segue a regra única: foto, depois acervo ilustrativo, depois inicial.
  Antes, sem foto não havia nada na mensagem.
- Validação: PHP lint PASS. PHPUnit e Runtime não executados (sem `vendor/`). RUNTIME STATUS: DEFERRED.

## 0.1.1-dev.403 — versionar defaults estruturais de comunicação

Config Sync agora contém os defaults instaláveis de `email_modes` e
`magazine_events`. A data inicial do digest permanece operacional e fora do
Sync. A documentação define overrides por ambiente e exige conferir diferenças
ativas antes de qualquer importação. Nenhuma configuração ativa foi importada.

## 0.1.1-dev.402 — manter relativos os links da gaveta de avisos

Os botões “Ver todos os avisos” e “Ver todas as mensagens” agora geram caminhos
relativos por route ID. Eles deixam de apontar para o hostname canônico
`aculta.org`, preservando a política de URLs relativas do projeto.

## 0.1.1-dev.401 — alinhar metadados Sync de amizade com defaults e Runtime

- Config Sync atualiza a descrição de `field_aculta_messenger_attempts` e o rótulo/descrição de `field_aculta_messenger_source_fr` para os valores dos defaults instaláveis. A leitura read-only confirmou que esses metadados também correspondem ao Runtime MAIN.
- Nenhum valor de mensagem, preferência, modo de e-mail ou dado do Runtime foi alterado. A matriz operacional `email_modes` permanece bloqueada para decisão porque o Runtime difere dos defaults e o Sync ainda não contém essa árvore.

## 0.1.1-dev.400 — corrigir e versionar o contador de tentativas

`field_aculta_enqueue_attempts` agora usa `unsigned`/`size` na configuração de
storage Integer; os limites de instância não ficam mais no nível de storage.
Os objetos `field.storage` e `field.field` foram adicionados ao Sync após
comparação read-only que confirmou o mesmo schema no Runtime MAIN. O update
10009 já instala esses defaults em sites existentes e permanece pendente no
Runtime consultado.

`MessengerMailDisplayConfigTest` **3/80 PASS**; conjunto focal de amizade e
entrega **38/724 PASS**; PHP lint/PHPCS Drupal+DrupalPractice e gates YAML,
Config Extension, status, pacotes e diff check PASS. Sem alteração de dados.

## 0.1.1-dev.399 — ocultar controles Entity Print em avisos

Os defaults e o Config Sync ocultam os campos extras EPUB, PDF e DOCX do
Entity Print nos sete displays de e-mail de avisos e no display de avisos
públicos. A instalação de Entity Print no Runtime MAIN os mantinha ocultos, mas
os defaults/Sync não. A reconciliação evita que reimportar esses displays os
tornasse visíveis; o evento de amizade também continua oculto.

Teste de contrato de display: **2/68 PASS**; focais de amizade/entrega:
**37/712 PASS**. PHPCS Drupal/DrupalPractice e PHP lint do teste PASS; gates
Config Extension, YAML, índice, pacotes e diff check PASS. Nenhum Runtime ou
dado de Entity Print foi alterado.

## 0.1.1-dev.398 — ocultar o estado técnico de amizade no e-mail

Os displays de e-mail de amizade no Config Sync agora declaram o campo
`field_aculta_friendship_event` como dependência e o mantêm oculto. A proteção
coincide com o Runtime MAIN read-only e com `config/install`; antes, o Sync
podia renderizar `requested`/`accepted` no assunto ou corpo do aviso, apesar do
template genérico. Nenhum template ou mensagem Runtime foi alterado.

Testes Unit de amizade e entrega: 35/644 PASS. Gate de dependências Config
Extension, YAML dos dois displays, índice de status, pacote próprio e diff check
PASS. PHPCS não aplicável (nenhum PHP foi alterado).

## 0.1.1-dev.397 — reconciliar estados persistentes de entrega no Config Sync

O campo compartilhado `field_aculta_msg_delivery` agora exporta também os
estados terminais `failed_permanent` e `cancelled`, já usados pelos workers e
presentes no default do Messenger e no Runtime de teste. A comparação read-only
confirmou os cinco estados ativos; o Sync anterior listava somente os três
estados intermediários. Nenhum dado de fila ou configuração ativa foi alterado.

Validado por comparação do default instalado, do Sync e da storage ativa;
YAML, dependências Config Extension, índice de status e pacote próprio PASS.
O ensaio de importação não foi executado no Runtime.

## 0.1.1-dev.396 — remover texto bruto de exceções dos logs operacionais

A revisão M7 identificou logs que copiavam respostas de transporte e exceções
IMAP sem filtro. Esses textos podem trazer endereços, dados da caixa ou
respostas arbitrárias do provedor. O Mailer agora registra transporte e código
SMTP, enquanto o monitor IMAP persiste e registra somente a classe da exceção;
a tela administrativa também exibe apenas esse diagnóstico categórico. Novos
registros de falha e alertas guardam somente classe/código, sem texto bruto.
Registros históricos anteriores permanecem sob a retenção/acesso existentes;
a tela mascara estado IMAP legado, e nenhuma limpeza de logs foi executada.

`MailMonitorTest` **6/18**, `MailSettingsKernelTest` **2/5**, Unit Mailer **162/343** e Kernel Mailer **46/214 PASS** no MariaDB dedicado. PHPCS Drupal/DrupalPractice passou sem erros; há avisos de linhas >80 chars no baseline dos arquivos tocados. Gate Drupal 11+ (**1.155 checks**), índice de status e diff check PASS. PHP 8.4.27; SMTP real e Runtime não executados.

## 0.1.1-dev.395 — corrigir propriedade de locks durante digest e entrega

Um teste com worker suspenso após aceite do Mailer encontrou uma falha real:
`lock.persistent` usa o mesmo identificador entre requisições e permitia que o
worker antigo renovasse uma lease já assumida por outro processo. Serviços
temporários de digest, filas, conversas e idempotência do Mailer agora usam o
serviço Drupal `lock`, cujo proprietário é específico ao processo. A cobertura
Kernel substitui o backend nulo do `KernelTestBase` por `DatabaseLockBackend`.
O teste suspende o worker, expira a lease somente no banco SimpleTest, assume-a
com outro proprietário e retoma o processo: o worker antigo não confirma o
manifesto, e a reconciliação posterior confirma o aceite sem novo transporte.

Validação focal `AvisosResumoMailerKernelTest`: **6/133 PASS**. Kernels
combinados Messenger + Mailer: **101/1.421 PASS**, com dez depreciações de
Core/contrib e quatro notices PHPUnit preexistentes. Testes usam somente
`aculta_simpletest` e transporte Drupal de teste; SMTP e Runtime não foram
usados.

## 0.1.1-dev.394 — estabilizar contenção concorrente do digest

O marcador de conclusão usado pelo teste de oito workers agora é publicado
por rename atômico. A carga repetida expôs uma corrida em que o transporte
podia ler um arquivo antes do conteúdo `locked` estar completo e interpretar
isso como falha. Após a correção, dez rodadas independentes passaram (**10/10,
330 assertions**), totalizando 80 boots Drupal concorrentes e um único envio e
acknowledgement por rodada. A suíte Kernel completa passou **56/1.192** no
MariaDB dedicado (`aculta_simpletest`), com 10 depreciações e 4 notices
upstream. PHPCS Drupal/DrupalPractice e PHP lint PASS. Temporários ficaram em
diretório isolado sob `/var/tmp`; sem SMTP externo ou Runtime. A evidência
confirma contenção repetida, não throughput sustentado ou homologação Alpha.

## 0.1.1-dev.393 — alinhar estados M1/M2 às evidências

ROADMAP agora distingue histórico de estado atual: HTTP autenticado da
conversa/resposta/grupo, fluxo sem JavaScript e negação a não participante
estão comprovados no BrowserTest; o envelope/API, adapters, storage existente
e idempotência passaram nas suítes Unit/Integration/Kernel. Permanecem
pendentes os smokes visuais Firefox, Runtime/Alpha, updates operacionais e
transporte externo; nenhum deles é declarado concluído por testes isolados.
Índice de status regenerado e validado.

## 0.1.1-dev.392 — concorrência com oito workers independentes

O cenário de digest agora inicia **oito boots Drupal concorrentes** para o
mesmo destinatário e prefixo MariaDB; só um transporte e acknowledgement são
permitidos. `testIndependentDrupalWorkersDoNotSendConcurrentDigests` passou
**1/33 assertions**. A suíte Kernel completa passou **56/1.192** (10
depreciações Core/contrib e 4 notices PHPUnit), sem falhas. PHPCS
Drupal/DrupalPractice, PHP lint e `git diff --check` PASS. A evidência mede
contenção determinística, não carga sustentada do Runtime/Alpha; SMTP externo
não foi usado.

## 0.1.1-dev.391 — concorrência entre workers de digest

Reexecutado isoladamente `AvisosResumoMailerKernelTest::testIndependentDrupalWorkersDoNotSendConcurrentDigests` no MariaDB SimpleTest: **1 teste / 18 assertions PASS**. Três boots Drupal independentes competem pelo mesmo destinatário e prefixo; somente um envia/acknowledges, os demais observam o lock, com um único Mail Log/transporte. Atualiza a evidência de concorrência do M7; stress sustentado sob carga, Runtime e SMTP externo permanecem pendentes.

## 0.1.1-dev.390 — caracterizar update aditivo 10012

`MessengerDefaultConfigUpdateTest` agora cobre também `update_10012`, que
instala a configuração padrão do bundle compartilhado de avisos do Game por
`ConfigInstaller::installDefaultConfig()`. A classe passou **6 testes / 18
assertions** e a suíte Unit do Messenger **376 testes / 4.150 assertions**;
PHPCS Drupal/DrupalPractice e PHP lint PASS. O update não foi aplicado no
Runtime.

## 0.1.1-dev.389 — negar acesso HTTP de não participante

O BrowserTest autentica um terceiro usuário sem membership na thread de grupo
e faz GET real ao endpoint do drawer. A resposta é negada (403/404), não contém
a mensagem e a thread preserva a contagem de itens: **1 teste / 65 assertions
PASS**. O caso exercita também o isolamento do cookie jar entre contas; uma
tentativa anterior havia reaproveitado a sessão do remetente e não serviu como
evidência. PHPCS Drupal/DrupalPractice, PHP lint e `git diff --check` PASS.
BrowserTest reportou 275 depreciações Core/contrib sem falhas. Tudo em site e
banco temporários; sem alteração no Runtime.

## 0.1.1-dev.388 — revalidação das integrações e Kernels

Reexecutadas as suítes completas do Messenger no snapshot desta branch:
`tests/src/Integration` passou **120 testes / 1.569 assertions** (5 deprecações
upstream e 7 notices PHPUnit) e `tests/src/Kernel` passou **56 testes / 1.177
assertions** (10 deprecações upstream e 4 notices), no banco SimpleTest
dedicado. Sem falhas. O Dev.387 BrowserTest também comprova a composição e o
envio de grupo sem JavaScript (**1/58 PASS**); a versão Firefox visual/Runtime
do script permanece pendente. Nenhum dado ou configuração do Runtime foi
alterado. SMTP externo, Alpha e updates operacionais seguem pendentes.

## 0.1.1-dev.387 — composição de grupo sem JavaScript

O BrowserTest autenticado percorre a busca nativa por GET, preserva dois
destinatários na composição progressiva e envia a primeira mensagem de grupo
sem JavaScript. O teste confirma uma thread com o membro remetente e os dois
destinatários, além da mensagem persistida: **1 teste / 58 assertions PASS**.
PHPUnit reportou 275 depreciações de Core/contrib, sem falhas. Esta evidência é
do site temporário BrowserTestBase; Firefox visível, Runtime e Alpha seguem
pendentes.

## 0.1.1-dev.386 — resposta autenticada na conversa existente

O hook de renderização agora adapta também o formulário nativo já presente no
display `private_message_thread`, encaminhando-o ao endpoint POST protegido do
drawer e marcando-o para o comportamento JavaScript compartilhado. Antes, o
hook retornava sem adaptação quando o display padrão do contrib já fornecia o
form, deixando `action` e atributos incompatíveis com a gaveta. O hook também
fornece um render array vazio para o campo `updated` quando ausente, conforme
esperado pelo preprocess do template instalado, sem alterar o módulo contrib.

BrowserTest autenticado cria uma conversa, extrai os tokens do formulário de
resposta e da rota, envia um POST HTTP real e confirma duas mensagens
persistidas na thread: **1 teste / 35 assertions PASS**. Suíte Unit completa:
**375 testes / 4.147 assertions PASS**. PHP lint, PHPCS Drupal/DrupalPractice e
`git diff --check` PASS. PHPUnit reportou 275 depreciações de Core/contrib, sem
falhas. Runtime, browser visual, fluxo de grupo sem JavaScript e Alpha seguem
pendentes.

## 0.1.1-dev.385 — envio inicial de conversa exercitado por HTTP autenticado

O BrowserTest agora autentica um membro pelo helper de sessão do Drupal, abre
o composer, seleciona um destinatário elegível e envia a primeira mensagem por
POST real. O cenário revelou que o controle de acesso de `private_message`
nega a visualização de uma thread vazia antes de ela receber sua primeira
mensagem; a submissão tentava registrar um erro depois do fim da validação e
respondia com exceção. A validação no limite de persistência agora permite
somente esse estado inicial quando remetente ativo, permissão e membership são
revalidados; threads existentes continuam usando o acesso nativo.

Validação: BrowserTest **1 teste / 26 assertions PASS**; Unit focal
`PrivateMessageFormValidatorTest` **4/30 PASS**; suíte Unit do Messenger
**375 testes / 4.147 assertions PASS**; PHP lint, PHPCS Drupal/DrupalPractice e
`git diff --check` PASS. PHPUnit reportou 274 depreciações de Core/contrib no
BrowserTest, sem falha. O teste prova primeira mensagem e
persistência de thread em site temporário; resposta em thread, Runtime, Firefox
visual, SMTP e Alpha seguem pendentes. A raiz do host de teste, verificada em
2026-10-25 após a correção do relato, responde HTTP 200; a rota Drupal `/`
aponta para `entity.node.canonical` e a home configurada é `/node/1`, portanto
não foi reproduzido `No route found` neste momento.

## 0.1.1-dev.384 — corrigir o escopo da evidência BrowserTest

A revisão do resultado Dev.383 encontrou que o HTTP de `/user/login` retornava
status 200 com corpo vazio no harness utilizado; isso não comprova uma rota
Drupal servida e não pode ser contado como homologação HTTP. O teste foi
reduzido ao que demonstrou: `aculta_messenger` e suas dependências instalam no
site BrowserTestBase isolado. A rota, sessão autenticada e envio permanecem
pendentes até corrigir o servidor/browser de teste.

Validação corrigida: **1 teste / 4 assertions PASS** para instalação e módulo
habilitado; 189 depreciações Core/contrib, sem falhas. PHPCS Drupal/DrupalPractice,
PHP lint e `git diff --check` PASS. O servidor estático foi usado somente como
URL-base de disponibilidade e não recebeu tráfego funcional da aplicação.

## 0.1.1-dev.383 — instalação funcional isolada do Messenger

`MessengerModuleInstallTest` instala `aculta_messenger` com suas dependências
em BrowserTestBase e faz uma requisição HTTP real a `/user/login`. O teste usa
Core/Composer e módulos do snapshot isolado da PR, servidor PHP local e banco
MariaDB SimpleTest dedicado; nenhum dado do Runtime foi usado.

Validação: **1 teste / 5 assertions PASS**, HTTP 200; PHPCS Drupal/DrupalPractice,
PHP lint e `git diff --check` PASS. PHPUnit reportou 189 depreciações de
Core/contrib durante a instalação, sem falha. Isto comprova instalação e uma
rota HTTP no site temporário; não homologa Runtime, sessão autenticada, envio de
mensagem, visual nem e-mail externo.
## 0.1.1-dev.383 — instalação funcional isolada do Messenger (evidência corrigida em Dev.384)

`MessengerModuleInstallTest` instalou `aculta_messenger` e suas dependências em
BrowserTestBase. A alegação inicial de que `/user/login` respondeu por HTTP foi
retirada em Dev.384: o corpo retornado era vazio, portanto o teste não provou
que a rota Drupal foi servida. A evidência válida limita-se à instalação do
módulo; sessão autenticada e entrega seguem sem homologação.

## 0.1.1-dev.382 — Kernels dos consumers de domínio

Os Kernels de integração LMS, Wiki, Commerce/Loja, Doações, Revista e Game
copiados do snapshot publicado passaram **37 testes / 697 assertions** no banco
dedicado `aculta_simpletest`, com autoload dos módulos apontado ao snapshot. O
Drupal Core e a descoberta de manifests vieram do checkout local. Foram
reportadas cinco depreciações upstream (plugins Message/QueueWorker/
MessagePurge) e sete notices PHPUnit; nenhuma falha.

Os testes não processaram filas ou dados do Runtime e não chamaram SMTP
externo. HTTP autenticado, visual, Runtime pós-update e Alpha permanecem fora
deste gate.

## 0.1.1-dev.381 — revalidação Unit dos consumers integrados

Os diretórios Unit de integração dos adapters centrais (LMS, Wiki,
Commerce/Loja, Doações, Revista e Game) passaram **83 testes / 872 assertions**
no snapshot publicado, com autoload direcionado aos módulos desse snapshot.
Sem conexão a banco, Runtime ou transporte externo.

Kernel específico dos produtores, entrega HTTP, BrowserTest/visual, Runtime,
SMTP e Alpha continuam pendentes.

## 0.1.1-dev.380 — revalidação Kernel do Messenger

A árvore Kernel completa passou **56 testes / 1.177 assertions** no banco
dedicado `aculta_simpletest`. A execução foi feita no checkout de trabalho;
os testes Kernel e os serviços exercitados não diferem do snapshot publicado
nessa frente. PHPUnit reportou dez depreciações upstream de Core/contrib e
quatro notices; sem falhas. Temporários e saída ficaram fora de `/tmp`.

Este gate não valida HTTP com sessão, BrowserTest, Runtime pós-update, SMTP
externo ou Alpha.

## 0.1.1-dev.379 — regressão completa dos testes Unit publicados

A suíte Unit do Messenger no snapshot da PR passou **374 testes / 4.140
assertions**. O carregamento foi direcionado ao código do worktree isolado da
PR, evitando que o Composer do checkout principal usasse cópias de classes de
outro snapshot. Inclui a regressão Dev.378 e os testes de composição
progressiva/recuperação de destinatários.

Esta execução é Unit local: não valida Kernel/Integration, HTTP com sessão,
Runtime pós-update, SMTP ou Alpha. PHPUnit informou que o diretório de saída
de BrowserTest não é gravável; isso não impediu nem alterou esta suíte Unit.

## 0.1.1-dev.378 — regressão da configuração modular do Aviso Institucional

Adicionado teste Unit que protege a dependência explícita de `menu_ui` e mantém
opcional o form display condicionado por Domain e workflow editorial global.
O teste verifica também que essa configuração não reaparece em
`config/install`, onde impediria instalações independentes do Messenger.

Validação: PHPUnit **1 teste / 5 assertions PASS**, PHPCS Drupal/DrupalPractice,
PHP lint e diff check PASS. BrowserTestBase permanece inconclusivo antes das
asserções HTTP devido à política de ACL do PHP Storage restrito no site
temporário; não houve operação no Runtime nem envio externo de e-mail.

## 0.1.1-dev.377 — instalação modular independente de configuração editorial global

`menu_ui` agora é dependência explícita, conforme o bundle `aculta_notice`. A
configuração do formulário desse bundle que depende de `field_domain_source` e
do workflow global `editorial` passou para `config/optional`; os demais campos
do Aviso Institucional e o display público continuam obrigatórios no módulo.
Assim, habilitar o Messenger não depende de importar previamente configuração
global de Domain/workflow. O Configuration Sync continua fornecendo o display
completo no site integrado.

Validação: YAML parse e diff check passaram. A instalação do módulo em
BrowserTestBase avançou até a primeira requisição sem erro de dependência de
configuração; o teste funcional não completou os requests porque o PHP Storage
restrito do Homelab exige ACL/setgid indisponível no site temporário. Nenhuma
operação foi feita no Runtime ou em SMTP.

## 0.1.1-dev.376 — revalidação dos Kernels de digest e idempotência

No MariaDB dedicado `aculta_simpletest`, `AvisosResumoMailerKernelTest` passou
**5 testes / 95 assertions** e `ForumNoticeIdempotencyKernelTest` passou
**39/919**, com quatro notices PHPUnit. Isso revalida contenção entre boots,
recuperação do aceite e expiração de lease enquanto o transporte continua em
andamento. SMTP externo e Runtime não foram usados. O smoke visual da central
não passou da etapa de autenticação: não encontrou o acionador do Messenger;
o Firefox também registrou falha de escrita do perfil temporário com `/tmp`
entre 98% e 100% de uso. Não houve interação com avisos.

