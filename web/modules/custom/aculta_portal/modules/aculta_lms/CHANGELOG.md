# Changelog — aculta_lms

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

## 0.1.0-dev.64 — tolerar atividade removida no aviso de prazo — 2026-10-21

- O formulário da resposta resolve a atividade pela referência do campo. Se a
  atividade tiver sido removida durante a limpeza assíncrona, omite o prazo sem
  chamar `Answer::getActivity()`. Teste focal: 2/6; PHPCS Drupal/DrupalPractice
  PASS. Unit LMS/Messenger: 82 testes / 433 assertions PASS.

## 0.1.0-dev.63 — suprimir aviso em edição editorial — 2026-10-21

- `CourseContentEventHooksTest` confirma que editar atividade já publicada sem
  liberar conteúdo novo não produz aviso, conforme a decisão F13 de notificar
  somente eventos acionáveis persistidos. Focal: 4 testes / 11 assertions PASS;
  Unit LMS/Messenger: 79/407 PASS, com 8 depreciações PHPUnit.

## 0.1.0-dev.62 — isolar certificados no painel do estudante — 2026-10-21

- O Kernel cria certificados de duas contas e confirma que o painel retorna
  apenas o da pessoa autenticada, preservando a tag de invalidação da entidade.
  `LearningDashboardKernelTest`: 1 teste / 41 assertions PASS. Kernel combinado
  LMS/Messenger: 23/712; Unit combinado: 78/406 PASS.

## 0.1.0-dev.61 — manter fail-closed em pagamento inválido — 2026-10-21

- A proteção de matrícula Commerce nega a exclusão quando o armazenamento
  retorna um registro que não é `PaymentInterface`; agora também evita erro 500
  ao tentar usá-lo como dependência de cache. `EnrollmentFormHooksTest`: 6 testes
  / 38 assertions PASS. Unit LMS/Messenger: 78/406; Kernel: 23/708 PASS. Há 92
  depreciações upstream no Kernel e 8 depreciações PHPUnit na Unit.

## 0.1.0-dev.60 — exibir conclusão elegível ao educador — 2026-10-21

- O teste Kernel confirma que uma conclusão aprovada aparece no seletor do
  formulário para o educador autorizado por `grade students` no curso. Focal:
  5 testes / 159 assertions PASS; regressão combinada LMS/Messenger: 23 testes /
  708 assertions PASS, com 92 depreciações upstream.

## 0.1.0-dev.59 — provar emissão por educador de turma — 2026-10-21

- O Kernel usa educador real com papel Group contextual `grade students`, sem
  permissão administrativa global, e percorre emissão e revogação por formulário.
  A fixture instala o schema de `entity_usage` usado pela membership. Teste focal:
  5 testes / 157 assertions PASS; 38 depreciações de Core/contrib.

## 0.1.0-dev.58 — cobrir emissão e revogação pelos formulários — 2026-10-21

- `CertificateLifecycleTest` percorre os submits dos formulários de educador,
  verifica o redirecionamento público após emissão e a preservação do snapshot
  com motivo após revogação. Kernel focal: 5 testes / 155 assertions PASS; 38
  depreciações vêm do Core/contrib carregado no teste. Revisão visual e smoke
  Runtime dos formulários seguem pendentes.

## 0.1.0-dev.57 — tratar referências ausentes na emissão de certificado — 2026-10-21

- O manager resolve curso e estudante pelos campos do `CourseStatus` e rejeita
  referências ausentes com resposta 422 controlada, sem chamar os getters
  tipados do LMS que podem falhar durante a limpeza assíncrona.
- O formulário do educador ignora conclusões cujo usuário já não está
  referenciado. `CertificateLifecycleTest` passou com 4 testes / 126 assertions;
  Kernel combinado LMS/Messenger: 22 testes / 675 assertions PASS, com 92
  depreciações upstream. Unit LMS: 61 testes / 334 assertions PASS (8
  depreciações PHPUnit). Runtime/visual não foram alterados.

## Evidência Runtime F11 — 2026-10-21

- No Runtime `cursos` confirmado em `aculta_runtime`, um fixture temporário
  criado via APIs Drupal permitiu emitir um snapshot elegível e exercitar o
  controlador com os serviços reais. Os dados públicos, QR SVG (`image/svg+xml`,
  13.577 bytes) e PDF (`%PDF`, 919 bytes) foram gerados com sucesso.
- Um segundo fixture isolou temporariamente os listeners de conclusão e
  certificado e validou as rotas HTTP anônimas: verificação, QR e PDF retornaram
  200; MIME SVG/PDF e assinatura `%PDF` estavam corretos. A primeira execução
  havia acionado o evento normal de conclusão ao salvar o `CourseStatus`; a
  Message, chave de deduplicação e agregado estatístico dessa fixture foram
  identificados e removidos. Em ambos os testes, as entidades temporárias foram
  excluídas por APIs Drupal; certificados, avisos e fila de e-mail ficaram sem
  resíduos.
- Limite: emissão/revogação pelo formulário e revisão visual seguem pendentes;
  nenhum e-mail foi enviado.

## 0.1.0-dev.56 — confirmar dados públicos mínimos do certificado — 2026-10-21

- `CertificateLifecycleTest` valida nome, curso, horas e estado na página por
  código opaco e confirma que e-mail e motivo interno de revogação não aparecem.
  Kernel focal: 3 testes / 102 assertions; suíte combinada: 21 testes / 651
  assertions PASS, com 92 depreciações de Core/contrib upstream.

## 0.1.0-dev.55 — alinhar teste de matrícula manual ao schema — 2026-10-21

- A fixture de matrícula manual agora mantém `field_course_order` presente e
  vazio, como no Group LMS instalado. Teste focal: 5/32 assertions PASS;
  Unit combinada LMS/Messenger: 77/400 PASS.

## 0.1.0-dev.54 — proteger matrícula por compra contra remoção manual — 2026-10-21

