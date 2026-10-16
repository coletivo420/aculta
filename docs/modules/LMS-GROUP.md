# Drupal LMS e Group

Revisão técnica: 2026-10-16.

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

- `composer.json` requer `drupal/lms:^1.2`, `drupal/group:^3.3` e `drupal/webform:^6.3`. O lock fixa LMS 1.2.3, Group 3.3.5 e Webform 6.3.1. Drupal local: 11.4.10.
- `lms_answer_plugins` e `lms_classes` são submódulos no repositório distribuído de `drupal/lms`, não pacotes Composer independentes. `lms_file_upload` é projeto Composer independente (`drupal/lms_file_upload`); não está instalado. `webform_score`, H5P, Entity Print, PDFPreview e ImageMagick também não estão instalados. `drupal/pdf` e PDF.js estão no Composer, mas o formatter de PDF contrib não é usado pelo `aculta_pdf_viewer` por incompatibilidade `.js`/ESM.
- `config/sync/lms.settings.yml`: `allow_to_enter_ungraded: false`, `use_revisions: false`. Não alterar sem plano de migração e validação dos dados existentes.
- `group.type.lms_course` habilita membership do criador e define o papel de criador. Membership tem cardinalidade de grupo ilimitada e de entidade 1.
- Papéis Group configurados: estudante (`member`, inclui `take course`); educador (`teacher`, global `lms_teacher`, pode administrar membros, editar/avaliar e ver revisões não publicadas); criador local; administradores Group interno/externo; outsiders e anônimos. Roles globais `lms_admin` e `lms_teacher` têm permissão global de criar grupos `lms_course`. Validar sobreposição de grants e comportamento de autor/educador antes de ampliar acesso.
- Tipos de atividade exportados: `true_false` e `no_answer`. Exibição, formulários e campos estão no Config Sync. `lms.settings.yml` não usa revisões LMS por padrão.
- Views existentes: `courses_catalog`, `courses` e `courses_admin`; controller `CoursesController` apresenta o catálogo COURSES usando a View e seus próprios access/cache. O catálogo não substitui o access handler de cada curso.
- Conta usa `AccountCoursesManager` e `AccountCoursePresenter`: carregamento de membership Group, `Group::access('view', $account)` antes de metadados/status, `TrainingManager::loadCourseStatus()`, URL por `DomainPurposeManager`, sem escrita. `LmsFriendlyRouteSubscriber`, converter e processor resolvem slugs amigáveis. Preservar rotas canônicas e política de slug em português.
- Há duas fontes versionadas para o curso piloto (“Introdução ao Antiproibicionismo”): JSON + loader idempotente em `scripts/content/courses/` e `scripts/content/load-course-*`; `scripts/institution/provision-lms-pilot.php` é um provisionador distinto. Ambos escrevem entidades e usam `\Drupal::...` por serem scripts operacionais. Antes de novas importações, declarar qual é a fonte editorial canônica e se estes caminhos devem ser consolidados.
- `validate-lms-skin.php` protege o skin do LMS.

## Critérios para consolidar o LMS

1. Registrar uma só fonte versionada para o curso piloto e executar importação apenas em fase autorizada, após backup/preview e confirmação de alvo.
2. Fazer diff entre código, Config Sync e Runtime canônico antes de atualizar contrib ou importar configuração.
3. Atualizar `drupal/lms` somente com Composer dry-run, changelog upstream, teste de atualização e checagem de compatibilidade de plugins e configurações.
4. Preservar Group/LMS access e cache em todos os presenters, Views e SDC; testar usuário A/B, educador e turma cruzada.
5. Não atribuir nota oficial a resultado sem autenticação, autoria, tentativa válida e método de cálculo auditável.

Ver também: [ADR-005](../decisions/ADR-005-lms-integration.md), [contratos LMS](../portal/LMS-INTEGRATION-CONTRACTS.md) e [roadmap do aculta_lms](ACULTA-LMS-ROADMAP.md).
O visualizador global pertence ao submódulo independente [aculta_pdf_viewer](../../web/modules/custom/aculta_portal/modules/aculta_pdf_viewer/README.md), sem dependência do LMS.
