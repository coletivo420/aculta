# Drupal LMS e Group

Revisão técnica: 2026-10-17.

## Ownership

| Dado/capacidade | Fonte canônica | Papel do Portal / `aculta_lms` |
| --- | --- | --- |
| Cursos e associação do autor/criador | Group, bundle `lms_course` | Apresentação, integrações e regras educacionais ausentes no LMS |
| Membership, papéis e turmas | Group membership e papéis Group | Verificação e apresentação; não criar matrícula paralela |
| Lições, atividades e plugins de resposta | Drupal LMS | Adaptar contratos; não duplicar entidades ou respostas |
| Tentativa, resposta, status e nota | Entidades `lms_course_status`, `lms_lesson_status`, `lms_answer` e `TrainingManager` | Consultar APIs LMS e construir projeções autorizadas |
| Arquivos/documentos | Media + File + File Access | Referenciar mídia autorizada, nunca copiar arquivo/URL para storage próprio |
| PDF viewer | Submódulo independente `aculta_pdf_viewer`; Media/File guardam arquivo e metadados | Integração global compartilhada; não pertence ao `aculta_lms` |
| Conquistas/identidade | `aculta_user` e `aculta_game` | Consumir serviço/evento público, sem gravar score duplicado |
| Mensagens e avisos | `aculta_messenger`, Message, Message Notify | Consumir contratos educacionais aprovados e revalidar acesso |

## Estado encontrado

- `composer.json` requer `drupal/lms:^1.2`, `drupal/group:^3.3` e `drupal/webform:^6.3`. O lock fixa LMS 1.2.4, Group 3.3.5 e Webform 6.3.1. Drupal local: 11.4.10.
- `lms_answer_plugins` e `lms_classes` são submódulos no repositório distribuído de `drupal/lms`, não pacotes Composer independentes. `lms_file_upload` é projeto Composer independente (`drupal/lms_file_upload`); não está instalado. `webform_score`, H5P, Entity Print, PDFPreview e ImageMagick também não estão instalados. `drupal/pdf` e PDF.js estão no Composer, mas o formatter de PDF contrib não é usado pelo `aculta_pdf_viewer` por incompatibilidade `.js`/ESM.
- `config/sync/lms.settings.yml`: `allow_to_enter_ungraded: false`, `use_revisions: false`. Não alterar sem plano de migração e validação dos dados existentes.
- `group.type.lms_course` habilita membership do criador e define o papel de criador. Membership tem cardinalidade de grupo ilimitada e de entidade 1.
- Papéis Group configurados: estudante (`member`, inclui `take course`); educador (`teacher`, global `lms_teacher`, pode administrar membros, editar/avaliar e ver revisões não publicadas); criador local; administradores Group interno/externo; outsiders e anônimos. Roles globais `lms_admin` e `lms_teacher` têm permissão global de criar grupos `lms_course`. Validar sobreposição de grants e comportamento de autor/educador antes de ampliar acesso.
- Tipos de atividade exportados: `true_false` e `no_answer`. Exibição, formulários e campos estão no Config Sync. `lms.settings.yml` não usa revisões LMS por padrão.
- Views existentes: `courses_catalog`, `courses` e `courses_admin`; controller `CoursesController` apresenta o catálogo COURSES usando a View e seus próprios access/cache. O catálogo não substitui o access handler de cada curso.
- Conta usa `AccountCoursesManager` e `AccountCoursePresenter`: carregamento de membership Group, `Group::access('view', $account)` antes de metadados/status, `TrainingManager::loadCourseStatus()`, URL por `DomainPurposeManager`, sem escrita. `LmsFriendlyRouteSubscriber`, converter e processor resolvem slugs amigáveis. Preservar rotas canônicas e política de slug em português.
- A fonte editorial canônica do curso piloto (“Introdução ao Antiproibicionismo”) é `scripts/content/courses/introducao-antiproibicionismo.json`, carregada pelo script idempotente `scripts/content/load-course-introducao-antiproibicionismo.php`. O caminho histórico `scripts/institution/provision-lms-pilot.php` delega ao loader e não mantém conteúdo próprio. Ambos os comandos operacionais escrevem entidades e usam `\Drupal::...` por serem scripts executados pelo Drush.
- `validate-lms-skin.php` protege o skin do LMS.

## F7 — Inventário de turmas e memberships

