## 0.1.0-dev.94 — feedback de educador distingue aluno e educador

- `EducatorFeedbackPolicy` (`aculta_lms.educator_feedback_policy`) define educador
  como autor diferente do dono da resposta e com a permissão `grade students` no
  curso. `EducatorFeedbackEventHooks` e `EducatorFeedbackAuthorCardHooks` usam o
  mesmo critério.
- `lms_answer_comment` aceita comentário do aluno dono da resposta; esses
  comentários não recebem o rótulo "Educador(a)" nem disparam a notificação de
  feedback, mas continuam com cartão de autor (nome + acervo quando não há perfil).
- O roster usa `AuthorCardBuilder::buildFallback()`: quem não tem perfil público
  continua na turma com nome de exibição + avatar do acervo.
- Validação: PHPUnit Unit 88/453 PASS (8 depreciações PHPUnit); Kernel do roster
  1/33 PASS (37 depreciações upstream); gates Portal (1112), foto, SDC, design
  foundations, browser validators, Foundation e shell-contract PASS. RUNTIME
  STATUS: DEFERRED para a verificação visual do feedback (o Runtime não tem turma).

## 0.1.0-dev.93 — avatar padrão nas turmas e no feedback do educador (Fase 4)

- Roster da turma e participantes da sala mostram cartão de autor (`profile_compact`, tamanho `sm`)
  com a mesma regra de avatar do site. Sem identidade pública, o nome de exibição continua na lista.
- Feedback do educador em resposta (`lms_answer_comment`) mostra o cartão com o papel "Educador(a)".
- Validação: PHP lint PASS. PHPUnit e Runtime não executados (sem `vendor/`). RUNTIME STATUS: DEFERRED.

## 0.1.0-dev.92 — dependência declarada do provedor de pagamento

- `aculta_lms` declara dependência direta de `aculta_portal:aculta_pagamentos_mercadopago`; nenhum código do LMS mudou. Os quatro módulos LMS relacionados verificados não usam APIs do MP.
- Unit: 87 testes / 447 assertions PASS (8 depreciações PHPUnit). Kernel MariaDB dedicado: 29 testes / 836 assertions PASS, com 98 depreciações Core/contrib. O harness usa classes reais MP e os contratos neutros.

## 0.1.0-dev.91 — fechar setup dos Kernels LMS com contratos reais — 2026-10-26

- Os harnesses Kernel que habilitam ou instalam `aculta_lms` agora compartilham
  definições de teste das classes reais necessárias de Portal, Messenger e
  Studio. O recorte não instala configuração alheia ao cenário nem adiciona
  serviços fictícios ao produto. `AcultaLmsInstallTest` também verifica o
  serviço real `CheckoutSettings`, o tipo padrão `curso` e o controller
  Ganjalab referenciado pela rota.
- Kernel agregado: 29 testes / 835 assertions PASS no MariaDB dedicado; uma
  depreciação vem de `webform_score` contrib. PHPCS Drupal/DrupalPractice e
  PHP lint dos arquivos alterados PASS. Isso remove os erros de setup dos
  harnesses LMS, mas não equivale a instalar a closure completa Portal/
  Messenger/Studio com toda sua configuração. Runtime não foi alterado.

## 0.1.0-dev.90 — Kernel focal do painel de turma com contratos reais — 2026-10-26

- `ClassDashboardKernelTest` não habilita a instalação inteira do Portal, pois isso carregava configuração e serviços de Commerce/Domain não usados pelo caso. O fixture Kernel mantém Group, LMS e `lms_classes` reais, registra os namespaces de código reais e instancia `LearningDashboardManager`, `ClassDashboardAccessCheck`, `LearningDashboardController`, prazo e compositor de Studio reais. Não há serviço fictício nem alteração no produto.
- O teste passou: 1 teste / 48 assertions, verificando educador, estudante, vínculo turma→curso, escopo de membros, cache tags/contextos e invalidação ao remover relacionamento. PHPCS e PHP lint PASS. 39 depreciações foram reportadas por Core/contrib/Group/LMS.
- Isso fecha a falha de setup deste caso focal, sem validar a compilação/inicialização limpa de toda a cadeia de serviços Portal/Messenger/Studio. Kernel agregado reexecutado: 29 testes / 98 assertions, 22 erros de setup; todos os 22 harnesses que instalam `aculta_lms` sem Portal falham pelo mesmo serviço obrigatório `aculta_portal.checkout_settings`. Nenhuma assertion funcional falhou nos testes que executaram. Runtime não foi alterado.