- `EnrollmentFormHooksTest` confirma que matrícula manual pode ser removida;
  pedidos pagos ou parcialmente reembolsados e pedidos gratuitos concluídos
  continuam protegidos; reembolso total permite remoção; origem quebrada falha
  fechada. A cobertura focal passou com 5 testes / 31 assertions; Unit combinada
  LMS/Messenger passou com 77 testes / 399 assertions (8 depreciações PHPUnit).

## 0.1.0-dev.53 — validar painel por papel de turma — 2026-10-21

- O Kernel do painel de turma usa o grant `grade students` no papel Group do
  educador em vez de permissão administrativa global e confirma que estudante
  não obtém os agregados. Isso cobre a autorização contextual prevista na
  matriz F16. `ClassDashboardKernelTest` passou com 1 teste / 43 assertions;
  Kernel combinado LMS/Messenger revalidado: 21 testes / 646
  assertions PASS; depreciações upstream registradas.

## 0.1.0-dev.52 — cobrir estados de pagamento para matrícula — 2026-10-21

- `CourseOrderEnrollmentManagerTest` cobre pedido gratuito concluído, pagamento
  completo, estorno parcial, estorno total e pedido incompleto. Unit combinada
  LMS/Messenger: 72 testes/368 assertions PASS; os 8 avisos são de metadados
  PHPUnit antigos em outros testes. PHPCS passou nos arquivos LMS alterados.
- Essa cobertura testa a decisão local de entitlement, sem chamar gateway nem
  simular uma transação remota. A homologação Runtime de pagamento/refund real
  continua pendente.

## 0.1.0-dev.51 — corrigir role da matrícula por compra — 2026-10-21

- A matrícula por pedido deixa de gravar `lms_class-member` como role
  individual. Essa role tem escopo `insider` e o Group a aplica pela própria
  membership; enviá-la no campo `group_roles` fazia o hook de conclusão de
  pedido falhar antes de concluir a matrícula.
- Runtime `cursos`: pedido gratuito passou de `draft` a `completed` e criou
  membership com `field_course_order` apontando para o pedido; o papel global
  `estudante` foi concedido e uma membership manual do educador foi preservada.
  Pedido, produto, variação, turma e curso foram removidos via APIs Drupal. A
  notificação tentou usar o endereço reservado da conta de teste, foi rejeitada
  pelo SMTP e a Message/fila temporárias foram removidas. Checkout HTTP, pedido
  pago e reembolso continuam pendentes. Unit LMS/Messenger: 67 testes/347
  assertions PASS; Kernel: 21/646 PASS, com 92 depreciações upstream. Gate
  Drupal 11 (1010), índice de estado (29), imagens, slugs (385) e PHPCS
  passaram. PHP CLI 8.4.26; PHP 8.5 não validado.

## 0.1.0-dev.50 — negar PDF de certificado revogado — 2026-10-21

- `CertificateLifecycleTest` chama o controlador PDF com um certificado
  revogado e confirma 404 sem instanciar Entity Print ou gerar QR. Kernel
  focal: 3 testes / 97 assertions; suíte combinada Kernel LMS/Messenger:
  21 testes / 646 assertions PASS. Unit segue 67 / 347, sem alteração de
  código Unit nesta entrega; 92 depreciações de Core/contrib.

## 0.1.0-dev.49 — remover domínio real do teste QR — 2026-10-21

- O teste de integração QR usa um host reservado `.test`, sem fixar domínio
  dependente de ambiente. PHPCS Drupal/DrupalPractice e Unit do módulo:
  51 testes / 281 assertions PASS. Regressão combinada LMS/Messenger:
  Unit 67 testes / 347 assertions; Kernel 21 testes / 643 assertions PASS.
  Foram reportadas 8 depreciações PHPUnit e 92 de Core/contrib. O gate geral
  de endereços continua
  apontando literais preexistentes em `aculta_qrcode` e `aculta_mailer`, fora
  desta alteração. `composer audit --locked` não encontrou advisories; PHP
  8.5 não está instalado no Homelab e permanece sem validação.

## 0.1.0-dev.48 — alinhar currículo ao acesso contextual do curso — 2026-10-21

- Sala de curso e painel do estudante agora usam `take course` no curso pai e
  incluem somente aulas referenciadas publicadas. O `view` genérico de Lesson
  nega estudantes matriculados e não representa a autorização da rota nativa.
- Kernel com entidades reais confirma o caso: estudante pode entrar no curso,
  mas não tem `view` genérico da Lesson; aula publicada e conclusão aparecem,
  aula não publicada não entra no progresso. Unit: 51 testes / 281 assertions;
  Kernel: 20 testes / 631 assertions PASS. Revisão visual e smoke Runtime
  continuam pendentes.

## 0.1.0-dev.47 — testar integração real de QR com domínio — 2026-10-21

- Teste Unit usa o `DomainPurposeManager`, validador, registry e gerador reais
  para confirmar que o QR contém a URL absoluta de verificação no propósito
  `main`; também confirma falha fechada sem o domínio configurado.
- O teste usa URLGenerator controlado e não faz requisição de rede. Unit do
  módulo: 50 testes / 277 assertions PASS; 8 depreciações do PHPUnit.

## 0.1.0-dev.46 — cobrir resposta SVG do QR de certificado — 2026-10-21

- Kernel exercita a resposta positiva do endpoint QR com código emitido e
  verifica conteúdo SVG, tipo MIME, cache privado e headers de segurança.
- `CertificateLifecycleTest`: 3 testes / 94 assertions PASS; 38 depreciações
  upstream. A validação usa provedor controlado no teste; geração da imagem é
  coberta pela suíte própria de `aculta_qrcode`.
- Revalidação integral: Kernel `aculta_lms` 19 testes / 594 assertions PASS
  (90 depreciações upstream e uma de `webform_score`); Unit 48 / 260 PASS (8
  depreciações do PHPUnit).

