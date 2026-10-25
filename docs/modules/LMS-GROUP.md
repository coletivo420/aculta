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
- `lms_answer_plugins` e `lms_classes` são submódulos no repositório distribuído de `drupal/lms`, não pacotes Composer independentes. `lms_file_upload` é projeto Composer independente (`drupal/lms_file_upload`); não está instalado. `webform_score`, H5P, Entity Print, PDFPreview e ImageMagick também não estão instalados. `drupal/pdf` foi removido do Composer em 2026-10-21 por estar desativado e sem consumidores; o serviço compartilhado `aculta_pdf_viewer` fornece o viewer PDF.js ESM.
- `config/sync/lms.settings.yml`: `allow_to_enter_ungraded: false`, `use_revisions: false`. Não alterar sem plano de migração e validação dos dados existentes.
- `group.type.lms_course` habilita membership do criador e define o papel de criador. Membership tem cardinalidade de grupo ilimitada e de entidade 1.
- Papéis Group configurados: estudante (`member`, inclui `take course`); educador (`teacher`, global `lms_teacher`, pode administrar membros, editar/avaliar e ver revisões não publicadas); criador local; administradores Group interno/externo; outsiders e anônimos. Roles globais `lms_admin` e `lms_teacher` têm permissão global de criar grupos `lms_course`. Validar sobreposição de grants e comportamento de autor/educador antes de ampliar acesso.
- Tipos de atividade exportados: `true_false` e `no_answer`. Exibição, formulários e campos estão no Config Sync. `lms.settings.yml` não usa revisões LMS por padrão.
- Views existentes: `courses_catalog`, `courses` e `courses_admin`; controller `CoursesController` apresenta o catálogo COURSES usando a View e seus próprios access/cache. O catálogo não substitui o access handler de cada curso.
- Conta usa `AccountCoursesManager` e `AccountCoursePresenter`: carregamento de membership Group, `Group::access('view', $account)` antes de metadados/status, `TrainingManager::loadCourseStatus()`, URL por `DomainPurposeManager`, sem escrita. `LmsFriendlyRouteSubscriber`, converter e processor resolvem slugs amigáveis. Preservar rotas canônicas e política de slug em português.
- A fonte editorial canônica do curso piloto (“Introdução ao Antiproibicionismo”) é `scripts/content/courses/introducao-antiproibicionismo.json`, carregada pelo script idempotente `scripts/content/load-course-introducao-antiproibicionismo.php`. O caminho histórico `scripts/institution/provision-lms-pilot.php` delega ao loader e não mantém conteúdo próprio. Ambos os comandos operacionais escrevem entidades e usam `\Drupal::...` por serem scripts executados pelo Drush.
- `validate-lms-skin.php` protege o skin do LMS.

## F7 — Turmas e memberships

Estado do checkout em 2026-10-17: `lms_classes` 1.2.4 está sendo integrado pela configuração versionada. Os arquivos opcionais upstream definem o grupo `lms_class`, a relação curso→turma (`lms_classes`) e a membership pessoa→turma (`group_membership`). O `ClassPermissionCalculator` herda permissões entre curso e turma; não cria uma segunda fonte de matrícula.

### Comportamentos upstream confirmados no código instalado

