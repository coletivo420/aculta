<?php

declare(strict_types=1);

namespace Drupal\aculta_studio\Learning;

use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Drupal\aculta_lms\Learning\StudentLearningCertificate;
use Drupal\aculta_lms\Learning\StudentLearningCourse;
use Drupal\aculta_lms\Learning\StudentLearningSummaryReaderInterface;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\Core\Url;

/**
 * Composes the learner dashboard from authorized provider-neutral summary data.
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
   * Builds the "Continuar aprendendo" home.
   */
  public function build(): array {
    if ($this->summaries === NULL) {
      throw new NotFoundHttpException();
    }
    $data = $this->summaries->getStudentLearningSummary($this->currentUser);
    $build = [
      '#type' => 'container',
      '#attributes' => [
        'data-portal-ui' => 'studio-fidelity-scope',
        'data-aculta-learning-home' => 'true',
      ],
      'context_header' => [
        '#theme' => 'aculta_portal_component',
        '#name' => 'context-header',
        '#props' => [
          'eyebrow' => (string) $this->t('ACULTA Studio Ganjalab Estudante'),
          'title' => (string) $this->t('Minha aprendizagem'),
          'subtitle' => (string) $this->t('Continue de onde parou e acompanhe suas aulas.'),
        ],
        '#weight' => -100,
      ],
      '#cache' => ['max-age' => 0],
    ];

    if ($data->continueCourse?->continueUrl instanceof Url) {
      $build['continue_section'] = $this->buildContinueSection($data->continueCourse);
    }

    $build['courses_section'] = $this->buildCoursesSection($data->courses);

    if ($data->certificates !== []) {
      $build['certificates_section'] = $this->buildCertificatesSection($data->certificates);
    }

    CacheableMetadata::createFromRenderArray($build)
      ->addCacheableDependency($data->cache)
      ->applyTo($build);
    return $build;
  }

  /**
   * Builds the resume card only for a canonical, unfinished course.
   *
   * @param \Drupal\aculta_lms\Learning\StudentLearningCourse $course
   *   One authorized course summary from the learning provider.
   *
   * @return array<string, mixed>
   *   Render array.
   */
  private function buildContinueSection(StudentLearningCourse $course): array {
    $show_lesson = $course->currentLessonLabel !== NULL && !$course->currentLessonFinished;
    $content = [];
    if ($show_lesson) {
      $content['lesson'] = [
        '#type' => 'html_tag',
        '#tag' => 'p',
        '#value' => $this->t('Aula: @lesson', ['@lesson' => $course->currentLessonLabel]),
        '#attributes' => ['data-portal-ui' => 'aculta-learning-home__lesson'],
      ];
    }
    $content['progress'] = $this->buildProgress($course);

    $url = $course->continueUrl;
    $label = $show_lesson ? $this->t('Continuar aula') : $this->t('Continuar curso');

    return [
      '#type' => 'container',
      '#attributes' => ['data-portal-ui' => 'aculta-learning-home__section'],
      'title' => [
        '#type' => 'html_tag',
        '#tag' => 'h2',
        '#value' => $this->t('Continuar aprendendo'),
        '#attributes' => ['data-portal-ui' => 'aculta-learning-home__section-title'],
      ],
      'card' => [
        '#theme' => 'aculta_portal_component',
        '#name' => 'card',
        '#props' => ['variant' => 'studio_fidelity'],
        '#slots' => [
          'header' => [
            '#type' => 'html_tag',
            '#tag' => 'h3',
            '#value' => $course->label,
            '#attributes' => ['data-portal-ui' => 'aculta-learning-home__course-title'],
          ],
          'content' => $content,
          'footer' => $this->button($label, $url, 'primary'),
        ],
      ],
    ];
  }

  /**
   * Builds the enrolled courses list.
   *
   * @param array<int, \Drupal\aculta_lms\Learning\StudentLearningCourse> $courses
   *   Authorized course records.
   *
   * @return array<string, mixed>
   *   Render array.
   */
  private function buildCoursesSection(array $courses): array {
    $section = [
      '#type' => 'container',
      '#attributes' => ['data-portal-ui' => 'aculta-learning-home__section'],
      'title' => [
        '#type' => 'html_tag',
        '#tag' => 'h2',
        '#value' => $this->t('Meus cursos'),
        '#attributes' => ['data-portal-ui' => 'aculta-learning-home__section-title'],
      ],
    ];

    if ($courses === []) {
      $catalog_url = Url::fromRoute('aculta_portal.courses_home');
      $section['empty'] = [
        '#theme' => 'aculta_portal_component',
        '#name' => 'empty-state',
        '#props' => ['compact' => FALSE],
        '#slots' => [
          'message' => [
            '#type' => 'html_tag',
            '#tag' => 'p',
            '#value' => $this->t('Você ainda não está matriculado em nenhum curso. Quando se matricular, seus cursos aparecem aqui.'),
          ],
          'actions' => $this->button($this->t('Explorar o catálogo de cursos'), $catalog_url, 'primary'),
        ],
      ];
      return $section;
    }

    $items = [];
    foreach ($courses as $course) {
      $items[] = $this->buildCourseCard($course);
    }
    $section['grid'] = [
      '#theme' => 'aculta_portal_component',
      '#name' => 'content-grid',
      '#props' => ['columns' => 2],
      '#slots' => ['items' => $items],
    ];
    return $section;
  }

  /**
   * Builds one enrolled course card with only canonical destinations.
   *
   * @param \Drupal\aculta_lms\Learning\StudentLearningCourse $course
   *   Authorized course summary.
   *
   * @return array<string, mixed>
   *   Render array.
   */
  private function buildCourseCard(StudentLearningCourse $course): array {
    $content = ['progress' => $this->buildProgress($course)];
    if ($course->pendingEvaluation) {
      $content['pending'] = [
        '#type' => 'html_tag',
        '#tag' => 'p',
        '#value' => $this->t('Avaliação pendente'),
        '#attributes' => ['data-portal-ui' => 'aculta-learning-home__pending'],
      ];
    }

    $actions = [];
    $journey_url = Url::fromRoute('entity.group.canonical', ['group' => $course->id]);
    $actions['journey'] = $this->button($this->t('Ver jornada'), $journey_url, 'outline');
    if ($course->continueUrl instanceof Url) {
      $actions['continue'] = $this->button($this->courseActionLabel($course), $course->continueUrl, 'primary');
    }

    return [
      '#theme' => 'aculta_portal_component',
      '#name' => 'card',
      '#props' => ['variant' => 'studio_fidelity'],
      '#slots' => [
        'header' => [
          '#type' => 'html_tag',
          '#tag' => 'h3',
          '#value' => $course->label,
          '#attributes' => ['data-portal-ui' => 'aculta-learning-home__course-title'],
        ],
        'content' => $content,
        'footer' => [
          '#type' => 'container',
          '#attributes' => ['data-portal-ui' => 'aculta-learning-home__actions'],
          'items' => $actions,
        ],
      ],
    ];
  }

  /**
   * Builds a link as the neutral aculta_portal_button element.
   *
   * @param mixed $label
   *   Visible label.
   * @param \Drupal\Core\Url $url
   *   Destination URL.
   * @param string $variant
   *   Button variant (primary, secondary, outline, ghost or danger).
   *
   * @return array
   *   Neutral element render array.
   */
  private function button(mixed $label, Url $url, string $variant): array {
    return [
      '#type' => 'aculta_portal_button',
      '#props' => [
        'text' => (string) $label,
        'url' => $url->toString(),
        'variant' => $variant,
      ],
    ];
  }

  /**
   * Chooses a label for the canonical LMS course destination.
   *
   * @param \Drupal\aculta_lms\Learning\StudentLearningCourse $course
   *   Authorized course summary.
   */
  private function courseActionLabel(StudentLearningCourse $course): string {
    if ($course->finished) {
      return (string) $this->t('Ver resultado');
    }
    if ($course->started) {
      return (string) $this->t('Continuar');
    }
    return (string) $this->t('Começar curso');
  }

  /**
   * Builds accessible progress text and bar, or an honest empty state.
   *
   * @param \Drupal\aculta_lms\Learning\StudentLearningCourse $course
   *   Authorized course summary.
   *
   * @return array<string, mixed>
   *   Render array.
   */
  private function buildProgress(StudentLearningCourse $course): array {
    $total = $course->totalLessons;
    if ($total <= 0) {
      return [
        '#type' => 'html_tag',
        '#tag' => 'p',
        '#value' => $this->t('Este curso ainda não tem aulas publicadas.'),
        '#attributes' => ['data-portal-ui' => 'aculta-learning-home__meta'],
      ];
    }
    $completed = $course->completedLessons;
    $percent = $course->progressPercent;
    return [
      '#type' => 'container',
      '#attributes' => ['data-portal-ui' => 'aculta-learning-home__progress'],
      'label' => [
        '#type' => 'html_tag',
        '#tag' => 'p',
        '#value' => $this->t('@completed de @total aulas concluídas (@percent%)', [
          '@completed' => $completed,
          '@total' => $total,
          '@percent' => $percent,
        ]),
      ],
      'bar' => [
        '#type' => 'html_tag',
        '#tag' => 'progress',
        '#value' => '',
        '#attributes' => [
          'max' => 100,
          'value' => $percent,
          'aria-label' => $this->t('Progresso do curso'),
        ],
      ],
    ];
  }

  /**
   * Builds the emitted certificates list.
   *
   * @param array<int, \Drupal\aculta_lms\Learning\StudentLearningCertificate> $certificates
   *   Authorized certificate summary DTOs supplied by the learning provider.
   *
   * @return array<string, mixed>
   *   Render array.
   */
  private function buildCertificatesSection(array $certificates): array {
    $items = [];
    foreach ($certificates as $certificate) {
      if (!$certificate instanceof StudentLearningCertificate) {
        continue;
      }
      $valid = $certificate->state === 'active';
      $items[] = [
        '#theme' => 'aculta_portal_component',
        '#name' => 'card',
        '#props' => ['variant' => 'studio_fidelity'],
        '#slots' => [
          'header' => [
            '#type' => 'html_tag',
            '#tag' => 'h3',
            '#value' => (string) $certificate->courseTitle,
          ],
          'content' => [
            'state' => ['#plain_text' => $valid ? $this->t('Válido') : $this->t('Revogado')],
            'issued' => [
              '#plain_text' => $this->t('Emitido em @date', [
                '@date' => $this->dateFormatter->format((int) $certificate->issuedAt, 'short'),
              ]),
            ],
          ],
          'footer' => $this->button($this->t('Ver certificado'), $certificate->verificationUrl, 'primary'),
        ],
      ];
    }

    return [
      '#type' => 'container',
      '#attributes' => ['data-portal-ui' => 'aculta-learning-home__section'],
      'title' => [
        '#type' => 'html_tag',
        '#tag' => 'h2',
        '#value' => $this->t('Meus certificados'),
        '#attributes' => ['data-portal-ui' => 'aculta-learning-home__section-title'],
      ],
      'grid' => [
        '#theme' => 'aculta_portal_component',
        '#name' => 'content-grid',
        '#props' => ['columns' => 2],
        '#slots' => ['items' => $items],
      ],
    ];
  }

}
