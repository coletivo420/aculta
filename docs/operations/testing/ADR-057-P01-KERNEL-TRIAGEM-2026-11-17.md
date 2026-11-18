# ADR-057 — triagem do Kernel CI (P01/F1) — 2026-11-17

## Execução observada

- PR #335, commit testado `8ba1b03d9083f5f900b58eca99cc7647ad7746a2`.
- GitHub Actions run `44080680393` (`PHP Quality Assurance`).
- Comparação de baseline: PR base `5c496047509f43831de2a6996cbc6ba95d867749`, workflow run `44065410069` no próprio `dev`.
- Cinco dos seis shards Kernel concluíram PHPUnit; um shard terminou cancelado sem resumo de testes. A conclusão do job desse shard não é PASS.
- Os cinco relatórios concluídos somam 949 testes, 15.848 assertions, 101 erros e 7 falhas. Os números excluem o shard cancelado.
- `phpstan`, `phpunit-unit`, `php-lint` e `test-discovery` concluíram sucesso; `PHPStan level 10 gate` concluiu sucesso. O debt-diff foi skipped por regra do workflow, não é um gate executado.

## Situação da PR e proveniência

Consulta GitHub em 2026-11-17 confirma que a PR #335 está **fechada e mesclada** no commit `444a6599e70c1b914004245d0607bee4f0469e70`. O `origin/dev` atual (`4dadf3b72f9ff4c0c170497f4b95a21f90195315`) tem esse merge como pai. As correções e evidências locais posteriores não podem ser acrescentadas à PR #335; permanecem na branch de trabalho local até publicação por um fluxo de revisão subsequente. A branch `dev` exige o check `PHPStan level 10 gate`.

## Causas agrupadas pelos logs

| Shard | Resumo PHPUnit | Causa/owner indicado pelo teste | Escopo para P01 |
| --- | --- | --- | --- |
| 1/6 | 172 testes; 8 erros | 2 erros: template SDC `aculta420_v2:account-terms-modal` não descoberto ao renderizar; 6 erros: plugin Views `ui_patterns` ausente ao instalar config de `aculta_lms` em testes do Fórum. | Descoberta de SDC no harness do tema; disponibilidade/registro do plugin no harness de UI Patterns/Views. |
| 2/6 | Cancelado; sem resumo/artifact | Nenhuma conclusão PHPUnit disponível. | Investigar cancelamento separadamente; não converter em PASS nem em erro de teste. |
| 3/6 | 176 testes; 54 erros; 3 falhas | 15 erros por serviço `aculta_user.avatar_resolver` ausente durante boot de `ForumThemeHooks`; 13 por plugin Views `ui_patterns` ausente; 9 por schema incompleto em `views.view.aculta_forum_public_topics` (`author_card.variant`, `author_card.size`, `pinned.source`); 9 por dependência `ui_patterns_views` não atendida em config `aculta_lms`; 8 ao instalar config de LMS sem plugin. Três falhas adicionais: agenda LMS recebe `null` em vez do título; filtros/paginação e resiliência de entidades salvas no Studio recebem valores vazios. | Separar boot/config de Fórum, UI Patterns/Views e LMS; revisar contratos dos providers de Agenda/Studio com seus owners. |
| 4/6 | 184 testes; 3 falhas | Falhas de integração em `ActivityContentIndexProviderKernelTest`, `aculta_H5PCourseAccessIntegrationTest` e `MapSettingsFormKernelTest`. | Frentes Activity, LMS H5P e Maps; não atribuir ao tema sem evidência de dependência. |
| 5/6 | 240 testes; 24 erros | 15 testes de Commerce falham porque `private_message` não está disponível; 6 testes de `aculta_index` aguardam a fila de geração drenar; 3 testes do bloco de conteúdo relacionado recebem `null` em `#items`. | Dependência/harness de Commerce e isolamento/assíncrono do indexador. |
| 6/6 | 177 testes; 15 erros; 1 falha | 14 erros por módulo `private_message` indisponível; 1 schema incompleto da View `aculta_forum_public_topics`; 1 falha de `InteractiveControllerKernelTest` porque o array não contém `componente`. | Dependência de Commerce, schema UI Patterns/Views e contrato de apresentação do calendário. |

## Interpretação

Os seis arquivos alterados pela PR #335 eram de auditoria, browser e
documentação; não incluíam os módulos/testes citados nos erros acima. Também
comparei os logs dos mesmos shards nos runs do commit base e da PR: os blocos
completos de erros/falhas e os resumos dos shards 1, 3, 4, 5 e 6 são idênticos.
No shard 3, a única diferença textual é a ordem da lista de plugins válidos
impressa pelo Plugin Manager; após ordenar essa enumeração, todo o bloco
coincide. O shard 2 foi cancelado nos dois runs. Portanto, as falhas Kernel
observadas já existiam no commit base e não foram introduzidas pela PR #335.
P01 continua **FAIL** porque o baseline do CI ainda falha; a comparação de
origem não substitui a resolução das falhas nem um gate Kernel estável.