Estado do checkout em 2026-10-17: o código `lms_classes` 1.2.4 está presente
como submódulo de `drupal/lms`, mas ainda não consta em
`config/sync/core.extension.yml`. Os arquivos opcionais da versão instalada
definem o grupo `lms_class`, a relação curso→turma (`lms_classes`) e a
membership pessoa→turma (`group_membership`). O `ClassPermissionCalculator`
herda permissões entre curso e turma; não cria uma segunda fonte de matrícula.

### Comportamentos upstream confirmados no código instalado

- `lms_classes.settings:create_default_class` vem como `TRUE`. Quando habilitado,
  o hook `group_insert` cria uma turma padrão para cursos criados fora de uma
  sincronização. Isso não matricula alunos. O hook de membership modifica o
  formulário nativo de matrícula do curso e pode converter a relação em uma
  membership da turma selecionada.
- A permissão `join group` está concedida ao papel Group
  `lms_course-outsider`, cujo papel global é `authenticated`. Hoje, portanto,
  qualquer conta autenticada pode pedir/realizar matrícula direta no curso.
  Com `lms_classes` habilitado e turma disponível, o formulário nativo pode
  encaminhar essa matrícula à turma. Nenhuma decisão escrita no contrato F7
  define se essa autoinscrição deve continuar, exigir aprovação ou ser fechada.
- `aculta_user` concede `estudante` em `group_relationship_insert` somente para
  memberships cujo tipo de grupo seja `lms_course`. A matrícula convertida para
  um grupo `lms_class` não passa nesse critério; habilitar Classes sem adaptar e
  testar essa regra deixaria o usuário fora do fluxo documentado de papel.
- Os mapeamentos padrão de `lms_classes` exigem `add students` no curso para
  herdar `administer members` na turma e `view students` para herdar a permissão
  de listar memberships. O papel atual `lms_course-teacher` tem
  `administer members`, mas não tem `add students` nem `view students`; logo,
  esses acessos padrão não são concedidos pela configuração atual.
- O controle de confirmação de e-mail e termos do Portal está ligado às rotas
  `lms.course.start` e `lms.group.answer_form`. O hook não adiciona essa política
  ao fluxo de membership do Group. A proteção de matrícula precisa ser decidida
  e verificada separadamente.

### Trabalho restante antes de habilitar F7

1. Decidir a política de entrada: autoinscrição imediata, pedido sujeito a
   aprovação, ou matrícula somente por educador/administrador. A permissão
   `join group` atual torna essa decisão funcional, não apenas de interface.
2. Definir como memberships de turmas afetam `estudante`, incluindo retirada e
   pertencimento a várias turmas; alinhar `EnrollmentRoleHooks` ao contrato e
   garantir que memberships de educadores/criadores não recebam papel de aluno.
3. Configurar e verificar permissões de criação/gestão/listagem de turmas para
   educador, criador e admin, incluindo os mapeamentos `add students` e
   `view students` exigidos pelo upstream instalado.
4. Só então habilitar `lms_classes` e importar sua configuração opcional. Testar
   aluno A/B, educador A/B, criador, admin, conta sem e-mail confirmado e conta
   sem membership; revisar acesso ao curso, turma e lista de alunos.

Até fechar essas decisões, F7 permanece em inventário, sem habilitar o módulo,
alterar `join group`, ou criar memberships/turmas no Runtime.

## Critérios para consolidar o LMS

1. Manter uma só fonte versionada para o curso piloto e executar importação apenas em fase autorizada, após backup/preview e confirmação de alvo.
2. Fazer diff entre código, Config Sync e Runtime canônico antes de atualizar contrib ou importar configuração.
3. Atualizar `drupal/lms` somente com Composer dry-run, changelog upstream, teste de atualização e checagem de compatibilidade de plugins e configurações.
4. Preservar Group/LMS access e cache em todos os presenters, Views e SDC; testar usuário A/B, educador e turma cruzada.
5. Não atribuir nota oficial a resultado sem autenticação, autoria, tentativa válida e método de cálculo auditável.

Ver também: [ADR-005](../decisions/ADR-005-lms-integration.md), [contratos LMS](../portal/LMS-INTEGRATION-CONTRACTS.md) e [roadmap do aculta_lms](ACULTA-LMS-ROADMAP.md).
O visualizador global pertence ao submódulo independente [aculta_pdf_viewer](../../web/modules/custom/aculta_portal/modules/aculta_pdf_viewer/README.md), sem dependência do LMS.