## 0.1.0-dev.89 — regressão da suíte Kernel agregada — 2026-10-26

- Unit LMS: 87 testes / 447 assertions PASS (8 depreciações PHPUnit).
- Kernel LMS agregado: 29 testes, 50 assertions e 23 erros de setup. Os harnesses que compilam serviços `aculta_lms` sem a dependência obrigatória Portal falham por `aculta_portal.checkout_settings` ausente; não se trata de assertions funcionais. Alguns focais executados anteriormente continuam registrados abaixo. A cadeia de instalação limpa Portal/Messenger/Studio permanece sem verificação agregada.
- Reproduzido isoladamente no worktree `ClassDashboardKernelTest`: 1 erro/0 assertions na compilação do container pela mesma ausência de `aculta_portal.checkout_settings`; a falha é do harness incompleto, sem evidência de defeito funcional no dashboard.

## 0.1.0-dev.88 — publicar avisos pelo kernel Messenger (2026-10-24)

- O producer de eventos LMS foi movido para o módulo dono dos cursos e
  workflows; consumer, persistência, preferências e entrega permanecem no
  `aculta_messenger`.
- O aviso no site é obrigatório; e-mail segue off/direto/digest por preferência
  e política. Unit integrada e Kernel LMS executados constam no changelog do
  Messenger. Runtime não homologado.

## 0.1.0-dev.87 — remoção de aliases temporários em desenvolvimento (2026-10-24)

- Removidos aliases temporários /minha-aprendizagem, /ganjalab420 e /studio/ganjalab420. Única entrada estudantil canônica: /studio/ganjalab. Nenhuma alteração de matrícula, curso ou certificado. Gate PHPUnit/Runtime pendente.
- Regra transversal: `AGENTS.md` e `scripts/validate-no-temporary-route-aliases.php`.

# Changelog — aculta_lms

## 0.1.0-dev.86 — tipo de pedido de curso lido da configuração do Portal (P-CART E) — 2026-10-22

- `CourseOrderEnrollmentManager::isCourseOrder()`, `EnrollmentFormHooks`, `CourseOrderHooks` e `CourseEnrollmentPurchaseForm` usam `CheckoutSettings` em vez do literal `curso`. Novas compras usam o tipo configurado; pedidos `curso` históricos seguem reconhecidos. Padrão sem mudança (`curso`). O tipo de item e a variação `curso` não mudam. RUNTIME STATUS: DEFERRED.

## 0.1.0-dev.85 — provider de checkout do Portal (P-CART C) — 2026-10-22

- `LmsCheckoutProvider` declara ao Portal o contexto LMS (tipo de pedido `curso`, checkout comum e flow nativo). Somente leitura; não altera matrícula, curso ou compra. RUNTIME STATUS: DEFERRED.
- Alinhamento: `aculta_lms.info.yml` estava em dev.80 enquanto o changelog já registrava dev.84.

## 0.1.0-dev.84 — rodar Kernel e Unit do LMS e registrar o resultado medido — 2026-10-22

Tarefa: "Rodar os Kernels e unitários dos módulos LMS e Forum alterados; registrar testes/asserções/depreciações em cada CHANGELOG; confirmar git status limpo e listar os commits da sequência."

- Documento apenas; nenhuma alteração de código, configuração ou Runtime.
- Unit: `tests/src/Unit` — 74 testes, 408 asserções, OK com 8 depreciações do
  PHPUnit.
- Kernel: `tests/src/Kernel` — 29 testes, 832 asserções, OK com 93
  depreciações conhecidas, todas de código contrib e já presentes antes desta
  sequência: anotações de plugin do Group (relação, argumento e acesso de
  Views), `FieldType`/`EntityType` do Webform, `fraction` e `entity_print`,
  além do retorno implicitamente anulável de
  `webform_score_entity_field_access()` e do hook de requisitos legado.
