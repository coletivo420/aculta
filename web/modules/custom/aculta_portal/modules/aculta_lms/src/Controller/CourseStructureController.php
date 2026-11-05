<?php

declare(strict_types=1);

namespace Drupal\aculta_lms\Controller;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Link;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\Core\Url;
use Drupal\group\Entity\GroupInterface;

/**
 * Presents the educator's course-owned lessons and activities.
 */
final class CourseStructureController {

  use StringTranslationTrait;

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    TranslationInterface $stringTranslation,
    private readonly ?FormBuilderInterface $formBuilder = NULL,
  ) {
    $this->stringTranslation = $stringTranslation;
  }

  /**
   * Builds a course curriculum page from ACULTA-owned learning entities. */
  public function structure(GroupInterface $course): array {
    $lessonStorage = $this->entityTypeManager->getStorage('aculta_lms_lesson');
    $lessonIds = $lessonStorage->getQuery()
      ->accessCheck(FALSE)
      ->condition('course_id', (int) $course->id())
      ->sort('weight')
      ->sort('id')
      ->execute();
    $loadedLessons = $lessonStorage->loadMultiple($lessonIds);
    $lessons = array_map(static fn($id) => $loadedLessons[$id], $lessonIds);
    $activityStorage = $this->entityTypeManager->getStorage('aculta_lms_activity');
    $lessonCards = [];
    $activityCount = 0;
    $publishedCount = 0;
    foreach ($lessons as $lessonIndex => $lesson) {
      $activityIds = $activityStorage->getQuery()
        ->accessCheck(FALSE)
        ->condition('lesson_id', (int) $lesson->id())
        ->sort('weight')
        ->sort('id')
        ->execute();
      $loadedActivities = $activityStorage->loadMultiple($activityIds);
      $activities = array_map(static fn($id) => $loadedActivities[$id], $activityIds);
      $activityCards = [];
      foreach ($activities as $activityIndex => $activity) {
        $activityCount++;
        $question = $this->activityQuestion((string) $activity->get('answer_config')->value);
        $answerCount = $this->entityTypeManager->getStorage('aculta_lms_answer')->getQuery()
          ->accessCheck(FALSE)
          ->condition('activity_id', (int) $activity->id())
          ->count()
          ->execute();
        $activityActions = [
          '#type' => 'container',
          '#attributes' => ['data-portal-ui' => 'aculta-lms-course-builder--actions'],
          'move_up' => $activityIndex > 0 ? Link::fromTextAndUrl(
            $this->t('Mover acima'),
            Url::fromRoute('aculta_lms.activity_move', [
              'course' => $course->id(),
              'lesson' => $lesson->id(),
              'activity' => $activity->id(),
              'direcao' => 'acima',
            ]),
          )->toRenderable() : [],
          'move_down' => $activityIndex < count($activities) - 1 ? Link::fromTextAndUrl(
            $this->t('Mover abaixo'),
            Url::fromRoute('aculta_lms.activity_move', [
              'course' => $course->id(),
              'lesson' => $lesson->id(),
              'activity' => $activity->id(),
              'direcao' => 'abaixo',
            ]),
          )->toRenderable() : [],
          'delete' => Link::fromTextAndUrl(
            $this->t('Excluir'),
            Url::fromRoute('aculta_lms.activity_delete', [
              'course' => $course->id(),
              'lesson' => $lesson->id(),
              'activity' => $activity->id(),
            ]),
          )->toRenderable(),
        ];
        $activityCards[] = [
          '#type' => 'container',
          '#attributes' => [
            'data-portal-ui' => 'aculta-lms-course-builder--activity',
            'data-aculta-sort-item' => (string) $activity->id(),
          ],
          'sort_handle' => [
            '#type' => 'html_tag',
            '#tag' => 'span',
            '#value' => $this->t('Arrastar para reordenar'),
            '#attributes' => [
              'data-portal-ui' => 'aculta-lms-course-builder--sort-handle',
              'data-aculta-sort-handle' => TRUE,
              'draggable' => 'true',
              'aria-hidden' => 'true',
            ],
          ],
          'title' => $answerCount === 0
            ? Link::fromTextAndUrl(
              $this->activityLabel((string) $activity->get('type')->value),
              Url::fromRoute('aculta_lms.activity_edit', [
                'course' => $course->id(),
                'lesson' => $lesson->id(),
                'activity' => $activity->id(),
              ]),
            )->toRenderable()
            : ['#plain_text' => $this->activityLabel((string) $activity->get('type')->value)],
          'question' => ['#plain_text' => $question !== '' ? $question : $this->t('Sem enunciado')],
          'answers' => $answerCount > 0
            ? ['#plain_text' => $this->t('@count resposta(s); edição bloqueada para preservar o histórico.', ['@count' => $answerCount])]
            : [],
          'actions' => $activityActions,
        ];
      }
      $published = (bool) $lesson->get('published')->value;
      if ($published) {
        $publishedCount++;
      }
      $lessonActions = [
        '#type' => 'container',
        '#attributes' => ['data-portal-ui' => 'aculta-lms-course-builder--actions'],
        'add_activity' => Link::fromTextAndUrl(
          $this->t('Adicionar atividade'),
          Url::fromRoute('aculta_lms.activity_add', ['course' => $course->id(), 'lesson' => $lesson->id()]),
        )->toRenderable(),
        'move_up' => $lessonIndex > 0 ? Link::fromTextAndUrl(
          $this->t('Mover aula acima'),
          Url::fromRoute('aculta_lms.lesson_move', [
            'course' => $course->id(),
            'lesson' => $lesson->id(),
            'direcao' => 'acima',
          ]),
        )->toRenderable() : [],
        'move_down' => $lessonIndex < count($lessons) - 1 ? Link::fromTextAndUrl(
          $this->t('Mover aula abaixo'),
          Url::fromRoute('aculta_lms.lesson_move', [
            'course' => $course->id(),
            'lesson' => $lesson->id(),
            'direcao' => 'abaixo',
          ]),
        )->toRenderable() : [],
        'delete' => Link::fromTextAndUrl(
          $this->t('Excluir aula'),
          Url::fromRoute('aculta_lms.lesson_delete', ['course' => $course->id(), 'lesson' => $lesson->id()]),
        )->toRenderable(),
      ];
      $activityList = $activityCards === []
        ? ['#plain_text' => $this->t('Nenhuma atividade nesta aula.')]
        : [
          '#theme' => 'item_list',
          '#list_type' => 'ul',
          '#items' => $activityCards,
          '#attributes' => ['data-portal-ui' => 'aculta-lms-course-builder--activities'],
        ];
      if (count($activityCards) > 1 && $this->formBuilder !== NULL) {
        $activityList['#attributes']['data-aculta-sort-list'] = 'activities';
        $activityList['#attributes']['data-aculta-sort-form'] = 'aculta-course-order-activities-' . $lesson->id();
        $activityOrderForm = $this->formBuilder->getForm(
          \Drupal\aculta_lms\Form\CourseStructureOrderForm::class,
          $course,
          'activities',
          (int) $lesson->id(),
          array_map('intval', $activityIds),
        );
      }
      else {
        $activityOrderForm = [];
      }
      $lessonCards[] = [
        '#type' => 'container',
        '#attributes' => [
          'data-portal-ui' => 'aculta-lms-course-builder--lesson',
          'data-aculta-lms-lesson' => (string) $lesson->id(),
          'data-aculta-sort-item' => (string) $lesson->id(),
        ],
        'heading' => [
          '#type' => 'container',
          '#attributes' => ['data-portal-ui' => 'aculta-lms-course-builder--lesson-heading'],
          'title' => Link::fromTextAndUrl(
            (string) $lesson->get('title')->value,
            Url::fromRoute('aculta_lms.lesson_edit', ['course' => $course->id(), 'lesson' => $lesson->id()]),
          )->toRenderable(),
          'sort_handle' => [
            '#type' => 'html_tag',
            '#tag' => 'span',
            '#value' => $this->t('Arrastar para reordenar'),
            '#attributes' => [
              'data-portal-ui' => 'aculta-lms-course-builder--sort-handle',
              'data-aculta-sort-handle' => TRUE,
              'draggable' => 'true',
              'aria-hidden' => 'true',
            ],
          ],
          'preview' => Link::fromTextAndUrl(
            $this->t('Prévia de estudante'),
            Url::fromRoute('aculta_lms.lesson_preview', ['course' => $course->id(), 'lesson' => $lesson->id()]),
          )->toRenderable(),
          'state' => [
            '#type' => 'html_tag',
            '#tag' => 'span',
            '#value' => $published ? $this->t('Publicada') : $this->t('Rascunho'),
            '#attributes' => ['data-portal-ui' => 'aculta-lms-course-builder--state ' . ($published ? 'aculta-lms-course-builder--published' : 'aculta-lms-course-builder--draft')],
          ],
        ],
        'activities_heading' => [
          '#type' => 'html_tag',
          '#tag' => 'h3',
          '#value' => $this->t('Atividades (@count)', ['@count' => count($activities)]),
        ],
        'activities' => $activityList,
        'activity_order_form' => $activityOrderForm,
        'actions' => $lessonActions,
      ];
    }

    $lessonList = $lessonCards === []
      ? [
        '#type' => 'container',
        '#attributes' => ['data-portal-ui' => 'aculta-lms-course-builder--empty'],
        'message' => ['#plain_text' => $this->t('Este curso ainda não tem aulas.')],
        'add_lesson' => Link::fromTextAndUrl(
          $this->t('Criar a primeira aula'),
          Url::fromRoute('aculta_lms.lesson_add', ['course' => $course->id()]),
        )->toRenderable(),
      ]
      : [
        '#theme' => 'item_list',
        '#list_type' => 'ol',
        '#items' => $lessonCards,
        '#attributes' => ['data-portal-ui' => 'aculta-lms-course-builder--lessons'],
      ];
    $lessonOrderForm = [];
    if (count($lessonCards) > 1 && $this->formBuilder !== NULL) {
      $lessonList['#attributes']['data-aculta-sort-list'] = 'lessons';
      $lessonList['#attributes']['data-aculta-sort-form'] = 'aculta-course-order-lessons-' . $course->id();
      $lessonOrderForm = $this->formBuilder->getForm(
        \Drupal\aculta_lms\Form\CourseStructureOrderForm::class,
        $course,
        'lessons',
        (int) $course->id(),
        array_map('intval', $lessonIds),
      );
    }

    return [
      '#type' => 'container',
      '#attributes' => ['data-aculta-lms' => 'course-structure'],
      '#attached' => ['library' => ['aculta_lms/course_structure']],
      '#cache' => ['contexts' => ['user', 'user.permissions', 'user.group_permissions'], 'max-age' => 0],
      'header' => [
        '#type' => 'container',
        '#attributes' => ['data-portal-ui' => 'aculta-lms-course-builder--header'],
        'back' => Link::fromTextAndUrl(
          $this->t('Meus cursos'),
          Url::fromRoute('aculta_lms.educator_dashboard'),
        )->toRenderable(),
        'title' => ['#type' => 'html_tag', '#tag' => 'h1', '#value' => $course->label()],
        'intro' => ['#plain_text' => $this->t('Organize as aulas e atividades do curso. Matrículas e prazos ficam nas turmas.')],
      ],
      'summary' => [
        '#type' => 'container',
        '#attributes' => [
          'data-portal-ui' => 'aculta-lms-course-builder--summary',
          'aria-label' => $this->t('Resumo da estrutura'),
        ],
        'lessons' => ['#plain_text' => $this->formatPlural(count($lessons), '1 aula', '@count aulas')],
        'activities' => ['#plain_text' => $this->formatPlural($activityCount, '1 atividade', '@count atividades')],
        'published' => ['#plain_text' => $this->formatPlural($publishedCount, '1 aula publicada', '@count aulas publicadas')],
      ],
      'actions' => [
        '#type' => 'container',
        '#attributes' => ['data-portal-ui' => 'aculta-lms-course-builder--actions'],
        'add_lesson' => Link::fromTextAndUrl(
          $this->t('Adicionar aula'),
          Url::fromRoute('aculta_lms.lesson_add', ['course' => $course->id()]),
        )->toRenderable(),
      ],
      'lessons_heading' => ['#type' => 'html_tag', '#tag' => 'h2', '#value' => $this->t('Estrutura do curso')],
      'lessons' => $lessonList,
      'lesson_order_form' => $lessonOrderForm,
    ];
  }

  /**
   * Gets the plain-text question stored in an activity configuration.
   */
  private function activityQuestion(string $json): string {
    try {
      $config = json_decode($json, TRUE, 512, JSON_THROW_ON_ERROR);
      return is_array($config) && is_string($config['question'] ?? NULL) ? $config['question'] : '';
    }
    catch (\JsonException) {
      return '';
    }
  }

  /**
   * Gets the translated label for a supported activity type.
   */
  private function activityLabel(string $type): string {
    return match ($type) {
      'true_false' => (string) $this->t('Verdadeiro ou falso'),
      'select' => (string) $this->t('Seleção'),
      'fill_blanks' => (string) $this->t('Preencher lacunas'),
      'free_text' => (string) $this->t('Resposta discursiva'),
      'file_upload' => (string) $this->t('Envio de arquivo'),
      default => (string) $this->t('Atividade'),
    };
  }

}
