# Inventário de dependência do Drupal LMS contrib

**Data:** 2026-11-01
**Branch:** `dev` (revisado após B1/B2/B3-A em 2026-11-01; `d419d9bb6` é somente a referência histórica das contagens)
**Natureza:** inventário técnico vivo da independência arquitetural. Registra código implementado e gates ainda pendentes; não autoriza migração/exclusão de dados, alteração de configuração ativa do Runtime nem remoção da dependência Composer.
**Escopo:** dependência funcional e arquitetural do ACULTA em relação ao módulo contrib `drupal/lms`. O ACULTA continua sendo Drupal; o objetivo é que o domínio educacional e suas experiências tenham contratos próprios e um adaptador substituível.

## 1. Resumo executivo

A dependência do ACULTA em relação ao Drupal LMS **não é só de tela nem só do módulo `aculta_lms`**. Ela está na fundação do produto:

- `aculta_portal` já não declara `lms:lms` nem importa classes `Drupal\lms` em `src/`.
- O serviço de cursos da Conta usa `AccountCoursesReaderInterface`; a leitura Group/LMS e os processadores de slug foram movidos para `aculta_lms`.
- `aculta_lms` declara dependência de `lms`, `lms_classes`, `lms_answer_plugins`, `lms_answer_comments` e `lms_file_upload`, além de `webform_score` e `entity_print`.
- Cursos, turmas e memberships vivem em Group; lições, atividades, respostas, tentativas, status e notas vivem em entidades do contrib.
- Views, rotas, permissões, plugins, tema e scripts assumem IDs e formatos do contrib.

Por isso, remover apenas `drupal/lms` do Composer não torna o ACULTA independente. A saída recomendada continua sendo a do estudo de independência arquitetural: contrato ACULTA → adaptador LMS durante a transição → domínio próprio por curso, com ownership único e rollback ensaiado.

**Entregas registradas nesta revisão:** jornada, sala e painel do estudante consomem contratos/DTOs ACULTA; o Studio é dono da apresentação Ganjalab; `aculta_portal` deixou de depender de `lms:lms`, e cursos da Conta/slugs foram isolados atrás de contratos/adaptadores. `aculta_lms` ainda depende do Drupal LMS e Group para a fonte de verdade e as escritas. Nenhuma entidade, configuração ativa ou dado foi migrado. O lint, testes e instalação sem contrib precisam ser confirmados localmente.

## 2. Versões e inventário de plataforma

| Item | Versão/estado confirmado |
| --- | --- |
| Drupal core | `11.4.10` (`composer.lock`) |
| `drupal/lms` | `1.2.4`; `composer.json` exige `^1.2` |
| `drupal/group` | `3.3.5`; `composer.json` exige `^3.3` |
| `drupal/lms_file_upload` | `1.0.2`; exige `^1.0` |
| `drupal/webform_score` | `2.4.0`; exige `^2.4` |
| `drupal/entity_print` | `2.19.0`; exige `^2.19` |
| PHP do CLI de testes | `8.4.27` nesta execução; o Runtime documentado em `docs/modules/LMS-GROUP.md` reporta `8.4.26`. PHP 8.5 segue não comprovado. |
| Módulos LMS no Config Sync | `lms`, `lms_classes`, `lms_answer_plugins`, `lms_answer_comments`, `lms_file_upload`, `aculta_lms`, `aculta_lms_h5p`, `aculta_forum_lms`, `aculta_lms_resources`, `aculta_lms_agenda`, `webform_score`, `entity_print` aparecem em `config/sync/core.extension.yml`. |
| Configuração LMS versionada | `lms.settings`, `lms_classes.settings`, `group.type.lms_course`, `group.type.lms_class`, `lms.lms_activity_type.true_false`, `lms.lms_activity_type.no_answer` |
| Views exportadas ligadas ao LMS | `courses_catalog`, `courses`, `courses_admin`, `classes_admin`, `lms_course_students`, `activities_selection`, `lessons_selection`, `aculta_forum_lms_admin_comments` |

Dependências declaradas em `info.yml`:

- `aculta_portal.info.yml`: não declara `lms:lms`; os acoplamentos funcionais de cursos foram movidos para o adaptador `aculta_lms`.
- `aculta_lms.info.yml`: `lms:lms`, `lms:lms_classes`, `lms:lms_answer_plugins`, `lms:lms_answer_comments`, `lms_file_upload:lms_file_upload`, `webform_score:webform_score`, `entity_print:entity_print`.
- `aculta_lms_h5p`, `aculta_forum_lms` e `aculta_lms_resources` dependem de `aculta_lms` e, por consequência, do contrib.

## 3. Mapa por camada

### 3.1 Composer e módulos

- Pacote `drupal/lms` e seus submódulos distribuídos no mesmo repositório (`lms_classes`, `lms_answer_plugins`, `lms_answer_comments`).
- Pacotes independentes `drupal/lms_file_upload`, `drupal/webform_score` e `drupal/entity_print`, todos usados pela integração educacional.
- O CMS não tem módulo ACULTA que hoje substitua essas dependências; `aculta_lms` é integração, não domínio próprio.

### 3.2 Serviços e injeção de dependência

- `@lms.training_manager` não é mais injetado por `aculta_portal.account_courses`; o Portal consome `AccountCoursesReaderInterface` como provider opcional. `aculta_lms.learning_dashboard` também consome diretamente essa interface, sem passar pelo facade do Portal.
- `Drupal\lms\TrainingManager` é usado diretamente em `LearningDashboardManager`, `AccountCoursesReader`, `CourseLinkHooks` e outros consumers do adaptador educacional.
- `Drupal\lms\Entity\Bundle\CourseInterface` é o tipo aceito em builders, hooks, controllers, formulários, acessos e plugins ACULTA.
- Existem contratos ACULTA neutros de leitura para jornada, resumo do estudante e cursos da seção Conta. O provider atual ainda usa Group/Drupal LMS; outros consumidores e escrita permanecem acoplados.