- Comandos: a partir de `web/core`, com `secrets/simpletest-db.env` carregado
  no ambiente, `../../vendor/bin/phpunit -c phpunit.xml.dist
  ../modules/custom/aculta_portal/modules/aculta_lms/tests/src/{Unit,Kernel}`;
  banco local `aculta_simpletest`; o Runtime de teste não foi tocado.
- Resultado anteriormente registrado por engano no `aculta_forum_lms`
  dev.42; este é o registro correto do módulo LMS.

## 0.1.0-dev.83 — regressão Kernel da política central de upload — 2026-10-22

Tarefa: "Migrar anexos LMS para a política central `aculta_portal.user_upload_policy`, com aba própria no painel de uploads. Política efetiva inalterada. Kernel de regressão."

- A migração já estava no código: o `aculta_portal` 0.4.0-dev.252 criou o consumidor `lms` e a aba "LMS" do painel de uploads, e o `aculta_lms` 0.1.0-dev.80 passou a aplicar `restrictValidators(..., 'lms')` em `SubmissionImageHooks`. Esta entrega fecha a verificação e a documentação; a única alteração de produção corrige o texto de ajuda do campo.
- Correção no texto de ajuda: desde `0361ae05` o hook montava a lista com o teto inteiro da política (`allowedExtensions('lms')`) para não ler `FileExtension` fora da política, então uma atividade mais restrita exibia extensões que ela não aceita — e o `SubmissionImageHooksTest` ficou vermelho. O novo `UserUploadPolicy::acceptedExtensions()` formata a interseção já restrita; o hook exibe essa lista e o gate `validate-aculta-image` segue verde, porque a leitura fica no dono da política.
- Superfície confirmada: o único upload de usuário do LMS é o campo File Upload da resposta (`form_lms_answer_form_alter`). `aculta_lms`, `aculta_lms_resources`, `aculta_lms_messenger` e `aculta_forum_lms` não têm campos de arquivo próprios; os uploads do editor H5P são administrativos e ficam fora da política de usuário.
- Novo Kernel `SubmissionUploadPolicyKernelTest` (5 testes, 39 asserções, PASS): herança da aba LMS sobre o global sem mudança de política efetiva (9 extensões, 10 MB, texto de ajuda limitado à interseção); atividade que não amplia extensões (`exe`/`txt` removidos) nem eleva o teto de tamanho; aba LMS salva que restringe para `pdf docx` e 5 MB sem tocar na política global; formulário sem upload intacto; validadores nativos e validador de imagem preservados quando o serviço da política não existe.
- Política efetiva inalterada: nenhuma configuração foi salva no Runtime de teste; a aba LMS continua herdando o global. O Kernel roda sem habilitar `aculta_portal`/`aculta_lms`, com os serviços reais do `aculta_image`, e sem depreciações.
- README corrigido: o trecho que descrevia o validador como aplicável só a extensões raster e a delegação de arquivos não raster à validação nativa estava desatualizado desde a dev.80.

## 0.1.0-dev.82 — smoke autenticado da navegação por curso — 2026-10-22

Tarefa: "Pendência de smoke autenticado do aculta_lms (CHANGELOG.md linha 51): executar o smoke da navegação por curso com conta de Educador e de estudante."

- Smoke HTTP no Runtime de teste com duas contas descartáveis: educador (papel global `lms_teacher` + matrícula no curso 1) e estudante (matrícula no curso 1 e na turma criada para o smoke). Sem senha no repositório; sessão obtida por login único do Drush.
- A navegação por curso exige aceite vigente dos termos (`aculta_portal.access.protected_route:course`). Contas novas recebiam **403** em `/course/1/start`; após registrar o aceite pelo serviço `aculta_portal.aculta_user_agreement_policy`, o cenário seguiu. Isso é pré-requisito de conta, não defeito de rota.
- Resultados por cenário (esperado → obtido):
  - `GET https://cursos.aculta.toca.net.br/group/1` — educador e estudante: **200** → **200** ("Introdução ao Antiproibicionismo | Cursos").
  - `GET https://cursos.aculta.toca.net.br/course/1/start` — ambos: **302** para a atividade → **302** para `/curso/.../questao-de-revisao`, que responde **200**.
  - `GET https://conta.aculta.toca.net.br/minha-aprendizagem` — ambos: **200** com o curso, "Acessar curso" e link para `/course/1/start` → **200** ("Aprendizagem | Conta ACULTA").
  - `GET /painel-administrativo/lms/curso/1/turma/{turma}/painel`, `.../participantes`, `.../atividade/1/prazo` e `.../certificados/emitir` — educador: **200** → **200** (painel com "Curso: Introdução ao Antiproibicionismo", 1 estudante, 14 atividades sem prazo e link da lista de participantes); estudante: **403** → **403** ("Acesso negado").