## 0.1.0-dev.45 — validar carga horária mínima — 2026-10-21

- O campo de carga horária do curso exige no mínimo uma hora, alinhando a
  configuração editorial ao requisito da emissão do certificado.
- `CertificateLifecycleTest`: 3 testes / 85 assertions PASS, com 38
  depreciações upstream. Runtime de teste recebeu somente a atualização da
  definição do campo por importação parcial revisada; `min: 1` foi confirmado,
  zero valores permanecem e não há updates de banco pendentes. `drush cr`
  concluiu com o aviso conhecido do asset H5P, sem relação com esta mudança.

## 0.1.0-dev.44 — completar schema de usuário na fixture Kernel — 2026-10-21

- O Kernel de instalação agora instala o schema de User e persiste a conta
  administrativa usada no access do histórico, exercitando o `AccountUserLoader`
  no contexto correto. Kernel LMS completo: 19 testes / 584 assertions PASS,
  com 90 depreciações upstream; suíte Unit: 48 / 260 PASS, 8 depreciações.

## 0.1.0-dev.43 — limitar progresso a aulas acessíveis — 2026-10-21

- O painel do estudante calcula total e aulas concluídas apenas entre aulas que
  passam pelo `view` access do Drupal LMS para a conta; alterações de aula e dos
  respectivos resultados de access entram na metadata de cache.
- Unit do módulo: 48 testes / 260 assertions PASS (8 depreciações PHPUnit).
  PHPCS Drupal/DrupalPractice, gate do índice de estado e gate Drupal 11+
  (1010 checks) PASS. PHP 8.4.26; PHP 8.5 não validado.

## 0.1.0-dev.42 — alinhar carga horária ao schema Core — 2026-10-21

- A configuração exportada do campo `field_course_hours` agora registra os
  valores padrão que o Core materializa (`min`, `max`, `prefix`, `suffix`),
  evitando drift após a criação no Runtime.
- O campo e o widget foram aplicados por importação parcial revisada no Runtime;
  zero valores foram criados. `config:status` confirma storage e definição do
  campo sem diferenças; o display permanece diferente por configurações locais
  preservadas fora deste escopo.

## 0.1.0-dev.41 — cobrir acesso genérico ao snapshot de certificado — 2026-10-21

- Kernel confirma que até administradores não visualizam nem criam snapshots
  pela API genérica de entidade; a verificação pública por código opaco continua
  acessível. `CertificateLifecycleTest`: 3 testes / 84 assertions PASS no
  MariaDB dedicado, com 38 depreciações upstream.

## 0.1.0-dev.40 — declarar cache de permissão de turma em certificados — 2026-10-21

- Access de emissão e revogação de certificados agora declara `user.group_permissions`,
  além de contexto de usuário/permissões globais. Alterações de papel ou matrícula
  em Group passam a invalidar corretamente a autorização contextual.
- Unit focal cobre o contexto; suíte Unit: 47 testes / 219 assertions PASS (8
  depreciações PHPUnit); PHPCS Drupal/DrupalPractice e gates do índice de estado
  e Portal Drupal 11+ PASS. PHP 8.4.26; PHP 8.5 não validado.
- Runtime de teste `cursos` confirmado em `aculta_runtime`, módulo habilitado e
  sem updates pendentes. `drush cr` concluiu; emitiu o warning conhecido de
  assets do H5P, fora desta alteração.

## 0.1.0-dev.39 — tipar resolução de referências LMS — 2026-10-21

- O resolvedor do histórico usa `FieldableEntityInterface`, campos de
  referência e interfaces LMS importadas; reduz checagens dinâmicas e mantém o
  fechamento seguro para referências removidas.
- Unit: 46 testes / 215 assertions PASS (6 depreciações); Kernel lifecycle:
  9 testes / 369 assertions PASS (42 depreciações), no banco dedicado.
- PHPCS Drupal/DrupalPractice PASS nos arquivos novos de resolução e no
  `AttemptArchiveManager`; PHP 8.4.26, PHP 8.5 não validado.

## 0.1.0-dev.38 — resolver estado ativo da conta de sessão — 2026-10-21

- Access do histórico e dos downloads resolve `AccountInterface`/AccountProxy
  para User antes de checar status; cache depende do User e varia por conta.
- Unit: 46 testes / 215 assertions PASS (6 depreciações); Kernel lifecycle:
  9 testes / 369 assertions PASS (42 depreciações). PHP 8.4.26; PHP 8.5 não
  validado.
- Runtime de teste `cursos`: Drush confirmou `aculta_runtime`, `aculta_lms`
  habilitado e sem updates pendentes. `drush cr` concluiu e compilou os serviços;
  o loader resolveu os dois usuários de teste a partir de sessões `UserSession`.
  O rebuild registrou warning de diretório `assets://h5p`; H5P não foi alterado.
  Nenhuma resposta ou fixture do Runtime foi criada; download privado real segue
  sem smoke positivo.

## 0.1.0-dev.37 — negar downloads LMS a contas bloqueadas — 2026-10-21

- A rota privada de arquivo de resposta nega estudantes e educadores bloqueados
  antes de delegar ao access do LMS, incluindo conta cujo status muda durante
  sessão existente; cache varia por usuário e depende de User/Answer.
- Kernel focal de acesso a arquivo PASS (1 teste / 43 assertions; 42
  depreciações Core/contrib). Kernel lifecycle completo: 9 testes / 368
  assertions PASS, 42 depreciações. Unit completo: 42 testes / 197 assertions
  PASS, 5 depreciações. PHP 8.4.26; PHP 8.5 não validado.

## 0.1.0-dev.36 — negar histórico a contas bloqueadas — 2026-10-21

- Access do histórico de tentativas nega contas bloqueadas, incluindo estudante
  e educador, e varia cache por usuário com dependência da conta.