O bloqueio da suíte não pertence apenas à interface: há falhas de descoberta
de SDC, integração UI Patterns/Views e schemas relevantes à ADR-057, além de
falhas independentes de Fórum, LMS, Commerce, Index, Activity, H5P, Maps e
Calendário. Nenhuma configuração, dependência ou workflow foi alterado nesta
triagem.

## Correção focal posterior — configuração de tópico LMS

No checkout local, o teste `LmsTopicBundleInstallKernelTest` reproduziu os
quatro erros por plugin Views `ui_patterns` ausente. A lista de módulos do
harness foi completada com `ui_patterns`, `ui_patterns_views`, `editor` e
`ckeditor5`, dependências reais da configuração instalada pelo módulo LMS. O
helper compartilhado agora valida YAML como mapa e restringe o tipo do Group
Type antes de criar relações. Com essa correção, o Kernel focal passou: **4
testes, 130 assertions**, com 12 depreciações de Core/contrib.

A análise canônica PHPStan nível 10 passou (exit 0); `phpstan-debt-guard check`
e `diff` passaram. Foram removidas 18 entradas de baseline que correspondiam
às mensagens corrigidas no helper/teste, sem novos ignores. Esta é uma correção
local e parcial: não reexecuta os outros casos de LMS/Fórum e não fecha P01.
O baseline Kernel do CI ainda contém falhas independentes e shard cancelado.

## Correção focal posterior — isolamento do contexto de discussão

O teste `LmsDiscussionContextIsolationKernelTest` também reproduzia seis
erros pela ausência de `ui_patterns`/`ui_patterns_views` durante a instalação
da configuração de `aculta_lms`. O harness agora declara esses módulos e
valida YAML e tipos retornados pelo Entity API antes de usá-los. O teste focal
passou: **6 testes, 300 assertions**, com 17 depreciações de Core/contrib. A
correção removeu 10 ocorrências congeladas no baseline deste teste; a análise
canônica PHPStan nível 10 e os guards `check`/`diff` passaram. P01 permanece
**FAIL** até a suíte completa do CI concluir sem falhas ou shard cancelado.

## Correção focal posterior — fechamento de discussão LMS

`LmsClosedDiscussionFieldKernelTest` reproduzia dois erros pelo plugin Views
`ui_patterns` ausente. O harness passou a declarar `ui_patterns`,
`ui_patterns_views`, `editor` e `ckeditor5`. As asserções de persistência e
cache de acesso foram tipadas explicitamente, sem reduzir a cobertura negativa
de acesso. O teste passou: **2 testes, 103 assertions**, com 11 depreciações
de Core/contrib. Foram removidas nove entradas PHPStan congeladas desse teste.
Isto corrige apenas este alvo local; não fecha os demais erros Kernel nem P01.

## Correção focal posterior — matriz de moderação e acesso contextual

Depois de habilitar as dependências reais do harness,
`LmsCommentModerationMatrixKernelTest` revelou uma falha anterior escondida
pelos erros de instalação: Educadores do curso eram negados ao moderar
comentários na turma. A causa estava no `getGroupModerationAccess()`: combinar
a permissão de associação `allowed` com uma dependência de cache `neutral` via
`andIf()` produz resultado `neutral` no Drupal. A dependência agora é
`allowed` e continua anexando o cache do Group. A matriz passou com **1 teste,
72 assertions**, preservando as negações para estudante e outsider e as
verificações de estado persistido. O harness também deixou de recriar campos
que `prepareAcultaLmsPartialConfigDependencies()` já instala. Foram removidas
cinco entradas congeladas PHPStan do teste. A revisão R2 independente passou,
sem findings. P01 segue **FAIL** até a suíte Kernel completa ficar estável.

## Correção focal posterior — importação das permissões LMS

`LmsRolePermissionsConfigSyncKernelTest` instala o mesmo conjunto de
configuração LMS e não declarava os plugins Views e editor necessários. O
harness passou a carregar `ui_patterns`, `ui_patterns_views`, `editor` e
`ckeditor5`. Também usa a interface `ImportableEntityStorageInterface` para a
importação real de roles, valida os mapas YAML e estreita os tipos de role
antes das asserções. O Kernel passou: **5 testes, 309 assertions**, com 11
depreciações de Core/contrib. Foram removidas nove entradas PHPStan
congeladas; a revisão independente R2 passou sem findings. A correção reduz
mais erros focais, sem atestar os demais shards ou concluir P01.