- Defeito corrigido no caminho do painel: a célula de prazo era um render array de `Link` sem a chave `data`, e o preprocess de tabela o interpretava como atributos, causando 500 (`Unexpected type for $value (Drupal\Core\Url)`). A célula agora é `['data' => Link::...->toRenderable()]`.
- Defeito corrigido no formulário de prazo: `ClassActivityDeadlineAccessCheck::access()` declarava `$routeCourse`, mas o parâmetro da rota é `{course}`; o formulário respondia 500 por não resolver o argumento. O parâmetro passou a se chamar `$course` e a variável interna do curso da turma, `$classCourse`.
- Testes: Unit focal do módulo 5 testes / 18 asserções PASS (`LearningDashboardControllerTest`, `ClassActivityDeadlineManagerTest`, `ClassActivityDeadlineFormTest`); PHPCS nos arquivos alterados sem erro.
- Cenário removido ao final (turma, relações de turma e de matrícula, situações de curso e de aula, linhas do ledger de aceite e as duas contas). Runtime de teste voltou à linha de base: 1 situação de curso no curso 1, 2 situações de aula, 2 respostas e 0 turmas.

## 0.1.0-dev.81 — download de anexo negado a conta bloqueada — 2026-10-22

- Verificação HTTP no Runtime de teste da rota `/resposta/{lms_answer}/baixar-arquivo`, com cenário descartável (tipo de atividade `t14_upload`, aula, curso, matrícula e resposta com arquivo privado).
- Conta válida e dona da resposta: `GET` devolve **200**, `Content-Disposition: attachment; filename="t14-<nonce>.txt"` e o conteúdo de 39 bytes.
- Conta autenticada sem matrícula: **403** (`UploadedAnswerAccessCheck` → `see_details` negado).
- Conta bloqueada: **404**. O core apaga todas as sessões da conta ao bloquear (`User::postSave`), então a requisição passa a anônima, e o site responde 404 a anônimo nessa rota — mesmo resultado do controle anônimo. Mesmo uma sessão sobrevivente seria recusada pelo provider `Cookie`, que só aceita `users_field_data.status = 1`. O ramo de conta inativa do `UploadedAnswerAccessCheck` (403) continua coberto pelo Kernel `AttemptArchiveLifecycleTest` (dono bloqueado).
- Cenário removido ao final (resposta, arquivo privado, matrícula, curso, aula, atividade, tipo e campo). O Runtime voltou à linha de base: 1 curso, 10 usuários, 2 respostas, 0 arquivos em `private://lms_file_upload`, tipos `no_answer` e `true_false`.

## 0.1.0-dev.80 — respostas com arquivo sob a política central de upload