- Kernel prova revogação de membership e bloqueio/desbloqueio sem conceder
  acesso por resultado de access em cache. `AttemptArchiveLifecycleTest`: 9
  testes / 366 assertions PASS; 42 depreciações Core/contrib. PHP 8.4.26;
  PHP 8.5 não validado.

## 0.1.0-dev.35 — tolerar referências removidas no histórico de tentativas — 2026-10-21

- Arquivamento pré-exclusão e consulta de tentativas resolvem referências do
  LMS por campos. Se status, curso, aula ou atividade já foram removidos, o
  fluxo não tenta getters tipados nem gera 500; não cria snapshot incompleto.
- Unit: 42 testes / 197 assertions PASS; 5 depreciações do ecossistema. PHP
  local 8.4.26; PHP 8.5 não validado. `AttemptArchiveLifecycleTest`: 9 testes
  / 363 assertions PASS no `aculta_simpletest`, com 42 depreciações de Core e
  contrib.

## 0.1.0-dev.34 — tolerar referências de resposta removidas — 2026-10-21

- Formulário de prazo e aviso de feedback resolvem curso através dos campos de
  referência e encerram com segurança quando LessonStatus/CourseStatus já foi
  removido durante limpeza assíncrona; evitam erro 500 em submissão/edição.
- Unit: 41 testes / 192 assertions PASS; 5 depreciações do ecossistema. PHP
  local 8.4.26; PHP 8.5 não validado.

## 0.1.0-dev.33 — verificar formato e unicidade do código de certificado — 2026-10-21

- Kernel confirma códigos de 43 caracteres URL-safe, código novo na reemissão e
  rejeição de comprimentos fora do formato antes da consulta.
- `CertificateLifecycleTest`: 3 testes / 82 assertions PASS, 38 depreciações
  upstream. Unit: 39 testes / 185 assertions PASS. Kernel completo: 19 testes /
  573 assertions PASS, com 90 depreciações upstream.

## 0.1.0-dev.32 — preservar certificado com conta desativada — 2026-10-21

- Kernel confirma que bloquear a conta do estudante não revoga nem altera o
  snapshot público de certificado; a revogação explícita continua disponível.
- `CertificateLifecycleTest`: 3 testes / 77 assertions PASS; 38 depreciações
  upstream de Core/Group/LMS/Entity Print.

## 0.1.0-dev.31 — cobrir contas bloqueadas no painel da turma — 2026-10-21

- O Kernel do painel inclui uma matrícula de conta bloqueada e confirma que ela
  não compõe as métricas, mantendo a dependência de cache da conta para que uma
  reativação invalide o agregado.
- `ClassDashboardKernelTest`: 1 teste / 43 assertions PASS; 39 depreciações
  upstream de Core/Group/LMS/Views. Unit: 39 testes / 185 assertions PASS.

## 0.1.0-dev.30 — mesclar cache privado dos painéis LMS — 2026-10-21

- Painéis de estudante/educador e render de entidade do certificado agora
  mesclam tags/contextos sem apagar `max-age: 0` definido pelo apresentador.
- Unit: 39 testes / 185 assertions PASS; Kernel MariaDB: 19 testes / 564
  assertions PASS, com 90 depreciações de Core/contrib.
- Cobertura inclui `LearningDashboardControllerTest` (1/4), `ClassDashboardKernelTest`
  (1/41) e `CertificateLifecycleTest` (3/75).

## 0.1.0-dev.29 — negar entregas sem contexto LMS íntegro — 2026-10-21

- Durante a exclusão assíncrona de statuses pelo Drupal LMS, a resposta pode
  ficar temporariamente sem LessonStatus/CourseStatus/curso. O download privado
  agora nega esse estado sem chamar getters tipados sobre referências ausentes.
- Kernel usa deleção real de LessonStatus, confirma a janela antes do worker e
  garante negação variada por usuário: 1 teste / 39 assertions PASS; 42
  depreciações upstream. Suíte Kernel LMS: 19 testes / 559 assertions PASS,
  com 90 depreciações de Core/contrib. Unit LMS: 38 testes / 181 assertions
  PASS; 5 avisos de depreciação do PHPUnit.

## 0.1.0-dev.28 — preservar cache seguro na verificação pública — 2026-10-21

- Corrige a aplicação de metadata do certificado: agora mescla as tags da
  entidade ao render array, preservando `max-age: 0` da verificação pública.
- Kernel confirma a política sem cache, invalidação por tag, campos públicos e
  ausência de motivo de revogação/PDF quando revogado. `CertificateLifecycleTest`:
  3 testes / 73 assertions PASS; 38 depreciações upstream.

## 0.1.0-dev.27 — cobrir isolamento entre respostas de estudantes — 2026-10-21

- O Kernel confirma que outro estudante, ainda que matriculado no mesmo curso,
  não pode baixar o arquivo privado de uma resposta alheia. O dono matriculado,
  a revogação de matrícula e o educador autorizado seguem cobertos.
- `AttemptArchiveLifecycleTest`: 8 testes / 324 assertions PASS; 42
  depreciações upstream.
- Runtime de teste: a verificação pública de código inexistente retornava 500
  porque o container compilado ainda tinha a definição anterior do serviço,
  sem o novo argumento `lock`. `drush cr --uri=https://aculta.toca.net.br`
  reconstruiu o container; a mesma rota passou a responder 404. Não houve
  alteração de entidades ou configuração. Verificação de código válido, PDF,
  QR e revisão visual seguem DEFERRED. O rebuild registrou também um aviso
  independente do H5P sobre o destino `assets://`; não foi alterado nesta frente.

## 0.1.0-dev.26 — proteger fuso horário de prazos — 2026-10-21

- Adiciona cobertura Unit para garantir que o formulário de prazo interprete a
  data no fuso da conta do educador e use UTC quando a conta não define um.
- `ClassActivityDeadlineFormTest`: 2 testes / 8 assertions PASS. A conversão e
  o armazenamento em UTC seguem o contrato do DateTime do Core Drupal 11.