## Schema da View pública do Fórum

A triagem encontrou `SchemaIncompleteException` na instalação de configuração
LMS por opções já usadas pela View pública do Fórum: `variant`/`size` do campo
`aculta_user_author_card` e a source `view_field_value` da prop `pinned`. O
schema do campo agora pertence a `aculta_user`; o schema da source deriva do
contrato `ui_patterns_source.view_field` e pertence a `aculta_forum`.

Reexecutei `AcultaLmsInstallTest::testInstallRegistersOnlyTheOptionalIntegrationService`
com `SIMPLETEST_DB` verificado como `aculta_simpletest`: **1 teste, 71
assertions, OK**. A instalação passou sem `SchemaIncompleteException`. O
PHPUnit registrou dois warnings preexistentes porque o hook do módulo
`private_message` tenta incluir `private_message.theme.inc`, ausente no
checkout. Este teste focal não reexecuta os shards e não quantifica a redução
total do shard; P01 permanece **FAIL**.

### Regressão cruzada em consumidores da configuração LMS/Fórum

Reexecutei no `aculta_simpletest` os seis arquivos Kernel identificados por
importar ou exercitar a configuração LMS/Fórum: `AcultaLmsInstallTest`,
`LmsTopicBundleInstallKernelTest`, `LmsDiscussionContextIsolationKernelTest`,
`LmsClosedDiscussionFieldKernelTest`, `LmsCommentModerationMatrixKernelTest` e
`LmsRolePermissionsConfigSyncKernelTest`. O resumo foi **27 testes, 1.235
assertions**, sem `SchemaIncompleteException` ou falha de assertion. O comando
terminou com exit 1: `AcultaLmsInstallTest` emite dois warnings porque
`aculta_messenger_theme()` ainda registra `private_message.theme.inc`, arquivo
ausente no módulo portado; também houve uma depreciação. Portanto, o warning é
uma pendência real do código do porte, não apenas dependência ausente no
harness. A correção exige restaurar o preprocess compatível com o bloco ACULTA
e testá-lo; não foi alterado nesta triagem.
Portanto, a regressão de schema não foi reproduzida nesta bateria, mas a
execução PHPUnit não é PASS por causa dos warnings e não substitui os shards
completos. P01 continua **FAIL**.

## Descoberta de SDC do tema nos testes de mídia privada

Os casos `AcultaDocumentMediaEmbedTest::testPrivatePdfUsesCoreRouteAndRespectsMediaAccess`
e `CourseDocumentAccessTest::testPrivatePdfRequiresCurrentGroupMembership`
precisavam renderizar o SDC `account-terms-modal` na resposta de acesso negado.
O harness agora instala e ativa `aculta420_v2` como tema ativo da requisição,
preservando o valor de `system.theme.default`. A closure inclui UI Styles,
UI Skins e UI Patterns Library, dependências efetivamente referenciadas durante
a compilação do tema.

Com `SIMPLETEST_DB` verificado como `aculta_simpletest`, as classes completas
passaram: `AcultaDocumentMediaEmbedTest` (**4 testes, 305 assertions**) e
`CourseDocumentAccessTest` (**1 teste, 47 assertions**). Isto confirma os
consumidores e a descoberta do SDC nesses contextos; não quantifica outros
consumidores não cobertos por essas classes. P01 segue **FAIL** até a suíte
completa do CI concluir sem falhas ou shard cancelado.

## Serviço de avatar no teste de integração Fórum/CGU

O teste `ForumCguIntegrationKernelTest` mantém `aculta_user` fora da lista de
módulos instalados, mas habilitar `aculta_forum` registra `ForumThemeHooks`,
cujo construtor exige `aculta_user.avatar_resolver`. O harness agora fornece
uma instância tipada sem construtor, tal como já fazia para
`AuthorCardBuilder`; isso satisfaz apenas o wiring da extensão do tema e não
ativa comportamento de avatar neste teste. A classe completa passou com
**15 testes, 287 assertions, exit 0**. Permanecem 12 depreciações do Core/
contrib (sem falha). Essa correção elimina a causa correspondente aos 15 erros
do shard 3 no checkout atual; a suíte CI completa ainda precisa ser executada
para confirmar a redução no shard. PHP lint, análise PHPStan nível 10 do arquivo
e `git diff --check` passaram. Revisão independente R2: sem finding funcional
ou de segurança; o revisor confirmou que o teste não chama `build()` no dummy.

## Contratos SDC em Agenda e Studio; confirmação no CI atual

