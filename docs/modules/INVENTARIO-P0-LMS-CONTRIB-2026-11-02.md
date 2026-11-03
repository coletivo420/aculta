# Inventário P0 — referências ao LMS contrib (ADR-042)

**Data:** 2026-11-02
**Status:** P0 do ADR-042. Inventário técnico. Não altera código, configuração nem dado.
**Método:** `grep` sobre `web/modules/custom` por `Drupal\lms\`, `lms_activity`, `lms_answer`, `lms_lesson`, `lms_course_status`, `lms_lesson_status`, `lms_activity_type`, `lms_classes`, `lms_answer_plugins`, `lms_answer_comments` e `lms_file_upload` (PHP, módulos, inc, yml e twig).

## 1. Totais

- Arquivos com referência: 127.
- Ocorrências por token (todas as referências, não só arquivos): `lms_activity` 94, `lms_answer` 83, `Drupal\lms\Entity\Bundle\CourseInterface` 76, `CourseStatusInterface` 31, `AnswerInterface` 25, `lms_lesson` 24, `lms_course_status` 19, `ActivityInterface` 17, `LessonStatusInterface` 15, `Lesson` 10, `TrainingManager` 8, `lms_lesson_status` 8, `lms_activity_type` 8.

## 2. Por módulo

| Módulo | Arquivos | Papel no contrib | Fase do ADR-042 |
| --- | --- | --- | --- |
| aculta_lms/src | 44 | ver seção 3 | P1–P5 |
| aculta_lms/tests | 26 | ver seção 3 | P1–P5 |
| aculta_forum_lms/tests | 14 | ver seção 3 | P1–P5 |
| aculta_lms_h5p/src | 7 | ver seção 3 | P1–P5 |
| aculta_forum_lms/src | 7 | ver seção 3 | P1–P5 |
| aculta_lms_resources/tests | 6 | ver seção 3 | P1–P5 |
| aculta_lms_resources/src | 5 | ver seção 3 | P1–P5 |
| aculta_messenger/tests | 3 | ver seção 3 | P1–P5 |
| aculta_lms/config | 3 | ver seção 3 | P1–P5 |
| aculta_messenger/src | 2 | ver seção 3 | P1–P5 |
| aculta_lms_h5p/tests | 2 | ver seção 3 | P1–P5 |
| tests/src/Unit | 1 | ver seção 3 | P1–P5 |
| src/Hook/EmailConfirmationAccessHooks.php | 1 | ver seção 3 | P1–P5 |
| src/EventSubscriber/DomainRouteSubscriber.php | 1 | ver seção 3 | P1–P5 |
| aculta_studio/src | 1 | ver seção 3 | P1–P5 |
| aculta_statistics/src | 1 | ver seção 3 | P1–P5 |
| aculta_lms_agenda/tests | 1 | ver seção 3 | P1–P5 |
| aculta_lms/aculta_lms.routing.yml | 1 | ver seção 3 | P1–P5 |
| aculta_lms/aculta_lms.info.yml | 1 | ver seção 3 | P1–P5 |

## 3. Classificação por tipo de dependência

| Tipo | Exemplo | Substituição proposta | Fase |
| --- | --- | --- | --- |
| Interface de curso (`Bundle\CourseInterface`, `Course`) | Consultas e permissões de curso | Contrato ACULTA de curso sobre Group (já existe no adaptador) | P1 |
| Aula (`Lesson`, `LessonInterface`, `lms_lesson`) | Ordem e conteúdo da aula | Entidade própria de aula | P2 |
| Atividade (`Activity`, `ActivityInterface`, `lms_activity`, `lms_activity_type`) | Conteúdo e tipo da atividade | Entidade própria de atividade e tipos | P2–P3 |
| Resposta (`Answer`, `AnswerInterface`, `lms_answer`) | Envio, nota e data da resposta | Entidade própria de resposta | P2 |
| Progresso (`LessonStatus`, `CourseStatus`, `lms_lesson_status`, `lms_course_status`) | Estado do aluno em aula e curso | Entidade própria; a referência xAPI (ADR-041) aponta para ela | P2 |
| `TrainingManager` | Serviço de treinamento | Serviço ACULTA equivalente | P1 |
| Plugins de resposta (`ActivityAnswerBase`, `Attribute\ActivityAnswer`) | Tipos objetivos e abertos | Plugins próprios no `aculta_lms` | P3 |
| `lms_classes` | Turma e permissões de turma | Permissões próprias sobre o modelo de turma | P3/P5 |
| `lms_answer_comments` | Comentários de resposta | Comentários próprios | P3 |
| `lms_file_upload` | Envio de arquivo na resposta | Já há `aculta_file_uploader`; ligar a atividade a ele | P3 |
| Config e rotas (`aculta_lms.routing.yml`, `aculta_lms.info.yml`, `aculta_lms/config`) | Dependências declaradas | Remover ao fim de P5 | P5 |

## 4. Arquivos (com contagem de referências)

| Arquivo | Ocorrências |
| --- | --- |
| `web/modules/custom/aculta_portal/modules/aculta_forum_lms/src/Access/LmsForumModerationManager.php` | 2 |
| `web/modules/custom/aculta_portal/modules/aculta_forum_lms/src/Context/LmsActivityDiscussionResolver.php` | 8 |
| `web/modules/custom/aculta_portal/modules/aculta_forum_lms/src/Context/LmsDiscussionContextProvider.php` | 7 |
| `web/modules/custom/aculta_portal/modules/aculta_forum_lms/src/Context/Lmsaculta_H5PDiscussionResolver.php` | 4 |
| `web/modules/custom/aculta_portal/modules/aculta_forum_lms/src/Hook/LmsActivityDiscussionHooks.php` | 8 |
| `web/modules/custom/aculta_portal/modules/aculta_forum_lms/src/Hook/Lmsaculta_H5PDiscussionHooks.php` | 1 |
| `web/modules/custom/aculta_portal/modules/aculta_forum_lms/src/Plugin/EntityReferenceSelection/Courseaculta_H5PContentSelection.php` | 2 |
| `web/modules/custom/aculta_portal/modules/aculta_forum_lms/tests/src/Kernel/LmsActivityDiscussionResolverKernelTest.php` | 14 |
| `web/modules/custom/aculta_portal/modules/aculta_forum_lms/tests/src/Kernel/LmsClassEducatorPermissionKernelTest.php` | 6 |
| `web/modules/custom/aculta_portal/modules/aculta_forum_lms/tests/src/Kernel/LmsClosedDiscussionFieldKernelTest.php` | 4 |
| `web/modules/custom/aculta_portal/modules/aculta_forum_lms/tests/src/Kernel/LmsCommentModerationMatrixKernelTest.php` | 6 |
| `web/modules/custom/aculta_portal/modules/aculta_forum_lms/tests/src/Kernel/LmsDiscussionContextIsolationKernelTest.php` | 7 |
| `web/modules/custom/aculta_portal/modules/aculta_forum_lms/tests/src/Kernel/LmsH5PDiscussionResolverKernelTest.php` | 4 |
| `web/modules/custom/aculta_portal/modules/aculta_forum_lms/tests/src/Kernel/LmsModuleLifecycleKernelTest.php` | 8 |
| `web/modules/custom/aculta_portal/modules/aculta_forum_lms/tests/src/Kernel/LmsRolePermissionsConfigSyncKernelTest.php` | 9 |
| `web/modules/custom/aculta_portal/modules/aculta_forum_lms/tests/src/Kernel/LmsTopicBundleInstallKernelTest.php` | 6 |
| `web/modules/custom/aculta_portal/modules/aculta_forum_lms/tests/src/Unit/LmsActivityDiscussionResolverTest.php` | 11 |
| `web/modules/custom/aculta_portal/modules/aculta_forum_lms/tests/src/Unit/LmsDiscussionContextAccessTest.php` | 2 |
| `web/modules/custom/aculta_portal/modules/aculta_forum_lms/tests/src/Unit/LmsDiscussionInstallConfigTest.php` | 9 |
| `web/modules/custom/aculta_portal/modules/aculta_forum_lms/tests/src/Unit/LmsEditWindowExemptionTest.php` | 1 |
| `web/modules/custom/aculta_portal/modules/aculta_forum_lms/tests/src/Unit/Lmsaculta_H5PDiscussionResolverTest.php` | 1 |
| `web/modules/custom/aculta_portal/modules/aculta_lms/aculta_lms.info.yml` | 3 |
| `web/modules/custom/aculta_portal/modules/aculta_lms/aculta_lms.routing.yml` | 5 |
| `web/modules/custom/aculta_portal/modules/aculta_lms/config/activity_answer/free_text_review_feedback/field.field.lms_activity._bundle_placeholder_.phrase_feedback.yml` | 5 |
| `web/modules/custom/aculta_portal/modules/aculta_lms/config/activity_answer/free_text_review_feedback/field.storage.lms_activity.phrase_feedback.yml` | 4 |
| `web/modules/custom/aculta_portal/modules/aculta_lms/config/schema/aculta_lms.file_upload.schema.yml` | 1 |
| `web/modules/custom/aculta_portal/modules/aculta_lms/src/Access/CertificateIssueAccessCheck.php` | 1 |
| `web/modules/custom/aculta_portal/modules/aculta_lms/src/Access/ClassActivityDeadlineAccessCheck.php` | 6 |
| `web/modules/custom/aculta_portal/modules/aculta_lms/src/Access/ClassDashboardAccessCheck.php` | 1 |
| `web/modules/custom/aculta_portal/modules/aculta_lms/src/Access/UploadedAnswerAccessCheck.php` | 13 |
| `web/modules/custom/aculta_portal/modules/aculta_lms/src/Account/AccountCoursesReader.php` | 3 |
| `web/modules/custom/aculta_portal/modules/aculta_lms/src/AttemptArchiveManager.php` | 8 |
| `web/modules/custom/aculta_portal/modules/aculta_lms/src/Certificate/CourseCertificateManager.php` | 2 |
| `web/modules/custom/aculta_portal/modules/aculta_lms/src/Certificate/CourseCertificateManagerInterface.php` | 1 |
| `web/modules/custom/aculta_portal/modules/aculta_lms/src/ClassRosterManager.php` | 3 |
| `web/modules/custom/aculta_portal/modules/aculta_lms/src/Controller/AnswerFileDownloadController.php` | 6 |
| `web/modules/custom/aculta_portal/modules/aculta_lms/src/Controller/LearningDashboardController.php` | 1 |
| `web/modules/custom/aculta_portal/modules/aculta_lms/src/CourseOrderEnrollmentManager.php` | 2 |
| `web/modules/custom/aculta_portal/modules/aculta_lms/src/CourseRoomBuilder.php` | 1 |
| `web/modules/custom/aculta_portal/modules/aculta_lms/src/Deadline/ClassActivityDeadlineManager.php` | 3 |
| `web/modules/custom/aculta_portal/modules/aculta_lms/src/EducatorFeedbackPolicy.php` | 5 |
| `web/modules/custom/aculta_portal/modules/aculta_lms/src/Event/CourseCompletionPublisher.php` | 1 |
| `web/modules/custom/aculta_portal/modules/aculta_lms/src/Event/CourseCompletionPublisherInterface.php` | 1 |
| `web/modules/custom/aculta_portal/modules/aculta_lms/src/EventSubscriber/LearningNotificationSubscriber.php` | 2 |
| `web/modules/custom/aculta_portal/modules/aculta_lms/src/Form/ClassActivityDeadlineForm.php` | 1 |
| `web/modules/custom/aculta_portal/modules/aculta_lms/src/Form/ClassRosterVisibilityForm.php` | 2 |
| `web/modules/custom/aculta_portal/modules/aculta_lms/src/Form/CourseCertificateIssueForm.php` | 3 |
| `web/modules/custom/aculta_portal/modules/aculta_lms/src/Form/CourseEnrollmentPurchaseForm.php` | 3 |
| `web/modules/custom/aculta_portal/modules/aculta_lms/src/Hook/ActivityDeadlineFormHooks.php` | 7 |
| `web/modules/custom/aculta_portal/modules/aculta_lms/src/Hook/AnswerLifecycleEventHooks.php` | 1 |
| `web/modules/custom/aculta_portal/modules/aculta_lms/src/Hook/AttemptArchiveHooks.php` | 3 |
| `web/modules/custom/aculta_portal/modules/aculta_lms/src/Hook/CourseContentEventHooks.php` | 2 |
| `web/modules/custom/aculta_portal/modules/aculta_lms/src/Hook/CourseEnrollmentEventHooks.php` | 2 |
| `web/modules/custom/aculta_portal/modules/aculta_lms/src/Hook/CourseLinkHooks.php` | 3 |
| `web/modules/custom/aculta_portal/modules/aculta_lms/src/Hook/CourseRoomHooks.php` | 1 |
| `web/modules/custom/aculta_portal/modules/aculta_lms/src/Hook/CourseStatusEventHooks.php` | 1 |
| `web/modules/custom/aculta_portal/modules/aculta_lms/src/Hook/EducatorFeedbackAuthorCardHooks.php` | 2 |
| `web/modules/custom/aculta_portal/modules/aculta_lms/src/Hook/EducatorFeedbackEventHooks.php` | 2 |
| `web/modules/custom/aculta_portal/modules/aculta_lms/src/Hook/EnrollmentFormHooks.php` | 3 |
| `web/modules/custom/aculta_portal/modules/aculta_lms/src/Hook/FileUploadActivityFormHooks.php` | 3 |
| `web/modules/custom/aculta_portal/modules/aculta_lms/src/Hook/SubmissionImageHooks.php` | 1 |
| `web/modules/custom/aculta_portal/modules/aculta_lms/src/LearningDashboardManager.php` | 14 |
| `web/modules/custom/aculta_portal/modules/aculta_lms/src/Notice/LearningEligibility.php` | 8 |
| `web/modules/custom/aculta_portal/modules/aculta_lms/src/Notice/LearningEligibilityInterface.php` | 3 |
| `web/modules/custom/aculta_portal/modules/aculta_lms/src/Notice/LearningNoticeRecipients.php` | 7 |
| `web/modules/custom/aculta_portal/modules/aculta_lms/src/Plugin/ActivityAnswer/FileUpload.php` | 9 |
| `web/modules/custom/aculta_portal/modules/aculta_lms/src/Plugin/ActivityAnswer/FreeTextReviewFeedback.php` | 7 |
| `web/modules/custom/aculta_portal/modules/aculta_lms/src/Routing/LmsFriendlyRouteProcessor.php` | 1 |
| `web/modules/custom/aculta_portal/modules/aculta_lms/src/Routing/LmsFriendlySlugConverter.php` | 3 |
| `web/modules/custom/aculta_portal/modules/aculta_lms/src/Routing/LmsFriendlySlugs.php` | 2 |
| `web/modules/custom/aculta_portal/modules/aculta_lms/tests/src/Kernel/AcultaLmsInstallTest.php` | 1 |
| `web/modules/custom/aculta_portal/modules/aculta_lms/tests/src/Kernel/AttemptArchiveLifecycleTest.php` | 29 |
| `web/modules/custom/aculta_portal/modules/aculta_lms/tests/src/Kernel/CertificateLifecycleTest.php` | 3 |
| `web/modules/custom/aculta_portal/modules/aculta_lms/tests/src/Kernel/ClassActivityDeadlineTest.php` | 21 |
| `web/modules/custom/aculta_portal/modules/aculta_lms/tests/src/Kernel/ClassDashboardKernelTest.php` | 7 |
| `web/modules/custom/aculta_portal/modules/aculta_lms/tests/src/Kernel/ClassRosterVisibilityTest.php` | 4 |
| `web/modules/custom/aculta_portal/modules/aculta_lms/tests/src/Kernel/LearningDashboardKernelTest.php` | 10 |
| `web/modules/custom/aculta_portal/modules/aculta_lms/tests/src/Unit/AccountCoursesReaderTest.php` | 3 |
| `web/modules/custom/aculta_portal/modules/aculta_lms/tests/src/Unit/ActivityDeadlineFormHooksTest.php` | 4 |
| `web/modules/custom/aculta_portal/modules/aculta_lms/tests/src/Unit/AnswerLifecycleEventHooksTest.php` | 1 |
| `web/modules/custom/aculta_portal/modules/aculta_lms/tests/src/Unit/AttemptArchiveManagerTest.php` | 9 |
| `web/modules/custom/aculta_portal/modules/aculta_lms/tests/src/Unit/CertificateIssueAccessCheckTest.php` | 1 |
| `web/modules/custom/aculta_portal/modules/aculta_lms/tests/src/Unit/ClassActivityDeadlineFormTest.php` | 1 |
| `web/modules/custom/aculta_portal/modules/aculta_lms/tests/src/Unit/ClassActivityDeadlineManagerTest.php` | 4 |
| `web/modules/custom/aculta_portal/modules/aculta_lms/tests/src/Unit/CourseCompletionPublisherTest.php` | 1 |
| `web/modules/custom/aculta_portal/modules/aculta_lms/tests/src/Unit/CourseContentEventHooksTest.php` | 4 |
| `web/modules/custom/aculta_portal/modules/aculta_lms/tests/src/Unit/CourseJourneyManagerTest.php` | 9 |
| `web/modules/custom/aculta_portal/modules/aculta_lms/tests/src/Unit/CourseOrderEnrollmentManagerTest.php` | 4 |
| `web/modules/custom/aculta_portal/modules/aculta_lms/tests/src/Unit/CourseRoomBuilderTest.php` | 1 |
| `web/modules/custom/aculta_portal/modules/aculta_lms/tests/src/Unit/EducatorFeedbackEventHooksTest.php` | 8 |
| `web/modules/custom/aculta_portal/modules/aculta_lms/tests/src/Unit/FileUploadAnswerDataTest.php` | 1 |
| `web/modules/custom/aculta_portal/modules/aculta_lms/tests/src/Unit/FreeTextReviewFeedbackTest.php` | 3 |
| `web/modules/custom/aculta_portal/modules/aculta_lms/tests/src/Unit/LearningDashboardManagerTest.php` | 12 |
| `web/modules/custom/aculta_portal/modules/aculta_lms/tests/src/Unit/LearningNoticeRecipientsTest.php` | 2 |
| `web/modules/custom/aculta_portal/modules/aculta_lms/tests/src/Unit/LmsSubmissionImageValidatorTest.php` | 2 |
| `web/modules/custom/aculta_portal/modules/aculta_lms/tests/src/Unit/UploadedAnswerAccessCheckTest.php` | 4 |
| `web/modules/custom/aculta_portal/modules/aculta_lms_agenda/tests/src/Kernel/AgendaEntriesProviderKernelTest.php` | 14 |
| `web/modules/custom/aculta_portal/modules/aculta_lms_h5p/src/Access/aculta_H5PCourseAccessManager.php` | 1 |
| `web/modules/custom/aculta_portal/modules/aculta_lms_h5p/src/Controller/aculta_H5PDashboardController.php` | 1 |
| `web/modules/custom/aculta_portal/modules/aculta_lms_h5p/src/Plugin/Condition/GroupHasaculta_H5PActivities.php` | 1 |
| `web/modules/custom/aculta_portal/modules/aculta_lms_h5p/src/Plugin/Field/FieldFormatter/aculta_H5PDefaultFormatter.php` | 1 |
| `web/modules/custom/aculta_portal/modules/aculta_lms_h5p/src/Plugin/Field/FieldWidget/aculta_H5PEditorWidget.php` | 1 |
| `web/modules/custom/aculta_portal/modules/aculta_lms_h5p/src/aculta_H5PDrupal/aculta_H5PDrupal.php` | 2 |
| `web/modules/custom/aculta_portal/modules/aculta_lms_h5p/src/aculta_H5PEditor/aculta_H5PEditorUtilities.php` | 2 |
| `web/modules/custom/aculta_portal/modules/aculta_lms_h5p/tests/src/Kernel/aculta_H5PCourseAccessIntegrationTest.php` | 1 |
| `web/modules/custom/aculta_portal/modules/aculta_lms_h5p/tests/src/Unit/aculta_H5PCourseAccessManagerTest.php` | 2 |
| `web/modules/custom/aculta_portal/modules/aculta_lms_resources/src/ContentIndex/LmsCourseContentIndexProvider.php` | 5 |
| `web/modules/custom/aculta_portal/modules/aculta_lms_resources/src/CourseResourceBuilder.php` | 2 |
| `web/modules/custom/aculta_portal/modules/aculta_lms_resources/src/Hook/CourseResourcesHooks.php` | 1 |
| `web/modules/custom/aculta_portal/modules/aculta_lms_resources/src/Hook/GalleryCourseAccessHooks.php` | 1 |
| `web/modules/custom/aculta_portal/modules/aculta_lms_resources/src/Plugin/EntityReferenceSelection/PublishedGallerySelection.php` | 1 |
| `web/modules/custom/aculta_portal/modules/aculta_lms_resources/tests/src/Kernel/CourseResourceBuilderKernelTest.php` | 1 |
| `web/modules/custom/aculta_portal/modules/aculta_lms_resources/tests/src/Kernel/LmsCourseContentIndexAccessKernelTest.php` | 2 |
| `web/modules/custom/aculta_portal/modules/aculta_lms_resources/tests/src/Unit/CourseResourceBuilderTest.php` | 1 |
| `web/modules/custom/aculta_portal/modules/aculta_lms_resources/tests/src/Unit/GalleryCourseAccessHooksTest.php` | 2 |
| `web/modules/custom/aculta_portal/modules/aculta_lms_resources/tests/src/Unit/LmsCourseContentIndexProviderTest.php` | 1 |
| `web/modules/custom/aculta_portal/modules/aculta_lms_resources/tests/src/Unit/PublishedGallerySelectionTest.php` | 1 |
| `web/modules/custom/aculta_portal/modules/aculta_messenger/src/Integration/Lms/EventSubscriber/LearningNoticeEventSubscriber.php` | 5 |
| `web/modules/custom/aculta_portal/modules/aculta_messenger/src/Integration/Lms/LearningNoticeManager.php` | 15 |
| `web/modules/custom/aculta_portal/modules/aculta_messenger/tests/src/Integration/Lms/Kernel/LearningNoticePersistenceKernelTest.php` | 2 |
| `web/modules/custom/aculta_portal/modules/aculta_messenger/tests/src/Integration/Lms/Unit/LearningNoticeDigestProviderTest.php` | 2 |
| `web/modules/custom/aculta_portal/modules/aculta_messenger/tests/src/Integration/Lms/Unit/LearningNoticeEventSubscriberTest.php` | 6 |
| `web/modules/custom/aculta_portal/modules/aculta_statistics/src/Lms/LmsClassResolver.php` | 2 |
| `web/modules/custom/aculta_portal/modules/aculta_studio/src/MarleyListingBuilder.php` | 2 |
| `web/modules/custom/aculta_portal/src/EventSubscriber/DomainRouteSubscriber.php` | 4 |
| `web/modules/custom/aculta_portal/src/Hook/EmailConfirmationAccessHooks.php` | 1 |
| `web/modules/custom/aculta_portal/tests/src/Unit/DomainRouteSubscriberLocalizedPathsTest.php` | 3 |