## 0.1.0-dev.25 — validar constraint pelo File API do Core — 2026-10-21

- Kernel passa arquivos raster válido e inválido pelo serviço `file.validator`
  real do Drupal, cobrindo o plugin constraint integrado ao upload gerenciado.
- `AttemptArchiveLifecycleTest`: 8 testes / 322 assertions PASS; 42
  depreciações upstream.

## 0.1.0-dev.24 — verificar integração do validador de anexos — 2026-10-21

- Testa que o hook OOP é descoberto pelo Kernel e que acrescenta o constraint
  `AcultaLmsSubmissionImage` ao `managed_file` do LMS sem remover os validadores
  próprios da atividade. Unit: 2 testes / 4 assertions; Kernel de instalação:
  4 testes / 35 assertions PASS.

## 0.1.0-dev.23 — isolar métricas agregadas da turma — 2026-10-21

- Exclui educadores com `grade students` e administradores das contagens de
  estudantes; o estado de alguém fora da turma também não entra nas métricas.
- Acrescenta dependências de cache para cada membership e conta consultada, além
  das listas de roles Group/Drupal, para invalidar dados quando roles ou
  memberships mudarem.
- Kernel real de Group/LMS cobre membro, educador, administrador e outsider:
  1 teste / 38 assertions PASS; 39 depreciações vêm do Core/Group upstream.

## 0.1.0-dev.22 — serializar reemissões de certificado — 2026-10-21

- Adquire lock do Core por UUID do status antes de revogar uma emissão anterior
  e criar a próxima versão; uma emissão concorrente recebe conflito e não grava
  certificado. O lock é liberado também em falhas transacionais.
- Kernel cobre a recusa quando o lock está ocupado; suíte de certificados:
  3 testes / 64 assertions PASS, com 38 depreciações upstream.

## 0.1.0-dev.21 — Kernel cobre dependência de imagens — 2026-10-21

- Inclui `aculta_image` e o schema de `entity_usage` no Kernel que cobre anexos
  privados, mantendo o lifecycle de limpeza habilitado durante os cenários.
- Kernel `AttemptArchiveLifecycleTest`: 7 testes / 289 assertions PASS; 42
  depreciações de Core/contrib upstream.

## 0.1.0-dev.20 — validar anexos raster de respostas — 2026-10-21

- Adiciona cobertura Unit para imagem válida, imagem inválida, limite de 10 MB e
  preservação da validação nativa de arquivos não raster.
- A política continua centralizada em `aculta_image`; o LMS mantém anexos em
  storage privado e o download revalida resposta e matrícula.

## Decisão F13 — prazo por atividade na turma — 2026-10-21

- O responsável confirmou o prazo por atividade na turma. A implementação já
  armazena o prazo na relação Group da turma com a atividade, permitindo datas
  distintas quando a mesma atividade é reutilizada.

## Decisão F13 — avisos de mudanças acionáveis — 2026-10-21

- O responsável escolheu notificar eventos acionáveis já persistidos: liberação
  de conteúdo, novos envios/retornos publicados e criação ou alteração de prazo
  por atividade/turma. Edições editoriais sem mudança acionável não geram aviso.
- Mantém o fluxo existente de resumo diário opt-in; a janela atual de 24 horas
  para lembrete de prazo aguarda confirmação do intervalo.

## Registro documental F15 — estado das integrações — 2026-10-21

- Corrige o estado do adapter `aculta_forum_lms`: permissões e módulo estão
  ativos no Runtime de teste, mas ainda não há tópicos/Comments LMS para
  homologação HTTP ou visual. Discussão vinculada a atividade continua ausente.
- Inventário atual do adapter: Unit 18/190 assertions e Kernel MariaDB 2/127
  assertions PASS; 43 depreciações upstream no Kernel. Esses testes cobrem
  contexto Group curso/turma e não provam o vínculo com atividade.

## 0.1.0-dev.19 — autorizar aprendizagem pelo curso pai — 2026-10-21

- Usa `take course` no curso pai junto da membership ativa na turma para
  resolver prazos de estudante. Group `lms_classes` concede essa permissão no
  curso, não no grupo de turma; Kernel cobre membro e outsider.

## 0.1.0-dev.18 — versão de prazo acompanha cada alteração — 2026-10-21

- A versão opaca do evento inclui UUID, prazo e horário de alteração da relação
  Group, impedindo reutilizar um aviso antigo quando o educador volta a uma data
  anterior. Teste Unit cobre estados iguais e alterações/reversões.
- Unit LMS/adapter: 47 testes / 210 assertions PASS; Kernel `aculta_lms`: 5
  testes / 67 assertions PASS. Runtime e verificação de entrega permanecem
  DEFERRED.

## 0.1.0-dev.17 — prazos de atividade por turma — 2026-10-21

- Educadores com `grade students` no curso e administradores podem definir,
  alterar e remover o prazo de uma atividade em cada turma. O dado fica na
  relação Group turma↔atividade; não altera a entidade LMS nem outros cursos.
- O estudante vê o prazo no fuso de sua conta; o painel do educador informa os
  prazos da turma, e a data usa o fuso de quem a definiu.
- Publica evento somente ao criar ou mudar a data. Unit LMS/adapter: 43 testes /
  197 assertions PASS; Kernel de instalação: 4 testes / 34 assertions e Kernel
  Group: 1 teste / 33 assertions PASS. O teste com Group real comprova que uma
  atividade recebe prazos distintos em duas turmas sem ser alterada. Lembrete
  antecipado depende da decisão de intervalo; Kernel de aviso Message, revisão
  visual e Runtime permanecem DEFERRED.

## 0.1.0-dev.16 — avisos de conteúdo liberado e retorno — 2026-10-21

- Publica eventos após publicar curso, aula ou atividade, incluindo atividades
  adicionadas a uma aula publicada; payload contém somente tipo, UUID e versão
  opaca.
- Publica evento para comentário de resposta quando educador com permissão
  atual publica ou altera o feedback; comentário do estudante não gera aviso.