- `SubmissionImageHooks` recebe `aculta_portal.user_upload_policy` (serviço `aculta_lms.submission_image_hooks`) e aplica `restrictValidators(..., 'lms')` ao campo de envio das atividades File Upload. Educadores continuam escolhendo as extensões de cada atividade, mas só dentro da política LMS; limite de tamanho só diminui.
- O validador de imagem `AcultaLmsSubmissionImage` continua aplicado às imagens rasters.
- Testes: `SubmissionImageHooksTest` (Unit, 3 testes, 10 asserções) cobre o acréscimo do validador de imagem, a impossibilidade de ampliar extensões ou tamanho e a não alteração de formulários sem upload. Os testes Kernel de upload no Portal passam (ver CHANGELOG do aculta_portal 0.4.0-dev.252).
- Correção da própria entrega: a primeira versão injetava `UserUploadPolicy` pelo tipo da classe e quebrava a montagem do container (`drush cr` falhava com "no such service"). Agora o serviço usa o FQCN do hook com `@?aculta_portal.user_upload_policy`, como os demais hooks do LMS. Sem a política, o hook mantém os validadores nativos. Kernel `AttemptArchiveLifecycleTest` (que monta o container) voltou a passar.
- Runtime de teste: sem alteração de configuração ou de schema; o consumidor `lms` herda a política global até que alguém salve a aba. `drush cr` OK; verificado por leitura no container: a atividade pedindo `png pdf exe txt` recebe `png pdf`, limite de 10 MB e o validador de imagem.
- Smoke HTTP autenticado no Runtime (conta de teste `teste.comum`, login único gerado por Drush, sem senha no repositório): a atividade File Upload com `png exe pdf txt` recebe, pelo formulário real, a recusa do `.exe` com a mensagem "Apenas arquivos com as seguintes extensões são permitidos: png pdf" (teto da política LMS), e o PNG é aceito e gravado na resposta (`private://lms_file_upload`).
- Cenário criado só para o smoke (tipo `smoke_upload`, campo `field_file_extensions`, aula, curso e matrícula) e removido ao final, com as respostas e arquivos de teste. O Runtime voltou aos tipos de atividade originais.
- Correção da revisão visual: o texto de ajuda do campo mostrava as extensões da atividade ("png exe pdf txt") e não o teto. O hook agora reescreve a ajuda com a lista efetiva ("Envie um arquivo. Extensões permitidas: png pdf."). `SubmissionImageHooks` recebe a tradução por injeção (`string_translation`). Unit 74/408 OK.
- Revisão visual com sessão autenticada no Firefox (X11, `tests/browser/aculta-lms/answer-upload-visual.mjs`): 8/8 verificações OK, incluindo o `.exe` recusado com "Somente arquivos com as seguintes extensões são permitidos: png, pdf", o PNG aceito, sem erros de JavaScript e sem rolagem horizontal. O celular foi verificado em 390 px, com viewport emulado pelo BiDi (`browsingContext.setViewport`), com `innerWidth` conferido antes da captura. Resultado final: 9/9 OK.

## 0.1.0-dev.79 (situação por aluno para a série, sob o aceite único)

- `StudentStatusSnapshotEvent` (`aculta_lms.student_status_snapshot`) emitido no cron com uma linha por curso e aluno. Só entram alunos com aceite único vigente (`aculta_user_agreement_terms`). Um aluno em duas turmas do mesmo curso conta uma vez.
- `StudentStatusSnapshotCronHooks`: sem o ledger instalado, não emite nada (`@?aculta_user.aculta_user_agreement_ledger`).
- `countClassStatuses($class, $course, TRUE)` devolve `by_student`. O painel não pede esse dado; o comportamento não mudou.
- Dependência declarada em `aculta_user`.
- Testes: Unit 71 (sem mudança de resultado). Sem turmas no Runtime de teste; o caminho com linhas não foi observado lá.

## 0.1.0-dev.78 — instantâneo diário da situação por turma para estatísticas — 2026-10-21

- `ClassStatusSnapshotCronHooks` emite `ClassStatusSnapshotEvent` (`aculta_lms.class_status_snapshot`) no cron, com uma linha por turma e situação (alunos, não iniciados, em andamento, aguardando avaliação, aprovados, reprovados). Só contagens agregadas; nenhum aluno é identificado.
- `LearningDashboardManager::countClassStatuses()`: contagem extraída do painel da turma, sem checagem de acesso. O painel mantém acesso e cache; os valores não mudam.
- Alias `Drupal\aculta_lms\LearningDashboardManager` para injeção por tipo.
- Decisão (2026-10-21): série diária por turma. Série diária **por aluno** não foi criada: exigiria revisão LGPD antes.
- Testes: Unit 71 e Kernel `ClassDashboardKernelTest` (1 caso) passam, com deprecações já existentes. No Runtime de teste não há turmas (`lms_class` = 0), então o caminho com linhas está coberto só pela unit test do assinante em `aculta_statistics`.

## 0.1.0-dev.77 — controlar a lista de participantes por turma — 2026-10-21