- `lms_classes.settings:create_default_class` vem como `TRUE`. Quando habilitado, o hook `group_insert` cria uma turma padrão para cursos criados fora de uma sincronização. O hook de membership modifica o formulário nativo e pode converter a matrícula de curso em membership da turma selecionada.
- `join group` permanece concedida ao papel upstream `lms_course-outsider`, mas o formulário de autoinscrição direta é bloqueado pelo Portal. A pessoa escolhe a turma e finaliza um pedido Commerce do curso; equipe autorizada continua usando a gestão nativa de memberships do Group.
- `aculta_user` concede `estudante` em memberships de `lms_course` e `lms_class`, exceto relações com papel explícito de educador, criador ou administrador. O papel global permanece após sair da turma, conforme decisão anterior do responsável.
- Os mapeamentos de `lms_classes` exigem `add students` no curso para herdar `administer members` na turma e `view students` para listar memberships. Os grants foram acrescentados ao papel Group de educador. O papel global `lms_teacher` pode criar grupos `lms_class` e a relação curso→turma pode ser gerida no escopo do curso.
- A matrícula não exige confirmação de e-mail nem aceite de termos; a pessoa recebe aviso por e-mail sem bloquear a entrada. A entrega e retries ficam na fase de notificações educacionais F13.
- Educadores administram memberships de turma pela interface nativa do Group; a permissão herdada `administer members` permite adicionar e remover alunos matriculados manualmente. `field_course_order` guarda a origem comercial e impede exclusão manual enquanto houver pagamento ativo; matrículas manuais não recebem essa referência.
- Cada curso referencia um produto Commerce próprio do tipo `produto_curso`, com uma variação `curso`. Uma pessoa administradora cria o produto e a variação e associa o produto ao curso. O produto não oferece Add to cart; a compra começa na página do curso para que a turma seja escolhida e enviada junto no pedido. Preço zero também passa por checkout e só matricula quando o pedido é colocado/concluído. O checkout exige conta, permite cadastro e dispensa confirmação de e-mail nessa rota; o aceite geral de termos da conta permanece.
- Reembolso parcial preserva acesso. Reembolso integral revoga somente membership criada pelo pedido; se houver outra compra válida para a mesma turma, ela assume a referência. Membership manual e compras válidas permanecem preservadas.

### Decisões e estado da implementação F7

1. Autoinscrição para cursos gratuitos e pagos por meio de pedido Commerce; a equipe também pode matricular diretamente.
2. Educadores criam e administram turmas.
3. Conceder `estudante` ao entrar em qualquer turma. O papel global não é removido ao sair, mantendo a decisão já registrada.
4. Matrícula sem confirmação de e-mail e sem aceite de termos; somente aviso por e-mail.
5. Curso novo não recebe turma automática; educador/admin cria explicitamente.

A configuração versionada habilita `lms_classes`, inclui a configuração opcional upstream, define `create_default_class: false`, declara o papel estudante de turma e atualiza grants de educador. `EnrollmentRoleHooks` foi ampliado para memberships de turma. `EnrollmentFormHooks` exige turma para matrículas manuais, bloqueia autoinscrição direta, rejeita turma cruzada e protege memberships comerciais contra exclusão manual. O campo de origem não é editável pela equipe. Falta importar a configuração e executar smoke tests de isolamento A/B, educador, admin, checkout gratuito/pago, reembolso e conta sem e-mail confirmado no Runtime.

**RUNTIME STATUS: DEFERRED.** Drush confirmou `https://aculta.toca.net.br` e MariaDB `aculta_runtime`; `lms_classes` permanece desativado e a configuração Commerce/LMS não foi importada. Não houve alteração persistente no Runtime.

## Critérios para consolidar o LMS

1. Manter uma só fonte versionada para o curso piloto e executar importação apenas em fase autorizada, após backup/preview e confirmação de alvo.
2. Fazer diff entre código, Config Sync e Runtime canônico antes de atualizar contrib ou importar configuração.
3. Atualizar `drupal/lms` somente com Composer dry-run, changelog upstream, teste de atualização e checagem de compatibilidade de plugins e configurações.
4. Preservar Group/LMS access e cache em todos os presenters, Views e SDC; testar usuário A/B, educador e turma cruzada.
5. Não atribuir nota oficial a resultado sem autenticação, autoria, tentativa válida e método de cálculo auditável.

Ver também: [ADR-005](../decisions/ADR-005-lms-integration.md), [contratos LMS](../portal/LMS-INTEGRATION-CONTRACTS.md) e [roadmap do aculta_lms](ACULTA-LMS-ROADMAP.md).
O visualizador global pertence ao submódulo independente [aculta_pdf_viewer](../../web/modules/custom/aculta_portal/modules/aculta_pdf_viewer/README.md), sem dependência do LMS.