- Unit: 29 testes / 140 assertions PASS. Integração com Message real, revisão
  visual e Runtime permanecem DEFERRED.

## 0.1.0-dev.15 — evento de resposta pendente — 2026-10-20

- Publica evento mínimo depois de criar/alterar resposta discursiva ou anexo
  ainda pendente de correção, sem transmitir conteúdo da resposta.
- Preserva o evento de nota publicada para o estudante e republicações.
- Unit cobre resposta pendente sem dados no payload. Runtime: DEFERRED.

## 0.1.0-dev.14 — publicação de avaliações — 2026-10-20

- Publica evento após uma resposta passar a avaliada e a cada nova gravação de
  avaliação publicada, inclusive quando o educador reaplica a mesma nota.
- O evento carrega somente UUID da resposta, identificador do estudante e uma
  versão opaca derivada do estado persistido; conteúdo e nota não são expostos.
- Teste Unit cobre primeira avaliação, republicação, mudança de nota e ausência
  de evento quando não houve mudança. Runtime/visual: DEFERRED.

## 0.1.0-dev.13 — eventos educacionais para avisos — 2026-10-20

- Publica eventos após salvar uma matrícula em turma e após emitir certificado;
  o adapter `aculta_lms_messenger` converte ambos em avisos pessoais no painel
  multidomínio existente e em e-mail transacional.
- Publica conclusão aprovada por evento para permitir o próximo incremento da
  F13; a distribuição diária opt-in e os demais eventos ainda não estão ligados.
- Testes isolados e validação do Runtime permanecem pendentes até execução desta
  entrega; Runtime: DEFERRED.


## 0.1.0-dev.12 — aba do painel da turma no contexto de Group — 2026-10-20

- Corrige a aba local do painel do educador para pertencer à rota canônica de
  Group; antes ela usava a tela de estudantes do curso como base e herdava o
  curso pai no parâmetro `group`, em vez da turma selecionada.
- A rota do painel continua validando que o Group é turma LMS vinculada a um
  curso e que a conta tem `grade students` nesse curso ou administra o LMS.
- Revisão de código identificou a origem do parâmetro herdado; a alteração de
  base route requer smoke visual em turma real, ainda DEFERRED no Runtime.

## 0.1.0-dev.11 — painéis de aprendizagem F12 (implementação inicial) — 2026-10-20

- Adiciona a seção autenticada `/minha-aprendizagem` à navegação de conta, com
  cursos próprios, progresso por aulas concluídas, avaliações pendentes e
  certificados emitidos.
- Adiciona painel administrativo por turma com contagens agregadas de
  matrícula, início, andamento, avaliação pendente, aprovação e reprovação.
  A tela não carrega nem apresenta nomes ou respostas de estudantes.
- Os dados continuam em Group/LMS e na entidade de certificado. As rotas são
  sem cache compartilhado e os dados agregados exigem `grade students` no curso
  associado à turma ou administração LMS.
- Unit dedicada dos painéis e indisponibilidade do PDF: 4 testes / 18 assertions;
  cobre o escopo próprio
  da conta e falha fechada para turma órfã/sem curso. Os Kernel de instalação e
  certificados também passam (6 testes / 76 assertions).
- Unit total do módulo: 20 testes / 106 assertions. O gerador Entity Print é
  opcional no bootstrap; sem ele a rota de PDF responde indisponibilidade
  controlada em vez de impedir a inicialização do Drupal.
- RUNTIME STATUS: DEFERRED. Testes reais com fixtures LMS/Group, revisão visual
  e smoke no navegador/runtime ainda pendentes; validação ocorreu em PHP 8.4.26,
  não no alvo PHP 8.5.
- Integra a verificação pública e o PDF ao gerador `aculta_qrcode`: URL absoluta
  resolvida pelo purpose `main`, SVG servido em endpoint sem armazenamento e QR
  PNG incorporado ao PDF com remoto/PHP embutido desligados.

## 0.1.0-dev.10 — certificados imutáveis, emissão e verificação (parcial) — 2026-10-20

- Adiciona armazenamento de snapshots, emissão somente após conclusão aprovada,
  reemissão encadeada, revogação e página/PDF público por código opaco.
- Educador contextual (`grade students`) e administrador podem emitir; a
  elegibilidade decorre do status LMS aprovado, sem exigir membership atual.
- Entity Print 2.19.0 e Dompdf 3.1.6 foram instalados; PDF desliga recursos
  remotos e PHP embutido, renderiza apenas o snapshot autenticado pelo código e
  retorna sem cache. A emissão é transacional; os campos de snapshot são
  imutáveis e a tela de educador lista emissões e permite revogar com motivo.
  Config Sync adiciona carga horária inteira ao curso.
- Decisões de produto e retenção após exclusão de conta registradas no contrato
  e inventário LGPD. RUNTIME STATUS: DEFERRED.
- Kernel isolado: 6 testes / 76 assertions; valida elegibilidade, autorização,
  privacidade do registro, snapshot imutável após exclusão da conta, reemissão,
  revogação, HTML escapado e cabeçalhos privados do PDF. Unit: 16 testes / 88
  assertions. Gates Drupal 11+ (1007 checks),
  índice de status, slugs, imagens e Composer audit: PASS. Entity Print 2.19.0
  ainda emite três deprecações Drupal 11.2/11.3 no código upstream; nenhuma
  alteração foi feita em contrib.
- F11 ainda não concluída: QR depende da API de `aculta_qrcode` da outra frente;
  runtime/configuração, smoke acessível e revisão visual estão pendentes.

## 0.1.0-dev.9 — validação ACULTA de imagens em entregas — 2026-10-20

- O formulário `file_upload` do LMS acrescenta constraint do Core que detecta
  anexos raster e aplica os 10 MB e a inspeção de conteúdo/MIME de
  `aculta_image`. Extensões não-imagem continuam sob a configuração da atividade;
  os arquivos permanecem privados e o download continua sujeito à resposta e à
  matrícula atual.