### 3.3 Dados e entidades

| Dado | Fonte atual | Observação |
| --- | --- | --- |
| Curso, turma, membership e papéis | Group (`lms_course`, `lms_class`) | Group é a fonte de matrícula e de grants; o bundle do curso pertence ao LMS. |
| Lição e atividade | `lms_lesson`, `lms_activity` | Estrutura e ordenação pertencem ao contrib. |
| Tentativa, resposta, status e nota | `lms_course_status`, `lms_lesson_status`, `lms_answer` | `TrainingManager` mantém leitura e escrita. |
| Comentário de avaliação | `lms_answer_comment` | Integra o feedback do educador; o comentário público de fórum é outro fluxo. |
| Progresso | Derivado de status LMS no `LearningDashboardManager` | Percentual calculado no ACULTA a partir de lições publicadas e status. |
| Certificado | Entidade própria `aculta_lms:Certificate` | Lê o status final do LMS para elegibilidade; não duplica conclusão. |
| H5P | `h5p_content` + vínculo por atividade LMS | `aculta_lms_h5p` é fork ACULTA, mas depende da atividade LMS. |
| Discussão pedagógica | Node/Comment do `aculta_forum_lms` | Referencia atividade LMS e revalida Group. |
| Arquivos | Media/File + `lms_file_upload` | Upload privado e revogação contam com o contrib. |

### 3.4 Rotas, Views e apresentação

- Rotas do contrib usadas: `lms.course.start`, `lms.group.answer_form`, `lms.group.self_results`, `lms.answer.details`, `entity.lms_answer.edit_form`, `lms.course.reset_test` e outras.
- `aculta_lms` adapta rotas LMS para slugs em português (`LmsFriendlyRouteProcessor`, `LmsFriendlySlugConverter`, `LmsFriendlySlugs` e `LmsFriendlyRouteSubscriber`); os IDs de serviços e parâmetros antigos foram preservados.
- Views LMS fornecem catálogo, seleção de atividades/lições, turmas e status.
- O tema ACULTA420 negocia o SDC `lms:course_card` por substituição compatível (`aculta420:course_card`); o tema não importa classes PHP do contrib.
- `aculta_studio` compõe dashboard, jornada e sala; antes desta fatia, `CourseJourneyComposer` importava `CourseInterface` diretamente.

### 3.5 Scripts e conteúdo

- `scripts/content/load-course-introducao-antiproibicionismo.php` é a fonte editorial canônica do curso piloto e escreve entidades LMS/Group.
- `scripts/validate-public-slugs.php` e `scripts/validate-admin-cleanup.php` verificam rotas/limpeza ligadas ao LMS.

### 3.6 Testes

- `aculta_lms` mantém Unit, Kernel e um harness de dependências opcionais.
- `aculta_lms_h5p` e `aculta_forum_lms` cobrem integração com entidades LMS.
- O harness `AcultaLmsDependencyHarnessTrait` registra serviços LMS falsos em Kernel; a suíte de instalação já falhava antes desta entrega por autowiring de `CourseLinkHooks`/`TrainingManager` (ver §11).

## 4. Matriz de dependências