Consultei o run `44092787085` do `PHP Quality Assurance` para o HEAD público de
`origin/dev` (`4dadf3b72f9ff4c0c170497f4b95a21f90195315`). O workflow encerrou
com sucesso, mas os shards Kernel 1, 3, 4, 5 e 6 falharam; o shard 2 foi
cancelado. Isso confirma que o status agregado não representa uma suíte Kernel
verde. No shard 3, as três falhas de assertion registradas eram incompatíveis
com o contrato atual de SDC: o teste de Agenda lia `#titulo`, enquanto
`EventPageController` entrega o título em `#slots.title.heading`; dois testes
de Studio liam `content` na raiz, enquanto `SavedContentController` entrega o
conteúdo no slot `#slots.content` do componente.

Atualizei apenas as assertions dos testes, mantendo as negativas de matrícula,
acesso anônimo, isolamento e exclusão de conteúdo de moderação. A classe de
Agenda passou (**5 testes, 196 assertions**) e a classe Saved/Recommendations
passou (**8 testes, 166 assertions**); juntas após as guardas de tipo: **13
testes, 356 assertions, exit 0**, com 44 depreciações Core/contrib. PHP lint,
PHPStan nível 10 nos dois arquivos e `git diff --check` passaram. Revisão
independente R2: sem findings; confirmou que os casos negativos de acesso
continuam presentes. As correções locais ainda precisam ser reexecutadas no CI
para confirmar a redução real do shard 3.

O shard 5 do mesmo run tinha três assertions antigas em
`RelatedContentBlockKernelTest`, que buscavam `#items` na raiz, e seis falhas ao
ativar gerações do índice com trabalhos de outros providers ainda pendentes.
Os testes do bloco agora leem os itens de `#slots.content` e mantêm a cobertura
que exclui locais privados. Os helpers dos testes de Media/Gallery agora drenam
todos os IDs do registry antes de `validate()` e `activate()`; o gate de
produção continua exigindo fila vazia para todos os providers. As duas classes
passaram juntas: **7 testes, 151 assertions**, com 9 depreciações Core/contrib.
A revisão independente R2 não encontrou findings e confirmou que a ordem e o
gate de ativação não foram enfraquecidos. A classe do bloco passou com **7
testes, 155 assertions**, 11 depreciações Core/contrib; manteve a ocultação de
alvos privados e o caso fail-closed quando todos estão privados.

Após essas correções, a análise canônica PHPStan nível 10 passou; os guards
`check` e `diff --analysis` passaram e o baseline registrou redução legítima
(sem novos ignores). PHP lint e `git diff --check` também passaram. As correções
são locais: ainda não foram publicadas nem reexecutadas nos shards CI.

## SDC do calendário interativo

O shard 6 também falhava em `InteractiveControllerKernelTest` porque o teste
esperava o antigo filho `componente` na raiz. A revisão do contrato revelou
que o controller já retorna o SDC `aculta420_v2:calendar-interactive`, mas
faltava declarar `#type: component`; corrigi o render array e atualizei o teste
para ler os elementos dentro de `#slots.content`. Os attachments e a metadata
de cache continuam no slot e agora são verificados explicitamente, incluindo
biblioteca, `drupalSettings`, contexto `route`, tag de invalidação e max-age.
O teste passou (**1 teste, 12 assertions**); PHPStan nível 10, guards do
baseline, gate Drupal 12 do Portal (**1.236 checks**), PHP lint e
`git diff --check` passaram. Revisão R1 independente: sem findings; confirmou
que renderizar o slot propaga attachments e metadata. O fix ainda requer
confirmação no shard CI atual.

## Preprocess do bloco de notificação do Messenger

A triagem local encontrou `hook_theme()` apontando para o arquivo inexistente
`private_message.theme.inc`; o Twig consumia três variáveis ausentes. Comparei
com o tarball upstream alpha5 cujo SHA-256 foi validado pelo runbook. O hook
agora é OOP (`#[Hook]`) em `PrivateMessagePresentationHooks`, injeta
`module_handler`, tradução e `RequestStack`, e devolve o link como render array.
O caminho de imagem usa o módulo ativo `aculta_messenger` e respeita subdiretório;
o count é não-negativo e limita o rótulo visual a `99+`. O Twig marca a imagem
decorativa com `alt=""`; nenhum fluxo de inbox, ACL ou cache foi alterado.

