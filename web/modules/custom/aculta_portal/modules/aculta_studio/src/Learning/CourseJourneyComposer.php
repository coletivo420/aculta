<?php

declare(strict_types=1);

namespace Drupal\aculta_studio\Learning;

use Drupal\aculta_lms\Learning\CourseJourney;
use Drupal\aculta_lms\Learning\CourseJourneyLesson;
use Drupal\Component\Utility\Html;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Link;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\Core\Url;

/**
 * Composes the Ganjalab course journey and learning room presentations.
 *
 * Commands, access and lesson state arrive through the ACULTA course journey
 * contract. This class only turns already-authorized data into ACULTA420
 * render arrays and never queries entities, permissions or progress.
 */
final class CourseJourneyComposer {

  use StringTranslationTrait;

  public function __construct(TranslationInterface $stringTranslation) {
    $this->stringTranslation = $stringTranslation;
  }

  /**
   * Builds the "Minha jornada" body for the native course page.
   *
   * @param \Drupal\aculta_studio\Learning\CourseJourney $journey
   *   Authorized journey data from the ACULTA course journey contract.
   *
   * @return array<string, mixed>
   *   Render array.
   */
  public function build(CourseJourney $journey): array {
    $build = [
      '#type' => 'container',
      '#attributes' => ['data-portal-ui' => 'aculta-learning-journey'],
      'back' => [
        '#type' => 'container',
        '#attributes' => ['data-portal-ui' => 'aculta-learning-journey__back'],
        'link' => Link::fromTextAndUrl(
          $this->t('Minha aprendizagem'),
          Url::fromRoute('aculta_lms.student_dashboard'),
        )->toRenderable(),
      ],
      'intro' => [
        '#type' => 'container',
        '#attributes' => ['data-portal-ui' => 'aculta-learning-journey__intro'],
        'eyebrow' => [
          '#type' => 'html_tag',
          '#tag' => 'p',
          '#value' => $this->t('ACULTA Studio Ganjalab'),
          '#attributes' => ['data-portal-ui' => 'aculta-learning-journey__eyebrow'],
        ],
        'title' => [
          '#type' => 'html_tag',
          '#tag' => 'h2',
          '#value' => $this->t('Minha jornada'),
          '#attributes' => ['data-portal-ui' => 'aculta-learning-journey__title'],
        ],
      ],
      'summary' => $this->buildSummary($journey),
      'outline' => $this->buildJourneyOutline($journey),
    ];
    CacheableMetadata::createFromRenderArray($build)
      ->addCacheableDependency($journey->cache)
      ->applyTo($build);
    return $build;
  }