- Sintaxe e container/gates ainda não foram validados; Kernel, upload real,
  revogação e inspeção visual permanecem pendentes. RUNTIME STATUS: DEFERRED.

## 0.1.0-dev.8 — roles Group de discussão — 2026-10-20

- Config Sync acrescenta permission Group Node de tópicos aos roles de curso
  Educador/estudante/criador e estudante de turma, condicionados à relação
  instalada pelo adapter `aculta_forum_lms`. Sem ativação ou import Runtime.
- Kernel usa os mesmos YAMLs e verifica acesso contextual, moderação, Comment
  publicado e negação de pessoa externa. RUNTIME STATUS: DEFERRED.

## 0.1.0-dev.7 — permissão de tópico LMS herdada na turma — 2026-10-20

- `lms_classes.settings` mapeia `grade students` do curso para acesso Group
  Node aos tópicos de discussão da turma. O `ClassPermissionCalculator` real
  foi validado em Kernel: Educador do curso permitido; pessoa externa, inclusive
  com papel global `lms_teacher`, sem acesso. 1 teste / 24 assertions PASS;
  37 depreciações Core/contrib.
- A configuração não ativa o adapter nem cria tópicos/Comments. Runtime
  DEFERRED; mudança de Config Sync ainda não foi importada.

## 0.1.0-dev.6 — review de texto livre sem classe de design — 2026-10-19

- `FreeTextReviewFeedback` marca as seções e frases do retorno com `data-portal-ui` (`feedback-matched`, `feedback-unmatched`, `phrase-match`, `phrase-mismatch`). As classes de estado do LMS (`correct-answer`, `wrong-answer`) continuam, vindas da constante do contrib.
- RUNTIME STATUS: DEFERRED.

## Integrações de conteúdo em andamento — 2026-10-19

- `aculta_lms` declara as dependências `aculta_image` e
  `aculta_pdf_viewer_group`: imagens de capa seguem a política ACULTA e Media
  `course_document` preserva acesso de turma pelo Group e leitura pelo viewer
  PDF compartilhado.
- Contrato expandido para submissões com imagem e discussão ligada a curso ou
  atividade. Upload de imagem de entrega e vínculo de discussão com
  `aculta_forum` permanecem objetivos a implementar/homologar; o módulo de
  fórum ainda não tem API executável. Nenhuma integração cria storage paralelo.
- `aculta_lms_h5p` já encaminha imagens do editor/assets via `aculta_image`;
  PDFs complementares continuam sob Media/viewer e Group, enquanto assets H5P
  são servidos pelo controlador H5P protegido. Não houve importação de
  configuração nem mutação do Runtime neste registro.

## Correção da sala de curso usada na homologação F10 — 2026-10-19

- Corrige a resolução da classe `CourseEnrollmentPurchaseForm` chamada pela sala do curso; o namespace incorreto gerava 500 para visitantes sem acesso `take`.
- Unit cobre a montagem do formulário de inscrição pela sala. Runtime `cursos`: estudante matriculada abriu a sala e reproduziu H5P; visitante anônimo foi bloqueado na rota de arquivo privado.
- RUNTIME STATUS: HOMOLOGADO EM TESTE; produção não foi alterada.

## Continuação F7 — participação sem confirmação de e-mail

- Corrige a divergência entre a matrícula em cursos, que não exige confirmação de e-mail, e as rotas de aula/resposta que ainda exigiam a marca global `email_confirmed`.
- As rotas continuam exigindo acesso `take` atual do Group e aceite vigente dos termos. Não amplia acesso de pessoas sem matrícula.
- Testes Unit do `ProtectedRouteAccessCheck`: estudante não confirmado com `take` permitido passa; pessoa sem `take` continua bloqueada. Runtime `cursos`: PASS com usuário temporário sem confirmação de e-mail e matrícula Group; a atividade de revisão renderizou. Usuário e membership removidos via Entity API; nenhum conteúdo do curso foi alterado.
- RUNTIME STATUS: código aplicado por cache rebuild e smoke HTTP aprovado no Runtime de teste `aculta_runtime`.

## Não lançado — F10: auditoria H5P

- A inspeção do endpoint de upload H5P `2.0.0-beta1` confirmou que a permissão de
  tipos restritos não é verificada na ingestão de pacote. H5P permanece
  desativado até correção upstream ou patch Composer acompanhado de teste de
  regressão; nenhum estado do Runtime foi alterado.

## Não lançado — F8: avaliações e feedback (implementação inicial)