Verificações locais: teste Unit focal **1/15 assertions**; Unit completo
**3.326 testes/19.173 assertions**, sem falhas (3 deprecações PHPUnit); Kernel
completo do Messenger **63 testes/1.646 assertions**, sem falhas (115
deprecações, 10 deprecações PHPUnit e 4 notices); lote Kernel focal dos sete arquivos: 33 testes/1.001 assertions, 147 depreciações,
sem falhas; PHPCS dos arquivos PHP,
gate Portal (**1.236 checks**), PHPStan canônico nível 10 e guard `check`
passaram. Para honrar o guard de dívida tocada, corrigi tipos reais em sete
arquivos que já estavam no diff: Calendar ficou sem erros baseline; CGU caiu de
22 para 21; Document Viewer de 22 para 21 e de 6 para 4; Index Gallery de 26
para 25; Index Media de 42 para 41; LMS Agenda de 21 para 19. A análise
canônica não tem erros e os guards `check` e `diff --include-worktree` passaram,
com 104 entradas removidas e quatro contagens reduzidas no baseline total.
P01 global continua FAIL até reexecutar os shards completos.

## Revalidação do merge da PR #335 e harnesses locais (2026-11-18)

O run `44089290925` de `PHP Quality Assurance`, associado ao merge commit `444a6599e70c1b914004245d0607bee4f0469e70`, teve `phpstan`, `phpunit-unit`, `php-lint` e `test-discovery` com sucesso. O shard Kernel 1 falhou com 172 testes, 3.588 assertions e 8 erros: dois `LoaderError` para `aculta420_v2:account-terms-modal` nos Kernels `AcultaDocumentMediaEmbedTest`/`CourseDocumentAccessTest`; seis `PluginNotFoundException` porque o plugin Views `ui_patterns` não estava disponível durante a instalação de config LMS no `LmsDiscussionContextIsolationKernelTest`. O shard 2 iniciou 218 testes e foi interrompido após 126 testes pelo sinal de shutdown/cancelamento do runner; shards 3–6 foram cancelados. Portanto, o run não fornece uma suíte Kernel aprovada.

No checkout local atual, os harnesses dessas duas frentes foram ajustados em arquivos que já estavam modificados na worktree: os dois testes de Document Viewer ativam a descoberta do tema SDC sem mudar `system.theme`; o teste de Fórum declara `ui_patterns` e `ui_patterns_views`. Reexecução focal em `aculta_simpletest` passou: Document Viewer **5 testes/353 assertions** e Fórum **6 testes/300 assertions**. Isso confirma localmente a correção das oito falhas do shard 1, sem provar os demais shards. Os arquivos de implementação/teste pertencem ao trabalho local paralelo e não foram incluídos neste registro.

**Status P01: FAIL.** O único run remoto mais recente continua com shards falhos/cancelados. As correções locais não foram submetidas a uma execução CI completa porque a PR #335 já foi merged; requerem o fluxo subsequente de revisão/publicação e uma nova execução completa dos shards.

## Correção focal local — asserção Maps (2026-11-18)

O shard 4 do run `44092787085` falhou porque `MapSettingsFormKernelTest` aceitava apenas valores escalares em `credential_status.#markup`. O formulário retorna `TranslatableMarkup`, que implementa `Stringable`; a guarda antiga convertia o status válido `Ausente` em string vazia. A asserção agora converte explicitamente valores `Stringable`, sem alterar o formulário ou o armazenamento de credenciais.

Reexecução Kernel focal: **3 testes, 6 asserções, PASS**. PHP lint, gate Drupal 12 do Portal (**1.236 checks**), PHPStan nível 10 global (JSON canônico, exit 0), `phpstan-debt-guard check` e `diff --changed-files --analysis` passaram. O módulo `aculta_maps` não tinha entrada no baseline a reduzir. A falha fica corrigida no checkout local; o CI remoto segue **FAIL** até executar todos os shards no fluxo de revisão subsequente.

## Próximos passos

### Execução Kernel local e correção da fila editorial Wiki

Uma execução local de `vendor/bin/phpunit --testsuite kernel` terminou com
exit 2: **1.167 testes, 22.649 assertions, 88 erros, 8 falhas, 7 warnings, 4
depreciações, 10 PHPUnit deprecations e 5 notices**. Os erros incluem a
indisponibilidade repetida do módulo legado `private_message`; as falhas
incluem Eventos, H5P, Maps e três casos de Wiki. O processo foi iniciado em
uma worktree compartilhada antes de alterações locais posteriores e não
preservou o SHA de origem; este resultado é diagnóstico, não comparação
canônica com o CI nem prova de regressão no HEAD atual. P01 permanece **FAIL**.