  /**
   * Wraps an ACULTA activity form in the learning room shell.
   *
   * @param \Drupal\aculta_studio\Learning\CourseJourney $journey
   *   Authorized journey data.
   * @param int $lesson_delta
   *   Requested lesson delta.
   * @param int $activity_delta
   *   Requested activity delta.
   * @param array<string, mixed> $content
   *   Original page content, including the LMS Form API form.
   *
   * @return array<string, mixed>
   *   Render array.
   */
  public function buildRoom(CourseJourney $journey, int $lesson_delta, int $activity_delta, array $content): array {
    $lesson = $journey->lessonByDelta($lesson_delta);
    $lesson_label = $lesson?->label ?? $this->t('Aula');
    $position = $journey->lessonPosition($lesson_delta);
    $subtitle = $position === NULL
      ? $journey->courseLabel
      : (string) $this->t('@course · Aula @position de @total', [
        '@course' => $journey->courseLabel,
        '@position' => $position,
        '@total' => $journey->totalLessons,
      ]);

    $back_url = Url::fromRoute('entity.group.canonical', ['group' => $journey->courseId]);
    $back_url->setOption('attributes', ['data-portal-ui' => 'aculta-learning-room__back-link']);

    $build = [
      '#type' => 'container',
      '#attributes' => ['data-portal-ui' => 'aculta-learning-room'],
      'header' => [
        '#theme' => 'aculta_portal_component',
        '#name' => 'context-header',
        '#props' => [
          'eyebrow' => (string) $this->t('ACULTA Studio Ganjalab'),
          'title' => (string) $lesson_label,
          'subtitle' => $subtitle,
        ],
        '#slots' => [
          'back_link' => Link::fromTextAndUrl($this->t('Voltar ao curso'), $back_url)->toRenderable(),
        ],
      ],
      'layout' => [
        '#type' => 'container',
        '#attributes' => ['data-portal-ui' => 'aculta-learning-room__layout'],
        'outline_mobile' => [
          '#type' => 'details',
          '#title' => $this->t('Conteúdo do curso'),
          '#open' => FALSE,
          '#attributes' => ['data-portal-ui' => 'aculta-learning-room__outline', 'data-variant' => 'mobile'],
          'items' => $this->buildLessonList($journey, $lesson_delta),
        ],
        'outline_desktop' => [
          '#type' => 'container',
          '#attributes' => [
            'data-portal-ui' => 'aculta-learning-room__outline', 'data-variant' => 'desktop',
            'role' => 'navigation',
            'aria-label' => $this->t('Conteúdo do curso'),
          ],
          'title' => [
            '#type' => 'html_tag',
            '#tag' => 'h2',
            '#value' => $this->t('Conteúdo do curso'),
            '#attributes' => ['data-portal-ui' => 'aculta-learning-room__outline-title'],
          ],
          'items' => $this->buildLessonList($journey, $lesson_delta),
        ],
        'content' => $content,
      ],
    ];
    CacheableMetadata::createFromRenderArray($build)
      ->addCacheableDependency($journey->cache)
      ->applyTo($build);
    return $build;
  }

  /**
   * Builds the course summary with progress and current position.
   *
   * @param \Drupal\aculta_studio\Learning\CourseJourney $journey
   *   Authorized journey data.
   *
   * @return array<string, mixed>
   *   Render array.
   */
  private function buildSummary(CourseJourney $journey): array {
    $summary = [
      '#type' => 'container',
      '#attributes' => ['data-portal-ui' => 'aculta-learning-journey__summary'],
      'progress' => $this->buildProgress($journey),
    ];
    if ($journey->currentLessonLabel !== NULL && $journey->currentLessonIndex !== NULL) {
      $summary['current'] = [
        '#type' => 'html_tag',
        '#tag' => 'p',
        '#value' => $this->t('Aula atual: @lesson (@position de @total)', [
          '@lesson' => $journey->currentLessonLabel,
          '@position' => $journey->currentLessonIndex,
          '@total' => $journey->totalLessons,
        ]),
        '#attributes' => ['data-portal-ui' => 'aculta-learning-journey__current'],
      ];
    }
    return $summary;
  }

  /**
   * Builds the ordered curriculum section.
   *
   * @param \Drupal\aculta_studio\Learning\CourseJourney $journey
   *   Authorized journey data.
   *
   * @return array<string, mixed>
   *   Render array.
   */
  private function buildJourneyOutline(CourseJourney $journey): array {
    $outline = [
      '#type' => 'container',
      '#attributes' => ['data-portal-ui' => 'aculta-learning-journey__outline'],
      'title' => [
        '#type' => 'html_tag',
        '#tag' => 'h2',
        '#value' => $this->t('Aulas do curso'),
        '#attributes' => ['data-portal-ui' => 'aculta-learning-journey__title'],
      ],
    ];
    if ($journey->lessons === []) {
      $outline['empty'] = [
        '#theme' => 'aculta_portal_component',
        '#name' => 'empty-state',
        '#props' => ['compact' => TRUE],
        '#slots' => [
          'message' => ['#plain_text' => $this->t('Este curso ainda não tem aulas publicadas.')],
        ],
      ];
      return $outline;
    }
    $outline['list'] = $this->buildLessonList($journey, NULL);
    return $outline;
  }