| Função | Consumo atual do Drupal LMS contrib | Contrato ACULTA existente | Plano de desacoplamento | Risco/migração | Estado nesta entrega |
| --- | --- | --- | --- | --- | --- |
| Catálogo de cursos | Views `courses_catalog`, `courses`, `courses_admin` + row plugin LMS | Nenhum contrato de leitura de catálogo | Criar projeção ACULTA de catálogo sobre a fonte atual; manter View como adaptador inicial | Médio: access, cache e negociação SDC precisam continuar no LMS/Views | Pendente |
| Dashboard do estudante | `LearningDashboardManager` lê Group/LMS e certificados | `StudentLearningSummaryReaderInterface` + DTOs `StudentLearningSummary`, `StudentLearningCourse`, `StudentLearningCertificate` | O adaptador LMS fornece dados; o Studio compõe a interface | Médio: certificados e retomada cruzam owners diferentes | **Contrato de dados implementado; validação local pendente** |
| Jornada do curso | `CourseInterface`, `TrainingManager`, `LessonStatusInterface` em `LearningDashboardManager` e `CourseJourneyComposer` | **`CourseJourneyReaderInterface` + `CourseJourney`/`CourseJourneyLesson` (novo)** | Adaptador LMS implementa o contrato; o Studio consome apenas o DTO | Baixo nesta fatia: nenhuma escrita, nenhum dado movido | **Encapsulado** |
| Sala de aula | Rota nativa `lms.group.answer_form`, Form API e entidades LMS | O mesmo contrato fornece aula, posição e destino autorizado | Envolver a rota nativa durante a transição; substituir a rota quando houver provedor próprio | Médio: submissão e access continuam no LMS | **Encapsulado parcialmente** (shell e acesso) |
| Página canônica do curso | Extra field em `entity_view` de `lms_course` | Contrato de jornada | Builder ACULTA recebe o ID e consulta o contrato | Baixo | **Adaptador na borda** |
| Progresso e conclusão | `lms_course_status`, `lms_lesson_status`, `TrainingManager` | Cálculo ACULTA sobre status LMS; sem fonte própria | Migrar fatos de tentativa/conclusão para domínio ACULTA em fase posterior | Alto: retenção, notas e certificados | Pendente |
| Tentativas, respostas e notas | `lms_answer`, plugins `lms_answer_plugins`, `lms_answer_comments` | Nenhum contrato de avaliação próprio | Manter temporariamente; migrar por curso após inventário de volume e tipos | Alto: histórico e autoria | Pendente |
| Turmas e memberships | Group `lms_class` + plugin `lms_classes` | Group é infraestrutura aproveitável; não é contrato ACULTA | Manter Group, encapsular leitura de turma/curso em contrato ACULTA | Médio: grants herdados e checkout | Pendente |
| Autorização de educador/estudante | `takeAccess()`, `grade students`, papéis Group e `lms_teacher` | Access checks ACULTA existentes para certificado, prazo e roster | Centralizar em contrato de acesso por curso/aula/atividade | Médio: não aceitar ocultação de botão como controle | Pendente |
| Criação/edição de curso e aula | Formulários nativos Group/LMS | Nenhum; o Studio apenas escolhe o formulário nativo | Criar autoria ACULTA sobre domínio próprio; manter formulário nativo como ponte | Alto: versionamento e publicação | Pendente |
| H5P | `aculta_lms_h5p` importa `ActivityInterface`, `CourseInterface` e usa Group | Fork ACULTA, mas vínculo por atividade LMS | Adapter H5P ACULTA com contrato de atividade e tentativa | Alto: bibliotecas e segurança | Pendente |
| Fórum pedagógico | `aculta_forum_lms` importa entidades LMS e relação Group | Node/Comment são owners ACULTA; o vínculo é LMS | Substituir o vínculo `lms_activity` por referência de atividade ACULTA | Médio: isolamento entre turmas | Pendente |
| Recursos do curso | `aculta_lms_resources` importa `CourseInterface` e lê Group | Content Index ACULTA + Group | Trocar o tipo por contrato de curso ACULTA | Baixo/médio | Pendente |
| Agenda do curso | `aculta_lms_agenda` importa entidades LMS | Nenhum | Ler por contrato de curso/atividade | Baixo | Pendente |
| Conquistas | `aculta_game` consome `CourseCompletedEvent`; provider `aculta_lms` ainda lê `CourseStatusInterface` | `aculta_learning_contracts\\Event\\CourseCompletedEvent` | Validar integração em Runtime; manter o LMS isolado no provider | Baixo/médio | Evento neutro implementado; validação pendente |
| Avisos educacionais | `aculta_messenger` consome `LearningEligibility` do `aculta_lms` | Contrato público `MessengerNoticeEvent`; elegibilidade em `aculta_lms` | Manter elegibilidade no adaptador até o domínio próprio existir | Baixo | Pendente |
| Certificados | Entidade própria lê status final LMS | `CourseCertificateManagerInterface` | Trocar a fonte de conclusão pelo contrato de progresso ACULTA | Médio: snapshot e verificação pública | Pendente |
| Slugs e rotas amigáveis | Classes `Drupal\lms\Entity\...` e nomes de rota LMS | Nenhum contrato de navegação | Encapsular a resolução de URL no adaptador de jornada/curso | Baixo | Pendente |
| Tema e SDC do curso | Negociação `lms:course_card` → `aculta420:course_card` | Contrato de apresentação neutro do Portal | Manter negociação enquanto a View LMS for fonte | Baixo | Justificado temporariamente |
| Conteúdo piloto | Script escreve entidades LMS/Group | JSON editorial canônico ACULTA | Migrar o importador para o domínio próprio na fase de autoria | Médio: idempotência | Pendente |
| Dependência Composer/módulo | `drupal/lms` e submódulos obrigatórios | Nenhuma | Remover somente após migração de dados e ausência de consumidores | Alto | **Bloqueio principal** |

## 5. Classificação dos pontos

**Encapsulados nesta fatia**

- Leitura da jornada do curso e da sala, incluindo acesso de entrada, estrutura publicada, estados, progresso e destinos autorizados.
- Tipo de curso e de aula exposto ao Studio, substituído por DTO ACULTA.

**Dependências temporárias que devem ser encapsuladas**

- Catálogo de cursos; o dashboard do estudante já consome o contrato neutro B2, embora seu provedor ainda leia Group/LMS.
- Turmas/memberships e autorização contextual.
- H5P, fórum pedagógico, recursos, agenda, conquistas, avisos e certificados na parte que lê o LMS.
- Slugs e rotas amigáveis.

**Infraestrutura aproveitável, se desacoplada**

- Group para turma, membership e grants.
- Media/File e política de arquivos.
- Commerce para pedido e entitlement.
- Messenger, certificados, fórum, galeria, revista e calendário já pertencem a owners ACULTA.
- Views e cacheabilidade do Drupal podem continuar, desde que consumidas por projeções ACULTA.

**Dependência necessária e justificada durante a transição**

- Escrita de tentativa, resposta e nota no LMS enquanto o domínio próprio não existir.
- Rotas nativas de atividade e submissão, porque o Form API e o access do LMS continuam autoritativos.
- Grupo `lms_course` como representação de curso enquanto a migração para entidade própria não for aprovada.

**Falsos positivos ou já independentes**

- O tema ACULTA420 não importa classes do LMS; apenas negocia um SDC de catálogo.
- Certificados, QR, PDF e verificação pública são entidades e rotas ACULTA.
- A entidade de certificado não é um segundo status de conclusão.

## 6. Ownership e fonte de verdade