A execução local falhou em
`WikiEditorialQueue::testEditorialQueuePaginatesAuthorizedRevisionsPastDeniedCandidates`.
`QueryAggregate::execute()` devolve linhas associativas com
`content_entity_revision_id`; a fila convertia a linha inteira
em string, descartava os candidatos e produzia `Array to string conversion`.
Corrigi a extração do VID e as guardas de tipo/cursor, removendo quatro
entradas correspondentes de dívida PHPStan no mesmo diff. Reexecução Kernel
focal: **1 teste, 119 assertions, PASS**, com uma depreciação. PHP lint,
PHPCS, PHPStan nível 10 canônico e guards `check`/`diff --include-worktree`
passaram. A alteração é **R2** porque mantém a fila de moderação sob
revalidação de acesso; revisão independente ainda é necessária antes da
publicação.

### Integração de reprodução H5P e documentos

A falha `testPlaybackRequiresCurrentCourseMembershipAndFieldReference` foi
reproduzida isoladamente no Kernel local. A causa estava no próprio teste:
ele criava o formatter antes de substituir o serviço
`aculta_document_viewer.pdf_builder`; por isso a instância mantinha o mock
padrão vazio injetado anteriormente, embora a expectativa fosse configurada
em outro mock. Reordenei a configuração para instalar o mock antes da criação
do formatter. O código de produção e o contrato de acesso não mudaram.

Reexecução da classe focal: **3 testes, 156 asserções, PASS**, com 7
deprecações upstream/Core. PHP lint, descoberta PHPUnit (252 Kernel/693 Unit)
e gate Drupal 12 do Portal (1.236 checks) passaram. PHPStan nível 10 canônico
retornou exit 0; guards `check` e `diff --changed-files --analysis` passaram.
A primeira tentativa da suíte Unit usou por engano a configuração Core, que
varre um teste contrib opcional de `fraction` sem instalar Feeds; essa
execução não corresponde ao gate do projeto. Reexecutei o gate oficial com
`vendor/bin/phpunit --testsuite unit`: **3.329 testes, 19.203 asserções, PASS**,
com 4 deprecações PHPUnit. Para satisfazer o gate de dívida por módulo no
incremento PHP, também renomeei o parâmetro legado não usado para `$_phase` e
removi somente sua ocorrência do `phpstan-baseline.neon`: dívida de
`aculta_lms_h5p` reduzida em uma entrada. PHPStan canônico e guards `check` e
`diff` foram reexecutados após essa remoção e passaram. A falha local H5P foi
corrigida, mas CI remoto e P01 permanecem **FAIL** até todos os shards serem
executados com sucesso.

### Hook de formulário Activity

A falha `ActivityContentIndexProviderKernelTest::testActivityFormHooksAreDispatchedByPortal`
vinha de uma asserção que aceitava apenas valores escalares para o título do
campo `author`. O hook corretamente retorna `TranslatableMarkup`, que
implementa `Stringable`; o teste agora valida a interface e compara a
representação textual traduzida. Nenhum código de produção foi alterado.

A classe Kernel focal passou: **6 testes, 142 asserções**, com 72
deprecações Core/contrib. PHP lint, descoberta PHPUnit, gate Drupal 12 do
Portal (1.236 checks), PHPStan nível 10 global e guards `check`/`diff` passaram.
P01 permanece **FAIL** até a execução integral aprovada dos shards remotos.

1. Reexecutar todos os shards para quantificar os fixes locais de schema da View, descoberta SDC, avatar do Fórum, Calendar e o preprocess do bloco Messenger; reavaliar outros consumidores do SDC no shard 1/6.
2. Corrigir as causas em lotes isolados e manter failures/skipped/cancelled distintos no relatório e na proteção de branch.
3. Reexecutar todos os shards no HEAD atual depois das correções e exigir sucesso; o cancelamento do shard 2 nos dois runs não prova estabilidade.
4. Após estabilizar, decidir se o gate Kernel pode ser obrigatório por shard sem continuar mascarando falha legítima.

## Revalidação focal local adicional — 2026-11-18

No checkout `e66f2c5a501ad525952225971df18e9bfce1f09b`, com worktree suja,
reexecutei no banco de teste `aculta_simpletest` as classes que ainda não tinham
resumo registrado: `ContentIndexGalleryProviderKernelTest` (**2 testes/50
assertions**), `ContentIndexMediaProviderKernelTest` (**5/102**),
`AgendaEntriesProviderKernelTest` (**5/198**) e
`SavedAndRecommendationsKernelTest` (**8/160**), todas com exit 0. A classe
Unit `PrivateMessagePresentationHooksTest` também passou (**1 teste/15
assertions**). Essas são evidências focais do estado local; não provam os
shards completos nem mudam P01 de **FAIL**.