  /**
   * Builds the ordered lesson list used by the journey and the room.
   *
   * @param \Drupal\aculta_studio\Learning\CourseJourney $journey
   *   Authorized journey data.
   * @param int|null $current_delta
   *   Current route lesson delta, when on the room.
   *
   * @return array<string, mixed>
   *   Render array.
   */
  private function buildLessonList(CourseJourney $journey, ?int $current_delta): array {
    $items = [];
    foreach ($journey->lessons as $lesson) {
      $items[] = $this->buildLessonItem($lesson, $current_delta);
    }
    return [
      '#theme' => 'item_list',
      '#list_type' => 'ol',
      '#items' => $items,
      '#attributes' => ['data-portal-ui' => 'aculta-learning-journey__lessons'],
    ];
  }

  /**
   * Builds one lesson item with a state that never relies on color alone.
   *
   * @param \Drupal\aculta_studio\Learning\CourseJourneyLesson $lesson
   *   Authorized lesson data.
   * @param int|null $current_delta
   *   Current route lesson delta.
   *
   * @return array<string, mixed>
   *   Render array.
   */
  private function buildLessonItem(CourseJourneyLesson $lesson, ?int $current_delta): array {
    $state = $lesson->state;
    if (!$lesson->allowed && $state !== 'finished') {
      $state = 'locked';
    }
    $is_route_current = $current_delta !== NULL && $lesson->delta === $current_delta;

    $item = [
      '#type' => 'container',
      '#attributes' => [
        'data-portal-ui' => 'aculta-learning-journey__lesson',
        'data-state' => $state,
        'data-route-current' => $is_route_current ? 'true' : NULL,
      ],
    ];
    if ($lesson->url instanceof Url) {
      $url = $lesson->url;
      if ($is_route_current) {
        $url->setOption('attributes', ['aria-current' => 'page']);
      }
      $item['link'] = Link::fromTextAndUrl($lesson->label, $url)->toRenderable();
    }
    else {
      $item['label'] = [
        '#type' => 'html_tag',
        '#tag' => 'span',
        '#value' => Html::escape($lesson->label),
        '#attributes' => ['data-portal-ui' => 'aculta-learning-journey__lesson-label'],
      ];
    }
    $item['state'] = [
      '#type' => 'html_tag',
      '#tag' => 'span',
      '#value' => $this->stateLabel($state),
      '#attributes' => [
        'data-portal-ui' => 'aculta-learning-journey__state',
        'data-state' => $state,
      ],
    ];
    return $item;
  }

  /**
   * Returns the localized textual state for one lesson.
   */
  private function stateLabel(string $state): string {
    return match ($state) {
      'finished' => (string) $this->t('Concluída'),
      'current' => (string) $this->t('Aula atual'),
      'in_progress' => (string) $this->t('Em andamento'),
      'locked' => (string) $this->t('Ainda não liberada'),
      default => (string) $this->t('Não iniciada'),
    };
  }

  /**
   * Builds course progress without inventing a percentage for empty courses.
   *
   * @param \Drupal\aculta_studio\Learning\CourseJourney $journey
   *   Authorized journey data.
   *
   * @return array<string, mixed>
   *   Render array.
   */
  private function buildProgress(CourseJourney $journey): array {
    $total = $journey->totalLessons;
    if ($total <= 0) {
      return [
        '#type' => 'html_tag',
        '#tag' => 'p',
        '#value' => $this->t('Este curso ainda não tem aulas publicadas.'),
        '#attributes' => ['data-portal-ui' => 'aculta-learning-journey__meta'],
      ];
    }
    $completed = $journey->completedLessons;
    $percent = $journey->progressPercent;
    return [
      '#type' => 'container',
      '#attributes' => ['data-portal-ui' => 'aculta-learning-journey__progress'],
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

}