| Dado/capacidade | Fonte atual | Fonte-alvo | Observação |
| --- | --- | --- | --- |
| Curso e currículo | Group `lms_course` + LMS | Domínio ACULTA (decisão futura sobre Group) | Não manter duas representações editáveis. |
| Turma e membership | Group | Group, inicialmente | Preserva grants e checkout. |
| Pedido e origem financeira | Commerce | Commerce | Não replicar status de pagamento no LMS. |
| Atividade, tentativa e resposta | LMS | Domínio ACULTA | Migração por curso, com reconciliação. |
| Progresso | Derivado de status LMS | Serviço ACULTA derivado de fatos canônicos | Não confiar em percentual armazenado sem reconciliar. |
| Arquivos e downloads | Media/File + LMS File Upload | Media/File + política central | Revalidar membership na entrega. |
| Certificado | ACULTA, lendo conclusão LMS | ACULTA, lendo conclusão ACULTA | Snapshot imutável preservado. |
| Avisos | Messenger + elegibilidade LMS | Messenger + evento educacional ACULTA | Sem tabela paralela de fila. |

## 7. Fatia implementada nesta entrega

### 7.1 Contratos e camada de dependência

- Módulo `aculta_learning_contracts` contém contratos e DTOs neutros e não
  depende do Portal, Studio, LMS ou Drupal LMS contrib.
- Jornada: `CourseJourneyReaderInterface`, `CourseJourney` e
  `CourseJourneyLesson`.
- Resumo de aprendizagem: `StudentLearningSummaryReaderInterface` e DTOs
  de estudante.
- Cursos da Conta: `AccountCoursesReaderInterface` e
  `AccountCourseSummary`. O DTO contém status ACULTA normalizado, texto e
  formato da descrição, destino autorizado e cache tags; não expõe entidades LMS
  nem render arrays.
- `LearningDashboardManager` e `AccountCoursesReader` no LMS adaptam os dados
  Group/LMS aos contratos. O Portal usa provider opcional e pode retornar estado
  vazio quando nenhum provider educacional está disponível.

A versão anterior dos contratos foi tentada em `aculta_portal` (que exige
`lms:lms`) e em `aculta_studio` (do qual o LMS dependia); nenhuma das duas
localizações sobrevive à retirada do contrib.

### 7.2 Consumidores e provedores alterados

- `aculta_studio\Learning\CourseJourneyComposer` e
  `CourseJourneyPresentationHooks` compõem a jornada e a sala.
- `aculta_studio\Learning\LearnerDashboardComposer` compõe o painel do
  estudante a partir dos DTOs neutros.
- `aculta_lms\LearningDashboardManager` implementa leitores de jornada e
  resumo estudantil.
- `aculta_lms\Account\AccountCoursesReader` implementa
  `AccountCoursesReaderInterface`, preservando autorização Group/LMS.
- `aculta_portal\AccountCoursesManager` virou facade provider-neutral; recebe
  o leitor opcional e adapta os DTOs ao presenter legado.
- `AccountCoursePresenter` usa status ACULTA normalizado, sem importar
  `CourseStatusInterface`.
- As rotas Ganjalab são definidas por `aculta_studio.routing.yml`; os IDs
  históricos `aculta_lms.*` foram preservados durante a transição.

### 7.3 O que a fatia prova

- A camada de apresentação pode ser testada com um leitor falso, sem instanciar
  `CourseInterface` nem `TrainingManager` (`CourseRoomBuilderTest`).
- O composer do Studio não precisa do módulo contrib nem de `aculta_lms` para
  montar a jornada (`CourseJourneyComposerTest`).
- O adaptador continua lendo a fonte de verdade atual e preserva o comportamento
  de acesso e progresso.
- O Studio não importa mais nenhuma classe de `Drupal\lms` nem de
  `Drupal\aculta_lms` em `src/`.

### 7.4 Direção das dependências (revisão de 2026-11-01)

Dependências **declaradas** relevantes nos metadados:

```text
aculta_learning_contracts        (sem dependências)
aculta_studio  ──depends──▶ aculta_portal
aculta_studio  ──depends──▶ aculta_learning_contracts
aculta_studio  ──depends──▶ group:group
aculta_lms     ──depends──▶ aculta_portal
aculta_lms     ──depends──▶ aculta_learning_contracts
```

Conclusões após a refatoração paralela de apresentação (2026-11-01):

- `aculta_lms` não declara dependência de `aculta_studio`; o adaptador não
  importa classes de apresentação.
- O Studio recebe dados por contratos neutros e possui a composição visual da
  jornada, sala e painel do estudante.
- `aculta_studio` depende de Group para a experiência de autoria/curso; essa
  dependência é explícita e permanece uma decisão da fase de domínio próprio.
- As rotas Ganjalab mantêm IDs `aculta_lms.*` por compatibilidade, embora a
  definição e a apresentação estejam no Studio. É acoplamento nominal transitório,
  não dependência de módulo.
- `aculta_portal` já não depende de `lms:lms` nos metadados. O adaptador
  `aculta_lms` mantém a dependência obrigatória do contrib e depende do Portal
  para serviços compartilhados, sem ciclo declarado.
- Validação Runtime desta entrega: jornada/sala **74/74** em 320–1440 px,
  `/ganjalab/educador` e o formulário de novo curso em **200**. O contrato B2 e a
  apresentação do painel estão no código; a homologação visual própria do novo
  dashboard permanece pendente.
### 7.5 O que permanece temporariamente acoplado

- `aculta_lms` declara as dependências contrib e depende de `aculta_portal` para
  serviços compartilhados. A dependência do Portal em LMS foi removida em código;
  as rotas do Ganjalab foram homologadas no Runtime com LMS ativo. Instalação
  limpa sem o contrib continua como gate arquitetural futuro.
