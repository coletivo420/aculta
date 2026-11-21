<?php

declare(strict_types=1);

namespace Drupal\aculta_lms\Controller;

use Drupal\aculta_lms\LearningDashboardManager;
use Drupal\aculta_lms\Deadline\ClassActivityDeadlineManager;
use Drupal\aculta_lms\Learning\ClassStatusHistory;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Component\Utility\Html;
use Drupal\Core\Link;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\Core\Url;
use Drupal\group\Entity\GroupInterface;
use Drupal\group\Entity\GroupRelationshipInterface;
use Drupal\aculta_lms\Entity\ActivityEntity;
use Drupal\aculta_lms\Entity\LessonEntity;
use Drupal\user\UserInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Builds student and educator LMS dashboard presentations.
 */
final class LearningDashboardController {

  use StringTranslationTrait;

  public function __construct(
    private readonly LearningDashboardManager $dashboards,
    private readonly ClassActivityDeadlineManager $deadlines,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly AccountProxyInterface $currentUser,
    private readonly DateFormatterInterface $dateFormatter,
    TranslationInterface $stringTranslation,
    private readonly ?ClassStatusHistory $classStatusHistory = NULL,
    private readonly ?RequestStack $requestStack = NULL,
  ) {
    $this->stringTranslation = $stringTranslation;
  }

  /**
   * Educator view: status counts for one class, with no student answer data.
   *
   * @return array<array-key, mixed>
   *   Render array for the class dashboard or its unavailable state.
   */
  public function class(GroupInterface $group, GroupInterface $course): array {
    $data = $this->dashboards->classDashboard($group, $this->currentUser);
    if ($data === NULL || (string) $data['course']->id() !== (string) $course->id()) {
      return ['#plain_text' => $this->t('Turma indisponível.')];
    }
    $activityRows = [];
    $seenActivities = [];
    $cacheability = new CacheableMetadata();
    $lessons = $this->entityTypeManager->getStorage('aculta_lms_lesson')->loadByProperties([
      'course_id' => (int) $course->id(),
      'published' => TRUE,
    ]);
    $lessons = array_values(array_filter(
      $lessons,
      static fn($lesson): bool => $lesson instanceof LessonEntity,
    ));
    usort($lessons, static fn(LessonEntity $a, LessonEntity $b): int =>
      [self::integerSortValue($a->get('weight')->value), self::integerSortValue($a->id())]
      <=> [self::integerSortValue($b->get('weight')->value), self::integerSortValue($b->id())]
    );
    foreach ($lessons as $lesson) {
      $cacheability->addCacheableDependency($lesson);
      $activities = $this->entityTypeManager->getStorage('aculta_lms_activity')->loadByProperties([
        'lesson_id' => (int) $lesson->id(),
      ]);
      $activities = array_values(array_filter(
        $activities,
        static fn($activity): bool => $activity instanceof ActivityEntity,
      ));
      usort($activities, static fn(ActivityEntity $a, ActivityEntity $b): int =>
        [self::integerSortValue($a->get('weight')->value), self::integerSortValue($a->id())]
        <=> [self::integerSortValue($b->get('weight')->value), self::integerSortValue($b->id())]
      );
      foreach ($activities as $activity) {
        $activityId = (string) $activity->id();
        if (isset($seenActivities[$activityId])) {
          continue;
        }
        $seenActivities[$activityId] = TRUE;
        $cacheability->addCacheableDependency($activity);
        $deadline = $this->deadlines->deadlineForClass($group, $activity);
        $timestamp = NULL;
        if ($deadline instanceof GroupRelationshipInterface && !$deadline->get('field_activity_due_at')->isEmpty()) {
          $dateValue = $deadline->get('field_activity_due_at')->value;
          if (is_string($dateValue) && $dateValue !== '') {
            $date = new \DateTimeImmutable($dateValue, new \DateTimeZone('UTC'));
            $timestamp = $date->getTimestamp();
          }
        }
        $activityType = $activity->get('type')->value;
        $activityLabel = is_string($activityType) ? str_replace('_', ' ', $activityType) : '';
        $activityRows[] = [
          ['data' => ['#plain_text' => (string) $this->t('Atividade @type', ['@type' => $activityLabel])]],
          [
            'data' => [
              '#plain_text' => $timestamp === NULL
                ? $this->t('Sem prazo')
                : $this->dateFormatter->format($timestamp, 'long', '', $this->educatorTimezone()),
            ],
          ],
          [
            'data' => Link::fromTextAndUrl(
              $timestamp === NULL ? $this->t('Definir prazo') : $this->t('Alterar prazo'),
              Url::fromRoute('aculta_lms.class_activity_deadline', [
                'course' => $course->id(),
                'group' => $group->id(),
                'activity' => $activity->id(),
              ]),
            )->toRenderable(),
          ],
        ];
      }
    }
    $rows = [
      [$this->t('Estudantes matriculados'), $data['students']],
      [$this->t('Ainda não iniciaram'), $data['not_started']],
      [$this->t('Em andamento'), $data['in_progress']],
      [$this->t('Aguardando avaliação'), $data['pending_evaluation']],
      [$this->t('Concluíram com aprovação'), $data['passed']],
      [$this->t('Não concluíram'), $data['failed']],
    ];
    $period = ClassStatusHistory::normalizePeriod(
      $this->requestStack?->getCurrentRequest()?->query->get('dias'),
    );
    $history = $this->classStatusHistory?->series((int) $course->id(), (int) $group->id(), $period);
    $historyRows = [];
    if ($history !== NULL) {
      foreach ($history['points'] as $point) {
        $historyRows[] = [
          $this->dateFormatter->format($point['day'] * 86400, 'custom', 'Y-m-d', 'UTC'),
          ...array_map(static fn(string $key): int => $point['counts'][$key], ClassStatusHistory::SITUATIONS),
        ];
      }
    }
    $build = [
      '#type' => 'container',
      'course' => [
        '#type' => 'html_tag',
        '#tag' => 'p',
        '#value' => Html::escape((string) $this->t('Curso: @course', [
          '@course' => $data['course']->label() ?? '',
        ])),
      ],
      'metrics' => [
        '#type' => 'table',
        '#header' => [$this->t('Indicador'), $this->t('Quantidade')],
        '#rows' => $rows,
        '#empty' => $this->t('Ainda não há estudantes nesta turma.'),
      ],
      'status_history' => [
        '#type' => 'container',
        'title' => [
          '#type' => 'html_tag',
          '#tag' => 'h2',
          '#value' => $this->t('Histórico diário da turma'),
        ],
        'period' => $this->buildHistoryPeriodSelector($course, $group, $period),
        'table' => $history === NULL
          ? ['#plain_text' => $this->t('O histórico diário não está disponível neste site.')]
          : (!$history['has_data']
            ? ['#plain_text' => $this->t('Não há instantâneos registrados neste período.')]
            : [
              '#type' => 'table',
              '#caption' => $this->t('Situação agregada por dia nos últimos @days dias', ['@days' => $history['days']]),
              '#header' => [
                $this->t('Dia (UTC)'),
                $this->t('Estudantes'),
                $this->t('Não iniciaram'),
                $this->t('Em andamento'),
                $this->t('Aguardando avaliação'),
                $this->t('Aprovados'),
                $this->t('Não concluíram'),
              ],
              '#rows' => $historyRows,
            ]),
      ],
      'activity_deadlines' => [
        '#type' => 'table',
        '#caption' => $this->t('Prazos por atividade desta turma'),
        '#header' => [$this->t('Atividade'), $this->t('Prazo'), $this->t('Ação')],
        '#rows' => $activityRows,
        '#empty' => $this->t('Este curso ainda não tem atividades publicadas.'),
      ],
      'roster_settings' => Link::fromTextAndUrl(
        $this->t('Lista de participantes'),
        Url::fromRoute('aculta_lms.class_roster_settings', [
          'course' => $course->id(),
          'group' => $group->id(),
        ]),
      )->toRenderable(),
      'answer_reviews' => Link::fromTextAndUrl(
        $this->t('Ver respostas e avaliações'),
        Url::fromRoute('aculta_lms.answer_reviews', [
          'course' => $course->id(),
          'group' => $group->id(),
        ]),
      )->toRenderable(),
      '#cache' => ['max-age' => 0],
    ];
    CacheableMetadata::createFromRenderArray($build)
      ->addCacheableDependency($data['cache'])
      ->addCacheableDependency($cacheability)
      ->applyTo($build);
    return $build;
  }