Iniciei `vendor/bin/phpunit --testsuite kernel` no mesmo banco de teste para
uma revalidação completa. A interação foi interrompida e o processo de captura
perdeu o handle original; o processo PHPUnit continua ativo em observação pelo
PID 356279, com subprocessos PHP e I/O crescente, mas sem saída final
recuperável. Portanto essa execução é **INCOMPLETA**, não PASS nem FAIL, e não
será usada como resultado de gate. Uma nova execução só deve ocorrer após o
processo atual terminar e em sessão que preserve o resumo completo.

**Fonte:** logs de workflow dos jobs `134030642221`, `134030642171`,
`134030642368`, `134030642245` e `134030642464`; shard `134030642255`
cancelado. Esta triagem não altera o status das ADR-054/055 nem fecha P01.

## Revalidação Kernel completa e correção de acesso — 2026-11-18

Reexecutei a suíte oficial `vendor/bin/phpunit --testsuite kernel` no banco
dedicado `aculta_simpletest`, no checkout local atualizado. Resultado: **1.168
testes, 22.763 assertions, 88 erros, 4 falhas, 6 warnings, 4 depreciações,
10 PHPUnit deprecations e 5 notices** (exit 2). Os erros continuam incluindo
29 ocorrências de dependência `private_message` indisponível, 31 falhas de
schema da View `eventos_datas_relacionadas`, 8 erros de plugin `ui_patterns`,
9 de dependência `ui_patterns_views`, além de schemas de Wiki, renderização de
Eventos e LMS. As quatro falhas foram em Structured Data de Eventos, retry de
coordenadas do Maps e dois testes de Wiki. Os detalhes completos estão em
`/tmp/aculta-adr057-kernel-current.log`. P01 permanece **FAIL**; a execução
confirma problemas preexistentes no checkout, não sucesso de gate.

A classe `EventOrganizationLifecycleKernelTest` tinha 31 erros de instalação
por falta das dependências reais `block`, `views`, `ui_patterns` e
`ui_patterns_views`; o harness agora as declara. Com a classe executável, o
teste revelou que `suggest` combinava a policy permitida com um resultado
`neutral`, mantendo o acesso neutro. O handler agora preserva o resultado da
policy e agrega a cacheabilidade da organização. O teste focal passou (**1
teste, 35 assertions**) e a classe completa passou (**31 testes, 1.051
assertions**).

PHP lint, PHPCS Drupal, descoberta PHPUnit e `git diff --check` passaram.
PHPStan nível 10 canônico produziu JSON válido com zero erros não suprimidos;
os guards `check` e `diff --include-worktree --analysis` passaram. Revisão
independente do diff não encontrou achados. Runtime/browser não foi usado;
esta correção não foi publicada nem sincronizada.

## Fixture temporal do JSON-LD — 2026-11-18

O `EventStructuredDataKernelTest` tinha duas datas de fixture fixas em
`2026-11-15`. Na execução de 2026-11-18, o controller omitiu corretamente
eventos que já não tinham datas futuras, deixando `html_head` vazio. Tornei os
dois intervalos relativos a `datetime.time` do Kernel, como outros testes da
classe já fazem. Os testes `testStructuredDataJsonLdOutput` e
`testPrivateOrganizerIdentityIsOmittedFromEventPresentation` passaram: **2
testes, 74 assertions**; a classe inteira passou: **6 testes, 162
assertions**. PHP lint, PHPCS Drupal e `git diff --check` passaram.

Uma nova execução integral de `--testsuite kernel` foi iniciada após as
correções de Eventos, mas a sessão de captura expirou depois de mais de 26
minutos; o processo terminou sem resumo recuperável e o arquivo de log ficou
vazio. Classificação: **INCOMPLETA**, sem atualizar os totais ou o status P01.

Uma primeira análise desta worktree retornou exit 1 por um `return.type` em
`EventOrganizationLifecycleKernelTest::queueItem()` durante alteração local
simultânea. Na reexecução canônica após a normalização do helper JSON-LD, o
PHPStan nível 10 passou com JSON válido e zero erros não suprimidos; os guards
`check` e `diff --include-worktree --analysis` passaram. A dívida congelada do
arquivo `EventStructuredDataKernelTest.php` foi reduzida de 104 para 103
ocorrências, removendo somente a entrada `return.type` corrigida. Naquele
momento, P01 permanecia **FAIL** por falta de resumo integral; a execução
recuperada posteriormente está registrada abaixo.

## Contrato do bloco de próximos Eventos — 2026-11-18

Os testes `EventDomainBoundaryKernelTest::testUpcomingBlockListsOnlyPublishedAndViewableEvents`
e `testUpcomingBlockHasNoEditorialActions` liam `#items` na raiz, enquanto o
bloco entrega os dados do componente em `#props.items`. Corrigi as assertions
para conferir o shape real do componente, preservar o filtro de publicação, a
cacheabilidade e a ausência de ações editoriais. Commit local:
`1e4718c15` (`test(events): alinha assertions ao contrato do SDC`).