- `aculta_studio` depende de Group para turma/curso e autoria educadora.
- O adaptador lê curso, lição, atividade, status, progresso e certificados;
  escrita de resposta/tentativa/nota continua no contrib.
- `aculta_lms.info.yml` continua exigindo `lms`, submódulos LMS,
  `lms_file_upload`, `webform_score` e `entity_print`.
- B3-A está implementada no código: facade de cursos opcional, contrato/DTO,
  provider LMS, slugs e route subscriber movidos para `aculta_lms`, e remoção de
  `lms:lms` do `aculta_portal.info.yml`. Lint, testes focais, rebuild e smoke
  do Ganjalab foram validados no Runtime/checkout; instalação limpa sem LMS
  permanece como gate futuro da independência total.

### 7.6 O que a fatia não faz

- Não remove `drupal/lms` do Composer nem das dependências `aculta_lms`/submódulos;
  somente `aculta_portal.info.yml` deixou de exigir `lms:lms`.
- Não migra cursos, lições, respostas, tentativas, notas ou progresso.
- Não altera a fonte de verdade e não cria entidade `Course`, `Module` ou `Activity`.
- Não altera o Runtime, a configuração ativa ou o banco.

## 8. Opigno: referência seletiva

A auditoria existente (`docs/modules/OPIGNO-CODE-AUDIT.md`) e o inventário (`docs/modules/OPIGNO-ACULTA-INVENTORY.md`) continuam válidos como referência. Nesta fatia, o Opigno influenciou apenas conceitos de apresentação:

- percurso ordenado com posição atual e próximo passo explícitos;
- separação entre catálogo, jornada e sala;
- visão do estudante como consumidor de um percurso já autorizado;
- estados de progresso textuais, não dependentes de cor.

Não foram portados: distribuição Opigno, manager Angular legado, entidades próprias de grupo/membership, armazenamento paralelo de progresso/notas, comércio, mensagens, certificados, SCORM/xAPI, export/import `.opi` nem migração de dados legados. A decisão da auditoria — não incorporar o Opigno inteiro; prototipar autoria própria — permanece.

## 9. Fases futuras

| Fase | Objetivo | Dependências | Critério de aceite | Rollback |
| --- | --- | --- | --- | --- |
| B2 — Resumo do estudante | Contrato ACULTA para dashboard (cursos, progresso, pendências, certificados) | `StudentLearningSummaryReaderInterface` e DTOs publicados; composer no Studio | Código implementado em `dev`; confirmar lint, testes e integração local; Studio sem imports LMS | Reverter consumidores e manter manager atual |
| B3-A — Portal sem LMS obrigatório | Contrato de cursos da Conta; provider e slugs no `aculta_lms`; remover `lms:lms` do Portal | Código validado no Runtime com contrib ativo; instalação limpa sem LMS permanece gate futuro | Implementada e homologada para Ganjalab no Runtime; instalação sem LMS não foi exercitada | Reintroduzir dependência se a validação de closure sem LMS falhar; nenhum dado migrado |
| C — Autoria própria | Curso, currículo e versão publicada em entidades ACULTA; editor mobile-first | Contratos B1/B2; decisão sobre Group | Rascunho, prévia e publicação sem duplicar atividade consumida | Desligar editor; fonte legada continua |
| D — Piloto de migração | Curso piloto migrado com mapa de IDs e reconciliação | C; backup e export imutável | Contagem, ordem, acesso e progresso equivalentes; sem exposição de arquivo privado | Ownership volta ao legado antes de novas tentativas |
| E — Avaliações | Migrar tentativas, respostas, notas e comentários | D; política de retenção | Testes de isolamento A/B, histórico preservado | Manter curso no novo backend; export/replay auditável |
| F — Retirada do contrib | Remover adapter, serviços, views, config e pacote Composer | E; nenhum consumidor restante | Checklist de desinstalação, restore e monitoramento sem chamadas ao LMS | Restaurar backup e versão compatível |

## 10. Critérios para declarar uma dependência removida

Uma dependência só pode ser marcada como removida quando, ao mesmo tempo:

1. não houver `use Drupal\lms`, serviço, entidade, rota, plugin, view ou configuração consumindo o ponto no código ACULTA;
2. os dados correspondentes tiverem fonte ACULTA canônica e migração reconciliada;
3. houver testes de acesso, cache e regressão cobrindo o novo caminho;
4. o adaptador legado puder ser desligado sem perda de histórico;
5. houver plano de rollback e backup verificável;
6. a dependência Composer/module puder ser removida sem quebrar instalação ou atualização.

## 11. Validações executadas e limites

Executado nesta entrega:

- `php -l` nos arquivos PHP alterados e criados: sem erros.
- PHPCS `Drupal,DrupalPractice` nos arquivos alterados: 0 erros; restaram 4 avisos de comprimento de linha em trechos **preexistentes** de `LearningDashboardManager`.
- PHPUnit Unit `aculta_studio`: 62 testes / 597 asserções, OK.
- PHPUnit Unit `aculta_lms`: 99 testes / 472 asserções, OK.
- Testes focais da jornada, do builder e do composer: OK. Com a mudança B2 do upstream, `LearnerDashboardComposerTest` foi movido para o Studio e o teste obsoleto de `LearningDashboardController::student()` no LMS foi removido; `StudioExperienceManagerTest` passou a fornecer o composer real.
- `aculta_learning_contracts.info.yml` não declara dependências; o módulo contém apenas interfaces e value objects. Kernel `ContractsInstallTest` **1 teste / 8 asserções PASS**: instala o módulo em um site com apenas `system`, sem `aculta_portal` e sem `lms`, e confirma que as interfaces e as classes `CourseJourney` e `StudentLearningSummary` autoload.
- Serviços: YAML analisado; `aculta_lms.course_journey_reader` e `aculta_studio.learner_dashboard_composer` existem; os aliases por interface apontam para eles; o controller e o provider do Studio recebem os serviços esperados.
- Direção de imports: `aculta_studio/src` não importa `Drupal\lms` nem `Drupal\aculta_lms`; `aculta_lms/src` não importa `Drupal\aculta_studio`. A composição da jornada/sala vive em `aculta_studio\Hook\CourseJourneyPresentationHooks`.
- **Runtime validado no commit `e60abcb62` em 2026-11-01.** Snapshot prévio em
  `/home/aculta-system-data/backups/aculta_runtime-before-learning-contracts-20261101.sql.gz`
  (SHA-256 `48d99c192d0d83a4c11ba3922fb25034dfe552d1f6e5fce180d93a7f946b1358`).
  `aculta_learning_contracts` habilitado com `drush en` e cache reconstruído com
  `drush cr`; `config:status` passou a responder **No differences between DB and
  sync directory**. `class_exists()` e `moduleExists()` retornam `true`.
- Smoke final do commit `e60abcb62`: Firefox ESR/X11 a 390 px aprovado; os 74/74 cenários mult largura foram aprovados após B3-A e correção do reader.
- Serviços resolvidos no container real:
  `aculta_lms.course_journey_reader` → `LearningDashboardManager`;
  `aculta_studio.learner_dashboard_composer` → `LearnerDashboardComposer`; os
  aliases pelas interfaces neutras devolvem as mesmas classes.
- Smoke Firefox ESR/X11 em `tests/browser/aculta-ganjalab/learning-experience.mjs`:
  **74/74 verificações OK** em 320, 390, 768, 1024, 1280 e 1440 px, incluindo
  início com/sem matrícula, jornada, sala, modo escuro e foco.
- Ganjalab Educador por HTTP autenticado: `/ganjalab/educador` **200** com H1 e
  ação "Novo curso"; `/ganjalab/educador/cursos/novo` **200** com o formulário
  Group nativo. Nenhum erro de H5P no request web (o erro visto via Drush era
  permissão do usuário de CLI sobre o diretório privado `aculta:aculta`).
- Erros de Runtime encontrados e corrigidos:
  - `ParseError` em `aculta_messenger.install:276` (`\\Drupal` e função
    duplicada) introduzido por trabalho não commitado de outra sessão; o arquivo
    foi restaurado para a versão já publicada em `origin/dev`. O conteúdo
    alheio foi preservado byte a byte fora esse arquivo quebrado.
  - `Interface "CourseJourneyReaderInterface" not found` — resolvido ao
    habilitar o módulo de contratos.
  - `ArgumentCountError` em `CourseAuthoringController::dashboard()`:
    `PortalLinkElement::build()` exige 3 argumentos. A chamada passou a enviar
    `data-aculta-studio` e `data-portal-ui`; teste de regressão adicionado em
    `CourseAuthoringControllerTest` (3/36). Verificação estática com
    nikic/php-parser: 54 chamadas de `PortalLinkElement::build`, 0 com aridade
    insuficiente.
  - `TypeError` em `CourseJourneyPresentationHooks::__construct()`: o type hint
    `CourseJourneyComposer`, sem import, resolvia para
    `Drupal\aculta_studio\Hook\CourseJourneyComposer`. Foi adicionado o import
    de `Drupal\aculta_studio\Learning\CourseJourneyComposer`; após `drush cr`, o
    container resolveu a dependência e o harness voltou a 74/74.
  - `Error: Call to a member function isAllowed() on true` em
    `AccountCoursesReader::getAccountCourses()`: `access('view', $account)` sem
    o terceiro argumento devolve bool. A chamada passou a usar
    `access('view', $account, TRUE)`; o catálogo e a home voltaram a responder e
    o harness fechou em 74/74.
- `php scripts/validate-project-status-index.php`: PASS (inclui o novo componente; 47 componentes).

Limites e bloqueios:

- O Kernel `AcultaLmsInstallTest` já falhava **antes** desta entrega: `Cannot autowire service "Drupal\aculta_lms\Hook\CourseLinkHooks": argument "$trainingManager" ... references class "Drupal\lms\TrainingManager" but no such service exists`. O harness registra serviços LMS falsos, mas não o alias de `TrainingManager`. A falha foi confirmada em `git stash` sobre a baseline e não é causada por esta fatia. Não foi corrigida aqui para não misturar outra frente.
- `updatedb:status` mostra as atualizações `aculta_messenger_update_10015` e `10016`, pertencentes à frente de Events de outra sessão; não foram executadas por não integrarem o Ganjalab nem esta revisão.
- Relatórios CSP (`style-src-elem`/`style-src-attr`, origem `htmx.min.js` e JS agregado) aparecem no log como `Informações` em páginas do site, inclusive antes desta entrega. Não são erros e não foram alterados: mexer na política exigiria decisão de segurança separada.
- O harness visual do Marley (`tests/browser/aculta-studio/visual.mjs`) não foi executado.
- Não houve migração de dados, importação global de configuração, `updatedb` ou alteração de conteúdo. A única escrita de configuração no Runtime foi habilitar o módulo necessário, com snapshot prévio.


## Decisão de escopo Opigno para a primeira fase de independência

O Opigno será aproveitado nesta fase como **referência de requisitos para percurso, estados, navegação guiada, reuso por referência e prévia**. Não serão portados o backend, as entidades, o manager Angular, os plugins de scoring ou os formatos de import/export. A especificação por capacidade, os limites e os critérios de aceite estão no [Plano seletivo Opigno para independência](OPIGNO-ACULTA-INDEPENDENCE-PLAN.md).

