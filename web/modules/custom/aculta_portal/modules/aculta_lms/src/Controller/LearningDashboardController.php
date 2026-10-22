<?php

declare(strict_types=1);

namespace Drupal\aculta_lms\Controller;

use Drupal\aculta_lms\LearningDashboardManager;
use Drupal\aculta_lms\Deadline\ClassActivityDeadlineManager;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\EntityPublishedInterface;
use Drupal\Component\Utility\Html;
use Drupal\Core\Link;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\Core\Url;
use Drupal\group\Entity\GroupInterface;
use Drupal\group\Entity\GroupRelationshipInterface;
use Drupal\aculta_lms\Entity\CourseCertificate;
use Drupal\lms\Entity\Bundle\CourseInterface;
use Drupal\user\UserInterface;

/**
 * Builds student and educator LMS dashboard presentations. */
final class LearningDashboardController {

  use StringTranslationTrait;

  public function __construct(
    private readonly LearningDashboardManager $dashboards,
    private readonly ClassActivityDeadlineManager $deadlines,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly AccountProxyInterface $currentUser,
    private readonly DateFormatterInterface $dateFormatter,
    TranslationInterface $stringTranslation,
    private readonly \\Drupal\\aculta_studio\\Learning\\LearnerDashboardComposer $learnerComposer,
  ) {
    $this->stringTranslation = $stringTranslation;
  }

  /**
   * Student view: own courses, progress, pending evaluations and certificates. */
  public function student(): array {
    return $this->learnerComposer->build();
  }

  /**
   * Educator view: status counts for one class, with no student answer data. */
  public function class(GroupInterface $group, CourseInterface $course): array {
    $data = $this->dashboards->classDashboard($group, $this->currentUser);
    if ($data === NULL || (string) $data['course']->id() !== (string) $course->id()) {
      return ['#plain_text' => $this->t('Turma indisponível.')];
    }
    $activityRows = [];
    $seenActivities = [];
    foreach ($data['course']->get(CourseInterface::LESSONS)->referencedEntities() as $lesson) {
      if (!$lesson instanceof EntityPublishedInterface || !$lesson->isPublished() || !$lesson->hasField('activities')) {
        continue;
      }
      foreach ($lesson->get('activities')->referencedEntities() as $activity) {
        $activityId = (string) $activity->id();
        if (isset($seenActivities[$activityId]) || !$activity instanceof EntityPublishedInterface || !$activity->isPublished()) {
          continue;
        }
        $seenActivities[$activityId] = TRUE;
        $deadline = $this->deadlines->deadlineForClass($group, $activity);
        $timestamp = NULL;
        if ($deadline instanceof GroupRelationshipInterface && !$deadline->get('field_activity_due_at')->isEmpty()) {
          $date = new \DateTimeImmutable((string) $deadline->get('field_activity_due_at')->value, new \DateTimeZone('UTC'));
          $timestamp = $date->getTimestamp();
        }
        $activityRows[] = [
          ['data' => ['#plain_text' => (string) $activity->label()]],
          [
            'data' => [
              '#plain_text' => $timestamp === NULL
                ? $this->t('Sem prazo')
                : $this->dateFormatter->format($timestamp, 'long', NULL, $this->educatorTimezone()),
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
    $labels = [
      'students' => $this->t('Estudantes matriculados'),
      'not_started' => $this->t('Ainda não iniciaram'),
      'in_progress' => $this->t('Em andamento'),
      'pending_evaluation' => $this->t('Aguardando avaliação'),
      'passed' => $this->t('Concluíram com aprovação'),
      'failed' => $this->t('Não concluíram'),
    ];
    $rows = [];
    foreach ($labels as $key => $label) {
      $rows[] = [$label, (int) $data[$key]];
    }
    $build = [
      '#type' => 'container',
      'course' => [
        '#type' => 'html_tag',
        '#tag' => 'p',
        '#value' => Html::escape((string) $this->t('Curso: @course', [
          '@course' => $data['course']->label(),
        ])),
      ],
      'metrics' => [
        '#type' => 'table',
        '#header' => [$this->t('Indicador'), $this->t('Quantidade')],
        '#rows' => $rows,
        '#empty' => $this->t('Ainda não há estudantes nesta turma.'),
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
      '#cache' => ['max-age' => 0],
    ];
    CacheableMetadata::createFromRenderArray($build)
      ->addCacheableDependency($data['cache'])
      ->applyTo($build);
    return $build;
  }

  /**
   * Returns the current educator's account timezone. */
  private function educatorTimezone(): string {
    $account = $this->entityTypeManager->getStorage('user')->load($this->currentUser->id());
    return $account instanceof UserInterface && $account->getTimeZone() ? $account->getTimeZone() : 'UTC';
  }

}