PHP lint, PHPCS Drupal e o gate Drupal 12 do Portal (**1.236 checks**) passaram.
A suíte oficial Unit passou (**3.332 testes, 19.208 assertions**, quatro
depreciações PHPUnit). PHPStan nível 10 canônico passou com JSON válido e zero
erros não suprimidos; `phpstan-debt-guard check` e `diff --changed-files
--analysis` passaram, reduzindo a dívida `aculta_events` de 68 para 63
ocorrências.

A suíte Kernel integral observada em `/tmp/aculta-adr057-kernel-head-a821941ef-detached.log`
começou antes do commit e terminou com exit 2 após 1 h 51 min: **1.168 testes,
23.856 assertions, 56 erros, 3 falhas, 4 warnings, 4 depreciações e 10
PHPUnit depreciações**. Portanto, agora há um resumo integral recuperável; o
gate Kernel continua **FAIL**.

Os erros incluem indisponibilidade de `private_message` no harness de Commerce,
plugin Views `ui_patterns` e dependência `ui_patterns_views` ausentes em
harnesses de Eventos/LMS, além de schema incompleto de `aculta420_v2.settings`
para `ui_styles_entity_status` em três casos Wiki. Três falhas restantes são
uma assertion em `MapControllerKernelTest` e duas assertions de formulários da
Wiki. Os warnings incluem as duas assertions antigas de `#items` em
`EventDomainBoundaryKernelTest`, uma chave ausente `titulo` em
`SurfaceConfigReader` chamada pelo teste do Sitemap e uma chave `#title` em um
teste Wiki. Esta execução precede a correção das assertions do bloco de Eventos
e não mede exatamente o HEAD após ela.

Após a liberação do processo, executei a classe completa
`EventDomainBoundaryKernelTest` com `SIMPLETEST_DB` verificado como
`aculta_simpletest`: **10 testes, 397 assertions, exit 0**, com duas
depreciações PHPUnit. Isso confirma a correção do contrato `#props.items` nos
dois casos de bloco, mas não elimina os outros erros/warnings dos shards nem
fecha P01. O resultado da classe foi gravado no log local
`/mnt/disco1/tmp/aculta-agent-logs/command-q6hn6z7w.log`.

Os 56 erros não devem ser interpretados como 56 regressões da ADR-057. Os
grupos indicados acima precisam de triagem por harness/owner, e as falhas de
Wiki/Mapa permanecem abertas. P01 continua **FAIL** até a resolução por owner
e uma execução Kernel estável no estado publicável.

Distribuição dos erros no log (antes da correção focal do harness de Eventos):

| Grupo | Erros | Causa observada |
| --- | ---: | --- |
| Commerce / Melhor Envio | 29 | `private_message` indisponível no harness. |
| LMS | 18 | 8 instalações sem plugin `ui_patterns`; 9 configs com dependência `ui_patterns_views` ausente; 1 caso sem o plugin Views. |
| Eventos | 6 | 2 Views sem plugin `ui_patterns`; 2 assertions antigas de `#items`; 1 template SDC não descoberto; 1 processo de teste encerrado inesperadamente. |
| Wiki | 3 | Schema de `aculta420_v2.settings` incompleto para `ui_styles_entity_status`. |

Os quatro erros de contrato de `#items`/array e plugin Views em Eventos têm
correções focais documentadas abaixo; elas não foram incluídas no total da
execução integral. Os demais grupos não foram reclassificados como regressões
da ADR sem comparação com seus owners.

## Harness das Views administrativas de Eventos — 2026-11-18

O erro `EventRelatedViewsAccessKernelTest` do run integral vinha da ausência de
`ui_patterns`/`ui_patterns_views` na lista de módulos do harness, embora a
configuração de `aculta_events` instale Views com esse plugin. Declarei ambas
as dependências no teste, sem alterar o módulo de produção nem as assertions
de acesso mínimo. A classe focal passou: **2 testes, 18 assertions, exit 0**,
com duas depreciações PHPUnit. Log local:
`/mnt/disco1/tmp/aculta-agent-logs/command-9qrzyd8l.log`. O run integral
precedente não inclui esta correção; a redução no total só poderá ser
confirmada por uma nova execução integral. Revisão independente R2:
**aprovada, sem findings introduzidos**; confirmou que a mudança só completa o
harness e preserva as assertions de não-admin e exposição mínima de campos.
Nota de cobertura preexistente: o teste não verifica acesso positivo de
administrador às Views.
