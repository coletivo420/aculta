<?php

declare(strict_types=1);

namespace Drupal\aculta_studio\Learning;

use Drupal\aculta_lms\Learning\StudentLearningCertificate;
use Drupal\aculta_lms\Learning\StudentLearningCourse;
use Drupal\aculta_lms\Learning\StudentLearningSummaryReaderInterface;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\Core\Url;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Composes the learner dashboard from authorized LMS summary data and SDCs.
 */
final class LearnerDashboardComposer {

  use StringTranslationTrait;

  public function __construct(
    private readonly ?StudentLearningSummaryReaderInterface $summaries,
    private readonly AccountProxyInterface $currentUser,
    private readonly DateFormatterInterface $dateFormatter,
    TranslationInterface $stringTranslation,
  ) {
    $this->stringTranslation = $stringTranslation;
  }

  /**
   * Tells whether the learning summary provider is available.
   */
  public function available(): bool {
    return $this->summaries !== NULL;
  }

  /**
   * Builds the new learner page entirely from presentation components.
   *
   * LMS remains the source of authorized course, progress and certificate
   * summaries. This composer only adapts those values to the theme SDC contract.
   *
   * @return array<string, mixed>
   *   The dashboard SDC render array with cache metadata from the LMS reader.
   */
  public function build(): array {
    if ($this->summaries === NULL) {
      throw new NotFoundHttpException();
    }

    $summary = $this->summaries->getStudentLearningSummary($this->currentUser);
    $courses = [];
    foreach ($summary->courses as $course) {
      $courses['course_' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $course->id)] = $this->buildCourseCard($course);
    }

    $certificates = [];
    foreach ($summary->certificates as $delta => $certificate) {
      if ($certificate instanceof StudentLearningCertificate) {
        $certificates['certificate_' . $delta] = $this->buildCertificateCard($certificate);
      }
    }

    $build = [
      '#type' => 'component',
      '#component' => 'aculta420_v2:lms-learning-dashboard',
      '#props' => [
        'title' => (string) $this->t('Minha aprendizagem'),
        'introduction' => (string) $this->t('Acompanhe seus cursos e continue de onde parou.'),
        'continue_title' => (string) $this->t('Continuar aprendendo'),
        'courses_title' => (string) $this->t('Meus cursos'),
        'empty_courses_message' => (string) $this->t('Você ainda não está matriculado em nenhum curso. Quando se matricular, seus cursos aparecerão aqui.'),
        'certificates_title' => (string) $this->t('Meus certificados'),
      ],
      '#slots' => [
        'resume' => $summary->continueCourse instanceof StudentLearningCourse ? $this->buildCourseCard($summary->continueCourse, TRUE) : [],
        'courses' => $courses,
        'empty_action' => $this->buildButton(
          $this->t('Explorar o catálogo de cursos'),
          Url::fromRoute('aculta_portal.courses_home'),
          'primary',
        ),
        'certificates' => $certificates,
      ],
    ];

    $cache = CacheableMetadata::createFromRenderArray($build);
    $cache->addCacheableDependency($summary->cache);
    $cache->applyTo($build);
    return $build;
  }

  /**
   * Builds a course card using the LMS-specific SDC.
   */
  private function buildCourseCard(StudentLearningCourse $course, bool $resume = FALSE): array {
    $hasLessons = $course->totalLessons > 0;
    $progressText = $hasLessons
      ? $this->t('@completed de @total aulas concluídas', [
        '@completed' => $course->completedLessons,
        '@total' => $course->totalLessons,
      ])
      : $this->t('Este curso ainda não tem aulas publicadas.');
    $courseId = preg_replace('/[^a-zA-Z0-9_-]/', '_', $course->id);
    $slots = [
      'journey_action' => $this->buildButton(
        $this->t('Ver jornada'),
        Url::fromRoute('entity.group.canonical', ['group' => $course->id]),
        'outline',
      ),
    ];
    if ($course->continueUrl instanceof Url) {
      if ($course->finished) {
        $label = $this->t('Ver resultado');
      }
      elseif ($resume) {
        $label = $course->currentLessonLabel !== NULL && !$course->currentLessonFinished
          ? $this->t('Continuar aula')
          : $this->t('Continuar curso');
      }
      else {
        $label = $course->started ? $this->t('Continuar') : $this->t('Começar curso');
      }
      $slots['continue_action'] = $this->buildButton($label, $course->continueUrl, 'primary');
    }

    return [
      '#type' => 'component',
      '#component' => 'aculta420_v2:lms-learning-course-card',
      '#props' => [
        'title' => $course->label,
        'progress_id' => 'lms-course-progress-' . $courseId,
        'progress_label' => (string) $this->t('Progresso do curso @course', ['@course' => $course->label]),
        'progress_text' => (string) $progressText,
        'progress_value' => $hasLessons ? $course->progressPercent : NULL,
        'has_lessons' => $hasLessons,
        'finished' => $course->finished,
        'pending_evaluation' => $course->pendingEvaluation,
        'pending_text' => (string) $this->t('Avaliação pendente'),
        'finished_text' => (string) $this->t('Curso concluído'),
        'current_lesson' => $resume && $course->currentLessonLabel !== NULL && !$course->currentLessonFinished
          ? (string) $this->t('Próxima aula: @lesson', ['@lesson' => $course->currentLessonLabel])
          : '',
      ],
      '#slots' => $slots,
    ];
  }

  /**
   * Builds a certificate card from the public-safe LMS certificate summary.
   */
  private function buildCertificateCard(StudentLearningCertificate $certificate): array {
    $active = $certificate->state === 'active';
    return [
      '#type' => 'component',
      '#component' => 'aculta420_v2:lms-certificate-card',
      '#props' => [
        'title' => (string) $certificate->courseTitle,
        'status' => $active ? 'active' : 'revoked',
        'status_text' => (string) ($active ? $this->t('Válido') : $this->t('Revogado')),
        'issued_text' => (string) $this->t('Emitido em @date', [
          '@date' => $this->dateFormatter->format((int) $certificate->issuedAt, 'short'),
        ]),
      ],
      '#slots' => [
        'verification_action' => $this->buildButton(
          $this->t('Ver certificado'),
          $certificate->verificationUrl,
          'secondary',
        ),
      ],
    ];
  }

  /**
   * Builds a theme button component for a canonical URL.
   */
  private function buildButton(mixed $label, Url $url, string $variant): array {
    return [
      '#type' => 'component',
      '#component' => 'aculta420_v2:button',
      '#props' => [
        'text' => (string) $label,
        'url' => $url->toString(),
        'variant' => $variant,
      ],
    ];
  }

}