- Educadores podem exibir ou ocultar, por turma, uma lista limitada aos nomes
  de estudantes ativos matriculados naquela mesma turma; o padrão é oculto.
  Formulário e rota validam o curso/turma e a permissão contextual. O Kernel
  cobre padrão oculto, isolamento entre turmas e exclusão de contas bloqueadas:
  1 teste / 28 assertions PASS (36 depreciações upstream de Core/contrib).
- `AcultaLmsInstallTest` confere a rota administrativa (1/17, com 3
  depreciações upstream); Unit LMS: 71/397 PASS (8 depreciações PHPUnit);
  PHPCS Drupal/DrupalPractice PASS. Runtime de teste recebeu somente as duas
  configurações do campo via importação parcial revisada; cache rebuild passou.
  Smoke autenticado e revisão visual seguem pendentes.

## 0.1.0-dev.76 — organizar a administração pelo curso — 2026-10-21

- Painel da turma e configuração de prazo agora ficam abaixo de
  `/painel-administrativo/lms/curso/{course}`; acesso confere se a turma e a
  atividade pertencem ao curso indicado. A navegação remove atalhos globais de
  aulas, atividades e turmas, mantendo o curso como entrada administrativa.
- Unit: 71 testes / 394 assertions PASS. Kernel MariaDB: 23 testes / 764
  assertions PASS; `AcultaLmsInstallTest` focal: 4/44 (93 depreciações upstream). PHP CLI 8.4.26; rebuild no Runtime de teste confirmou os caminhos course-first e a navegação por curso; smoke autenticado pendente.

## 0.1.0-dev.75 — cobrir hooks Commerce de pedido — 2026-10-21

- `AcultaLmsInstallTest` agora confirma o registro dos hooks de insert e update
  do pedido, que chamam a reconciliação de memberships compradas. O teste focal
  passou com 4 testes / 42 assertions; Kernel LMS/Messenger: 24 testes / 772
  assertions PASS, com 95 depreciações upstream.

## 0.1.0-dev.74 — reconciliar memberships com itens atuais do pedido — 2026-10-21

- A sincronização de pedido pago agora reconcilia as memberships marcadas com
  sua origem: mantém as turmas ainda compradas; revoga turma removida do pedido
  e transfere a origem se existir outra compra ativa. Matrículas manuais sem
  origem continuam preservadas. `CourseOrderEnrollmentManagerTest`: 9/47;
  Unit LMS/Messenger: 89/482 PASS. Kernel LMS/Messenger: 24/770 PASS (95
  depreciações upstream). PHPCS Drupal/DrupalPractice PASS.

## 0.1.0-dev.73 — negar download após remoção do CourseStatus — 2026-10-21

- O Kernel agora reproduz o intervalo entre a exclusão do `CourseStatus` e a
  limpeza enfileirada do `LessonStatus`/resposta; o download privado falha
  fechado sem erro 500. `AttemptArchiveLifecycleTest`: 10 testes / 409
  assertions PASS; Kernel LMS/Messenger: 24/770 PASS, com 95 depreciações
  upstream. PHPCS Drupal/DrupalPractice PASS no arquivo da suíte.

## 0.1.0-dev.72 — preservar matrícula após outra compra ativa — 2026-10-21

- `CourseOrderEnrollmentManagerTest` agora cobre a sincronização de um pedido
  integralmente reembolsado quando há outra compra ativa para a mesma pessoa,
  turma e curso: a membership permanece e sua origem muda para o pedido válido.
  Focal: 7 testes / 35 assertions PASS; Unit LMS/Messenger: 87/470 PASS.
  Kernel LMS/Messenger: 23/730 PASS (95 depreciações upstream do Core/contrib).

## 0.1.0-dev.71 — proteger membership manual com compra ativa — 2026-10-21

