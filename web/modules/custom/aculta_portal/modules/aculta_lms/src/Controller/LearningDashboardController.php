<?php

declare(strict_types=1);

namespace Drupal\aculta_lms\Controller;

use Drupal\aculta_lms\LearningDashboardManager;
use Drupal\aculta_lms\Deadline\ClassActivityDeadlineManager;
use Drupal\Core\Datetime\DateFormatterInterface;
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
  ) {
    $this->stringTranslation = $stringTranslation;
  }

  /**
   * Student view: own courses, progress, pending evaluations and certificates. */
  public function student(): array {
    $data = $this->dashboards->studentDashboard($this->currentUser);
    $build = [
      '#type' => 'container',
      'courses_title' => ['#type' => 'html_tag', '#tag' => 'h2', '#value' => $this->t('Meus cursos')],
      '#cache' => ['max-age' => 0],
    ];
    $course_items = [];
    foreach ($data['courses'] as $course) {
      $item = [
        'title' => [
          '#type' => 'html_tag',
          '#tag' => 'h3',
          '#value' => Html::escape((string) $course['label']),
        ],
        'progress' => [
          '#type' => 'container',
          'label' => [
            '#plain_text' => $this->t('@completed de @total aulas concluídas (@percent%)', [
              '@completed' => $course['completed_lessons'],
              '@total' => $course['total_lessons'],
              '@percent' => $course['progress_percent'],
            ]),
          ],
          'bar' => [
            '#type' => 'html_tag',
            '#tag' => 'progress',
            '#value' => '',
            '#attributes' => [
              'max' => 100,
              'value' => $course['progress_percent'],
              'aria-label' => $this->t('Progresso do curso'),
            ],
          ],
        ],
      ];
      if ($course['pending_evaluation']) {
        $item['pending'] = ['#plain_text' => $this->t('Avaliação pendente')];
      }
      if ($course['url'] instanceof Url) {
        $item['link'] = Link::fromTextAndUrl($this->t('Acessar curso'), $course['url'])->toRenderable();
      }
      $course_items[] = $item;
    }
    $build['courses'] = $course_items === []
      ? ['#plain_text' => $this->t('Você ainda não está matriculado em nenhum curso.')]
      : ['#theme' => 'item_list', '#items' => $course_items];

    $build['certificates_title'] = ['#type' => 'html_tag', '#tag' => 'h2', '#value' => $this->t('Meus certificados')];
    $certificate_items = [];
    foreach ($data['certificates'] as $certificate) {
      if (!$certificate instanceof CourseCertificate) {
        continue;
      }
      $valid = $certificate->get('state')->value === 'active';
      $certificate_items[] = [
        'course' => ['#plain_text' => (string) $certificate->get('course_title')->value],
        'state' => ['#plain_text' => $valid ? $this->t('Válido') : $this->t('Revogado')],
        'issued' => [
          '#plain_text' => $this->t('Emitido em @date', [
            '@date' => $this->dateFormatter->format((int) $certificate->get('issued')->value, 'short'),
          ]),
        ],
        'verify' => Link::fromTextAndUrl($this->t('Ver certificado'), Url::fromRoute('aculta_lms.certificate_verify', [
          'codigo' => $certificate->get('verification_code')->value,
        ]))->toRenderable(),
      ];
    }
    $build['certificates'] = $certificate_items === []
      ? ['#plain_text' => $this->t('Nenhum certificado emitido ainda.')]
      : ['#theme' => 'item_list', '#items' => $certificate_items];
    $data['cache']->applyTo($build);
    return $build;
  }

  /**
   * Educator view: status counts for one class, with no student answer data. */
  public function class(GroupInterface $group): array {
    $data = $this->dashboards->classDashboard($group, $this->currentUser);
    if ($data === NULL) {
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
          Link::fromTextAndUrl(
            $timestamp === NULL ? $this->t('Definir prazo') : $this->t('Alterar prazo'),
            Url::fromRoute('aculta_lms.class_activity_deadline', [
              'group' => $group->id(),
              'activity' => $activity->id(),
            ]),
          )->toRenderable(),
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
      '#cache' => ['max-age' => 0],
    ];
    $data['cache']->applyTo($build);
    return $build;
  }

  /**
   * Returns the current educator's account timezone. */
  private function educatorTimezone(): string {
    $account = $this->entityTypeManager->getStorage('user')->load($this->currentUser->id());
    return $account instanceof UserInterface && $account->getTimeZone() ? $account->getTimeZone() : 'UTC';
  }

}