A primeira fase de independência deve mover os contratos/DTOs para uma camada educacional ACULTA que possa permanecer habilitada sem Drupal LMS contrib. **Estado em 2026-11-01:** os contratos vivem em `aculta_learning_contracts`, sem dependência de `lms:lms`; a localização anterior em `aculta_portal` era transitória porque o Portal exige o contrib. O provider legado fica isolado como adaptador; Studio e outros consumidores usam somente contratos ACULTA. A dependência de código reversa entre `aculta_lms` e `aculta_studio` foi removida com a mudança do `LearnerDashboardComposer` para o LMS.

**Cursos legados:** compatibilidade integral e migração de todo o acervo não são requisitos. O planejamento pode excluir cursos obsoletos da migração e prever seu descarte para reduzir esforço. Este inventário não autoriza exclusão: antes de apagar dados compartilhados, conferir matrículas, respostas, certificados, arquivos e referências, confirmar descarte e obter autorização para a ação destrutiva. Nenhuma exclusão ou migração integra a primeira fase.

Use o plano vinculado para os critérios de aceite e microfases; este inventário continua sendo a evidência de dependências atuais, não uma declaração de independência concluída.

## 12. Contagem reproduzível de imports do contrib LMS

Esta seção corrige a contagem qualitativa usada no relatório anterior ("42 arquivos"). O número não era reproduzível: a soma por módulo já indicava 60 arquivos únicos. A contagem abaixo distingue **arquivos únicos** de **ocorrências** (linhas de import).

Definição usada: linha que começa com `use Drupal\lms`, incluindo `Drupal\lms_classes` e `Drupal\lms_file_upload`. Não inclui `Drupal\aculta_lms` nem referências a nomes de rota/bundle.

Método:

```sh
cd /home/piradopirata/projetos/aculta

# Arquivos únicos, excluindo tests, no código ACULTA.
grep -rlE '^use Drupal\\lms' \
  web/modules/custom web/themes/custom scripts \
  --include='*.php' --include='*.module' --include='*.theme' \
  | grep -v '/tests/' | sort

# Ocorrências (linhas de import) nos mesmos arquivos.
grep -rlE '^use Drupal\\lms' \
  web/modules/custom web/themes/custom scripts \
  --include='*.php' --include='*.module' --include='*.theme' \
  | grep -v '/tests/' | xargs grep -hE '^use Drupal\\lms' | wc -l

# Incluindo tests.
grep -rlE '^use Drupal\\lms' \
  web/modules/custom web/themes/custom scripts \
  --include='*.php' --include='*.module' --include='*.theme' \
  | sort
```

Resultado atualizado em 2026-11-01, após a migração da conquista de conclusão para evento neutro:

| Escopo | Arquivos únicos | Ocorrências (`use`) |
| --- | ---: | ---: |
| Código ACULTA sem `tests/` | **59** | **114** |
| Incluindo `tests/` | **95** | **208** |

Distribuição dos 59 arquivos não-teste:

| Módulo/área | Arquivos únicos |
| --- | ---: |
| `aculta_lms` | 38 |
| `aculta_lms_h5p` | 7 |
| `aculta_forum_lms` | 6 |
| `aculta_lms_resources` | 5 |
| `aculta_messenger` | 1 |
| `aculta_lms_agenda` | 1 |
| `scripts` | 1 |

Caminhos dos 59 arquivos não-teste:

```text
scripts/content/load-course-introducao-antiproibicionismo.php
web/modules/custom/aculta_portal/modules/aculta_forum_lms/src/Context/LmsActivityDiscussionResolver.php
web/modules/custom/aculta_portal/modules/aculta_forum_lms/src/Context/Lmsaculta_H5PDiscussionResolver.php
web/modules/custom/aculta_portal/modules/aculta_forum_lms/src/Context/LmsDiscussionContextProvider.php
web/modules/custom/aculta_portal/modules/aculta_forum_lms/src/Hook/LmsActivityDiscussionHooks.php
web/modules/custom/aculta_portal/modules/aculta_forum_lms/src/Hook/Lmsaculta_H5PDiscussionHooks.php
web/modules/custom/aculta_portal/modules/aculta_forum_lms/src/Plugin/EntityReferenceSelection/Courseaculta_H5PContentSelection.php
web/modules/custom/aculta_portal/modules/aculta_lms_agenda/src/EventClassAccess.php
web/modules/custom/aculta_portal/modules/aculta_lms_h5p/src/Access/aculta_H5PCourseAccessManager.php
web/modules/custom/aculta_portal/modules/aculta_lms_h5p/src/aculta_H5PDrupal/aculta_H5PDrupal.php
web/modules/custom/aculta_portal/modules/aculta_lms_h5p/src/aculta_H5PEditor/aculta_H5PEditorUtilities.php
web/modules/custom/aculta_portal/modules/aculta_lms_h5p/src/Controller/aculta_H5PDashboardController.php
web/modules/custom/aculta_portal/modules/aculta_lms_h5p/src/Plugin/Condition/GroupHasaculta_H5PActivities.php
web/modules/custom/aculta_portal/modules/aculta_lms_h5p/src/Plugin/Field/FieldFormatter/aculta_H5PDefaultFormatter.php
web/modules/custom/aculta_portal/modules/aculta_lms_h5p/src/Plugin/Field/FieldWidget/aculta_H5PEditorWidget.php
web/modules/custom/aculta_portal/modules/aculta_lms_resources/src/ContentIndex/LmsCourseContentIndexProvider.php
web/modules/custom/aculta_portal/modules/aculta_lms_resources/src/CourseResourceBuilder.php
web/modules/custom/aculta_portal/modules/aculta_lms_resources/src/Hook/CourseResourcesHooks.php
web/modules/custom/aculta_portal/modules/aculta_lms_resources/src/Hook/GalleryCourseAccessHooks.php
web/modules/custom/aculta_portal/modules/aculta_lms_resources/src/Plugin/EntityReferenceSelection/PublishedGallerySelection.php
web/modules/custom/aculta_portal/modules/aculta_lms/src/Account/AccountCoursesReader.php
web/modules/custom/aculta_portal/modules/aculta_lms/src/Access/CertificateIssueAccessCheck.php
web/modules/custom/aculta_portal/modules/aculta_lms/src/Access/ClassActivityDeadlineAccessCheck.php
web/modules/custom/aculta_portal/modules/aculta_lms/src/Access/ClassDashboardAccessCheck.php
web/modules/custom/aculta_portal/modules/aculta_lms/src/Access/UploadedAnswerAccessCheck.php
web/modules/custom/aculta_portal/modules/aculta_lms/src/AttemptArchiveManager.php
web/modules/custom/aculta_portal/modules/aculta_lms/src/Certificate/CourseCertificateManagerInterface.php
web/modules/custom/aculta_portal/modules/aculta_lms/src/Certificate/CourseCertificateManager.php
web/modules/custom/aculta_portal/modules/aculta_lms/src/ClassRosterManager.php
web/modules/custom/aculta_portal/modules/aculta_lms/src/Controller/LearningDashboardController.php
web/modules/custom/aculta_portal/modules/aculta_lms/src/CourseOrderEnrollmentManager.php
web/modules/custom/aculta_portal/modules/aculta_lms/src/CourseRoomBuilder.php
web/modules/custom/aculta_portal/modules/aculta_lms/src/Deadline/ClassActivityDeadlineManager.php
web/modules/custom/aculta_portal/modules/aculta_lms/src/EducatorFeedbackPolicy.php
web/modules/custom/aculta_portal/modules/aculta_lms/src/Event/CourseCompletionPublisherInterface.php
web/modules/custom/aculta_portal/modules/aculta_lms/src/Event/CourseCompletionPublisher.php
web/modules/custom/aculta_portal/modules/aculta_lms/src/Form/ClassActivityDeadlineForm.php
web/modules/custom/aculta_portal/modules/aculta_lms/src/Form/ClassRosterVisibilityForm.php
web/modules/custom/aculta_portal/modules/aculta_lms/src/Form/CourseCertificateIssueForm.php
web/modules/custom/aculta_portal/modules/aculta_lms/src/Form/CourseEnrollmentPurchaseForm.php
web/modules/custom/aculta_portal/modules/aculta_lms/src/Hook/ActivityDeadlineFormHooks.php
web/modules/custom/aculta_portal/modules/aculta_lms/src/Hook/AnswerLifecycleEventHooks.php
web/modules/custom/aculta_portal/modules/aculta_lms/src/Hook/AttemptArchiveHooks.php
web/modules/custom/aculta_portal/modules/aculta_lms/src/Hook/CourseContentEventHooks.php
web/modules/custom/aculta_portal/modules/aculta_lms/src/Hook/CourseEnrollmentEventHooks.php
web/modules/custom/aculta_portal/modules/aculta_lms/src/Hook/CourseLinkHooks.php
web/modules/custom/aculta_portal/modules/aculta_lms/src/Hook/CourseRoomHooks.php
web/modules/custom/aculta_portal/modules/aculta_lms/src/Hook/CourseStatusEventHooks.php
web/modules/custom/aculta_portal/modules/aculta_lms/src/Hook/EducatorFeedbackAuthorCardHooks.php
web/modules/custom/aculta_portal/modules/aculta_lms/src/Hook/EnrollmentFormHooks.php
web/modules/custom/aculta_portal/modules/aculta_lms/src/LearningDashboardManager.php
web/modules/custom/aculta_portal/modules/aculta_lms/src/Notice/LearningEligibilityInterface.php
web/modules/custom/aculta_portal/modules/aculta_lms/src/Notice/LearningEligibility.php
web/modules/custom/aculta_portal/modules/aculta_lms/src/Notice/LearningNoticeRecipients.php
web/modules/custom/aculta_portal/modules/aculta_lms/src/Plugin/ActivityAnswer/FreeTextReviewFeedback.php
web/modules/custom/aculta_portal/modules/aculta_lms/src/Routing/LmsFriendlyRouteProcessor.php
web/modules/custom/aculta_portal/modules/aculta_lms/src/Routing/LmsFriendlySlugConverter.php
web/modules/custom/aculta_portal/modules/aculta_lms/src/Routing/LmsFriendlySlugs.php
web/modules/custom/aculta_portal/modules/aculta_messenger/src/Integration/Lms/LearningNoticeManager.php
```

Observações:

- A extração dos contratos não adicionou imports de `Drupal\lms` nos consumidores. A separação de apresentação removeu `aculta_lms/src/Hook/LearningRoomHooks.php`; a extração de conta/rotas moveu `AccountCoursesReader` e os três `Routing/LmsFriendly*` para o LMS. Com a B3-A, **`aculta_portal` deixou de importar `Drupal\lms`** e os dois processadores residuais saíram da lista. A migração do gatilho de conclusão para o evento neutro também removeu o import de `Drupal\\lms` do Game. O total atualizado é **59 arquivos / 114 linhas**.
- `aculta_studio/src` não aparece na lista: não importa `Drupal\lms`.
- Nenhum arquivo do `aculta_portal` base aparece na lista atual.