- `aculta_lms` depende dos submódulos contrib LMS de plugins de resposta e comentários, sem novo pacote Composer.
- A navegação administrativa lateral recebe um ícone SVG próprio de livro aberto pelo Icon API do Drupal Navigation; a barra Toolbar clássica mantém seu fallback customizado. Core e contrib não foram editados.
- O painel `/painel-administrativo/lms` e suas subpáginas usam traduções pt-BR para menus, descrições, cursos, turmas, aulas, atividades e configurações; sem alterar rotas ou títulos de conteúdo.
- F9 adiciona Webform Score e LMS File Upload via Composer; o download privado em `/resposta/{lms_answer}/baixar-arquivo` exige acesso atual ao curso para o autor e mantém a permissão de avaliação para educadores.
- Kernel F9 verifica autoria em uma submissão Webform persistida: proprietário com permissão pode visualizar a nota, outra conta não pode e edição direta é negada; Runtime ainda não contém fixture educacional para smoke HTTP/browser.
- F10 adiciona `drupal/h5p` 2.0.0-beta1 e dependências ao Composer para auditoria/avaliação de integração. Módulos H5P continuam desativados no Runtime; não foram instaladas bibliotecas de conteúdo nem criados registros. Ativação aguarda correção verificada do bypass de bibliotecas restritas, auditoria de PII/Hub, CSP e isolamento.
- O plugin `free_text_review_feedback`, adaptado do Drupal LMS 1.2.4 sob GPL-2.0-or-later, oferece feedback formativo por frases e mantém a correção final exclusivamente manual pelo educador.
- O Drupal LMS já fornece múltipla escolha/seleção múltipla, verdadeiro/falso e completar lacunas, com variantes de feedback; o LMS mantém resposta, nota e progresso.
- `lms_answer_comments` e Core Comment fornecem diálogo ligado à resposta e avaliação manual, sem campo ou gradebook paralelo.
- Não mexe diretamente em contrib/Core e não altera respostas existentes.
- `AttemptArchive` preserva cópias das versões anteriores antes de edição, reavaliação ou reinício de resposta e mantém a conversa da resposta; histórico exige membership/acesso atual e é removido com o progresso, conta ou curso.
- Verificação F8: Unit e Kernel do `aculta_lms` passaram (15 testes, 259 assertions); `lms_answer_plugins` contrib passou (2 testes, 4 assertions). Kernel cobre entidades reais de Answer/Comment, comentários publicados, edição, reavaliação, exclusão e limpeza por progresso/conta/curso; valida isolamento por turma, permissões de aluno/educador, saída como texto e cache por usuário/permissões. Gates Portal (950 checks), imagem, slugs e PHP lint passaram. Executado com PHP 8.4.26; PHP 8.5 ainda não foi validado.
- Os Kernel tests reportam 34 depreciações de Group/LMS/Views contrib e `EntityBase::__get('original')` disparado durante persistência do ecossistema; nenhum arquivo contrib/Core foi alterado. Nenhum histórico preexistente foi migrado.
- Runtime de teste: Drush confirmou `http://cursos.aculta.toca.net.br`/`aculta_runtime`; aplicado apenas o update 10001 após backup privado (modo 0600). Smoke sintético PASS: persistência, estudante sem matrícula negado, educador `lms_teacher` com leitura, edição negada e registro removido. As contas `teste.estudante` e `teste.educador` foram criadas; credenciais apenas em `secrets/lms-test-accounts.env` local (ignorado pelo Git, modo 0600). Autenticação das duas contas validada. RUNTIME STATUS: UPDATE APLICADO; após esta correção foi reconstruído o cache no Runtime `aculta_runtime`. Testes de ciclo de vida e acesso reais passaram em Kernel no MariaDB dedicado `aculta_simpletest`; smoke visual por navegador segue DEFERRED. Matrículas e pedidos continuam fora do escopo F8.

## Não lançado — F7: matrícula sempre associada a turma

- Config Sync habilita `lms_classes`, desativa criação automática de turma e configura grants de educador.
- `EnrollmentFormHooks` valida a turma das memberships da equipe contra as turmas do curso original, bloqueia autoinscrição direta e exige que a turma pertença ao curso. Educadores e administradores podem manter sua membership no nível do curso.
- Educadores podem remover pela interface Group as matrículas manuais. O campo oculto `field_course_order` guarda a origem comercial; a exclusão de membership ligada a pedido de curso é negada enquanto houver pagamento ativo, inclusive reembolso parcial e pedido gratuito concluído. Só pedidos cancelados sem pagamento ou com todos os pagamentos estornados liberam a exclusão.
- O curso pode referenciar produto Commerce próprio. A página pública oferece turma/preço e envia item `curso` com turma ao checkout dedicado; cursos de preço zero também passam pelo pedido. O fluxo exige conta, permite cadastro e dispensa confirmação de e-mail no checkout de curso, mantendo a política geral de termos.
- `CourseOrderEnrollmentManager` concede membership Group após pedido de curso concluído e pago; reembolso parcial conserva acesso e estorno integral remove somente a origem do pedido, preservando memberships manuais e compras válidas. A autoinscrição direta do LMS é bloqueada.
- Render do formulário varia por usuário/permissões e Domain, e invalida conforme curso, turmas, produto e variações referenciados.
- `EnrollmentRoleHooks` em `aculta_user` concede `estudante` para membership de turma e preserva a exclusão de papéis de educador/admin.
- Matrícula por checkout e por equipe foram escolhidas; sem confirmação de e-mail/termos para matricular, com aviso por e-mail (entrega F13). Sem turma padrão.
- RUNTIME STATUS: CONFIG APLICADA no Runtime de teste em 2026-10-18. Após backup, habilitei `aculta_lms`/`lms_classes`, migrei o setting de doações e importei parcialmente 29 objetos F7; nenhum objeto ausente foi removido. O único curso existente foi preservado; não criei turma, produto ou pedido. `create_default_class` está `false`. Smoke de matrícula, turma cruzada, compra paga/gratuita, reembolso e isolamento A/B segue DEFERRED. Restam 284 diferenças globais; não houve importação global. Backup privado: `/home/aculta/private/aculta/config-reconcile-20261018/aculta_runtime-before-f7.sql.gz`.

## 0.1.0-dev.2 — F6: página de curso e sala (implementação inicial)

- Acrescentado `CourseRoomBuilder` e campo extra configurável na página canônica
  de curso; o índice usa conteúdo e access do Drupal LMS/Group e liga para a
  rota nativa da primeira atividade de cada lição.
- A seção não expõe lições quando a conta não tem `take course`; resultados de
  access, curso e lições alimentam a cacheabilidade.
- Sem alteração de entidades LMS, matrícula, status, resposta ou storage.
- RUNTIME STATUS: DEFERRED; smoke test com membro, não membro e visitante
  pendente.

## 0.1.0-dev.1 — Fundação F1

Submódulo opcional, publisher DI e evento imutável de conclusão aprovada do
Drupal LMS. Testes Unit (2/2) e Kernel de instalação (1/1) PASS. Nenhuma
persistência, rota, configuração ou integração Messenger ativa é criada.
RUNTIME STATUS: DEFERRED; o submódulo não foi habilitado no Runtime.