- A remoção pelo educador agora consulta pedidos ativos da mesma turma mesmo
  quando a membership foi criada manualmente antes da compra. A conferência
  valida estudante, produto associado ao curso, estado do pedido e entitlement;
  sem compra ativa, a matrícula manual segue removível após revalidação em cada
  request. `EnrollmentFormHooksTest`: 7/49; `CourseOrderEnrollmentManagerTest`:
  6/25; Unit LMS/Messenger: 86/460; `AcultaLmsInstallTest`: 4/40;
  Kernel LMS/Messenger: 23/730 (95 depreciações de Core/contrib). Runtime
  `cursos`: sem updates; cache rebuild e resolução do manager/hook passaram,
  com warning preexistente de cópia de assets H5P. Nenhuma configuração foi
  importada.

## 0.1.0-dev.70 — invalidar acesso armazenado ao remover vínculo da turma — 2026-10-21

- O resultado positivo do painel de educadores agora depende das listas Group
  de relações da turma e do curso. A remoção da relação curso→turma invalida
  imediatamente o grant armazenado. `ClassDashboardKernelTest`: 1/46; a
  regressão LMS/Messenger: 23/728 assertions PASS, com depreciações de
  Core/contrib. Runtime `cursos`: sem updates pendentes; cache rebuild e
  resolução do manager do painel passaram, com warning preexistente de cópia de
  assets H5P. Nenhuma configuração foi importada.

## 0.1.0-dev.69 — invalidar negação do painel de turma sem curso — 2026-10-21

- O controle de acesso do painel de educadores também invalida a negativa
  quando a turma recebe relação com o curso. A cobertura Kernel exercita ambas
  as negativas armazenadas e confirma invalidação pela cache tag Group.
  `ClassActivityDeadlineTest`: 1/52; regressão Kernel LMS/Messenger: 23/725
  PASS (95 depreciações upstream). Runtime `cursos`: sem updates pendentes;
  cache rebuild e resolução do serviço do painel passaram, com warning
  preexistente de cópia de assets H5P; nenhuma configuração foi importada.

## 0.1.0-dev.68 — invalidar negativa de acesso quando a turma é vinculada — 2026-10-21

- O access checker de prazo agora inclui a lista de relações Group da turma na
  cacheabilidade da negação sem curso pai. Também depende do curso quando a
  atividade ainda não pertence ao seu conteúdo. O Kernel prova que criar o
  vínculo invalida a negativa armazenada: `ClassActivityDeadlineTest` 1/49;
  regressão Kernel LMS/Messenger 23/722 PASS (95 depreciações upstream). No
  Runtime `cursos`, Drush confirmou ausência de updates; cache rebuild e
  resolução do access checker passaram, com warning preexistente de cópia de
  assets H5P. Nenhuma configuração foi importada.

## 0.1.0-dev.67 — exibir prazo no formulário de resposta — 2026-10-21

- Corrige a exibição do prazo para estudantes: o hook injeta o serviço de
  tradução e usa o namespace instalado de `DateTimeItemInterface`; a rota
  positiva agora mostra o prazo da turma com o fuso da conta. Kernel integrado
  valida entidades LMS/Group reais e autowiring do hook: 1 teste / 46
  assertions. Unit LMS/Messenger: 84/445; Kernel combinado: 23/719, com 95
  depreciações upstream; PHPCS Drupal/DrupalPractice PASS. No Runtime `cursos`,
  `drush updatedb:status` não encontrou updates, cache rebuild passou e o
  container resolveu o serviço do hook; houve apenas warning preexistente da
  cópia de assets H5P. Nenhuma configuração foi importada.

## 0.1.0-dev.66 — cobrir resposta aberta sem atividade — 2026-10-21

- `FreeTextReviewFeedback` mantém o campo de resposta sem os controles de
  feedback dependentes da atividade removida; a tela de correção informa que a
  atividade não está disponível e o score formativo falha fechado em zero.
  Unit focal: 4/20; combinado LMS/Messenger: 84/445, com 8 depreciações
  PHPUnit. PHPCS Drupal/DrupalPractice PASS.

## 0.1.0-dev.65 — tolerar atividade removida em respostas abertas — 2026-10-21

- O plugin de resposta aberta não chama `Answer::getActivity()` quando a
  referência foi removida na limpeza assíncrona. Score formativo retorna zero,
  o formulário mantém a resposta sem o widget de feedback e a avaliação mostra
  que a atividade não está disponível. Unit focal: 3/15; combinada LMS/
  Messenger: 83/440, com 8 depreciações PHPUnit.