  /**
   * Builds no-JavaScript links for the allowed history periods.
   *
   * @return array<string, mixed>
   *   Tabs component render array.
   */
  private function buildHistoryPeriodSelector(GroupInterface $course, GroupInterface $class, int $activePeriod): array {
    $options = [];
    foreach (ClassStatusHistory::PERIODS as $days) {
      $link = Link::fromTextAndUrl(
        $this->t('@days dias', ['@days' => $days]),
        Url::fromRoute('aculta_lms.class_dashboard', [
          'course' => $course->id(),
          'group' => $class->id(),
        ], ['query' => ['dias' => $days]]),
      )->toRenderable();
      if ($days === $activePeriod) {
        $link['#attributes'] = [
          'class' => ['is-active'],
          'aria-current' => 'page',
        ];
      }
      $options[] = $link;
    }
    return [
      '#theme' => 'portal_component',
      '#name' => 'tabs',
      '#props' => ['label' => (string) $this->t('Período da atividade')],
      '#slots' => [
        'items' => [
          '#theme' => 'item_list',
          '#items' => $options,
        ],
      ],
    ];
  }

  /**
   * Returns the current educator's account timezone. */
  private function educatorTimezone(): string {
    $account = $this->entityTypeManager->getStorage('user')->load($this->currentUser->id());
    return $account instanceof UserInterface && $account->getTimeZone() ? $account->getTimeZone() : 'UTC';
  }

  /**
   * Converts valid integer field values and entity IDs for stable sorting.
   *
   * Malformed values sort as zero, matching the historical integer cast for
   * empty values while avoiding coercion of arbitrary mixed input.
   */
  private static function integerSortValue(mixed $value): int {
    if (is_int($value)) {
      return $value;
    }
    if (is_string($value) && preg_match('/^-?\d+$/D', $value) === 1) {
      return (int) $value;
    }
    return 0;
  }

}
