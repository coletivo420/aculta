<?php

declare(strict_types=1);

namespace Drupal\aculta_lms\Controller;

use Drupal\Core\Entity\EntityTypeManagerInterface;
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
    $lessons = $lessonStorage->loadMultiple($lessonIds);
    $activityStorage = $this->entityTypeManager->getStorage('aculta_lms_activity');
    $rows = [];
    foreach ($lessons as $lesson) {
      $activityIds = $activityStorage->getQuery()
        ->accessCheck(FALSE)
        ->condition('lesson_id', (int) $lesson->id())
        ->sort('weight')
        ->sort('id')
        ->execute();
      $activities = $activityStorage->loadMultiple($activityIds);
      $activityRows = [];
      foreach ($activities as $activity) {
        $question = $this->activityQuestion((string) $activity->get('answer_config')->value);
        $answerCount = $this->entityTypeManager->getStorage('aculta_lms_answer')->getQuery()
          ->accessCheck(FALSE)
          ->condition('activity_id', (int) $activity->id())
          ->count()
          ->execute();
        $activityRows[] = [
          'data' => [
            $answerCount === 0
              ? Link::fromTextAndUrl(
                $this->activityLabel((string) $activity->get('type')->value),
                Url::fromRoute('aculta_lms.activity_edit', [
                  'course' => $course->id(),
                  'lesson' => $lesson->id(),
                  'activity' => $activity->id(),
                ]),
              )->toRenderable()
              : ['#plain_text' => $this->activityLabel((string) $activity->get('type')->value)],
            ['#plain_text' => $question !== '' ? $question : $this->t('Sem enunciado')],
            ['#plain_text' => (string) $activity->get('weight')->value],
            ['#plain_text' => $answerCount > 0 ? $this->t('@count resposta(s); edição bloqueada para preservar o histórico.', ['@count' => $answerCount]) : ''],
            Link::fromTextAndUrl(
              $this->t('Excluir'),
              Url::fromRoute('aculta_lms.activity_delete', [
                'course' => $course->id(),
                'lesson' => $lesson->id(),
                'activity' => $activity->id(),
              ]),
            )->toRenderable(),
          ],
        ];
      }
      $rows[] = [
        'data' => [
          Link::fromTextAndUrl(
            (string) $lesson->get('title')->value,
            Url::fromRoute('aculta_lms.lesson_edit', ['course' => $course->id(), 'lesson' => $lesson->id()]),
          )->toRenderable(),
          ['#plain_text' => $lesson->get('published')->value ? $this->t('Publicada') : $this->t('Rascunho')],
          ['#plain_text' => (string) $lesson->get('weight')->value],
          [
            'data' => [
              '#type' => 'table',
              '#header' => [
                $this->t('Atividade'),
                $this->t('Enunciado'),
                $this->t('Ordem'),
                $this->t('Respostas'),
                $this->t('Ações'),
              ],
              '#rows' => $activityRows,
              '#empty' => $this->t('Nenhuma atividade nesta aula.'),
            ],
          ],
          [
            'data' => [
              Link::fromTextAndUrl(
                $this->t('Adicionar atividade'),
                Url::fromRoute('aculta_lms.activity_add', ['course' => $course->id(), 'lesson' => $lesson->id()]),
              )->toRenderable(),
              Link::fromTextAndUrl(
                $this->t('Excluir aula'),
                Url::fromRoute('aculta_lms.lesson_delete', ['course' => $course->id(), 'lesson' => $lesson->id()]),
              )->toRenderable(),
            ],
          ],
        ],
      ];
    }

    return [
      '#type' => 'container',
      '#attributes' => ['data-aculta-lms' => 'course-structure'],
      '#cache' => ['contexts' => ['user', 'user.permissions', 'user.group_permissions'], 'max-age' => 0],
      'course' => ['#type' => 'html_tag', '#tag' => 'h1', '#value' => $course->label()],
      'intro' => ['#plain_text' => $this->t('Estrutura do curso. Aulas e atividades são organizadas aqui; matrículas e prazos continuam nas turmas.')],
      'actions' => [
        '#type' => 'container',
        'add_lesson' => Link::fromTextAndUrl(
          $this->t('Adicionar aula'),
          Url::fromRoute('aculta_lms.lesson_add', ['course' => $course->id()]),
        )->toRenderable(),
        'back' => Link::fromTextAndUrl(
          $this->t('Voltar ao Ganjalab Educador'),
          Url::fromRoute('aculta_lms.educator_dashboard'),
        )->toRenderable(),
      ],
      'lessons' => [
        '#type' => 'table',
        '#header' => [
          $this->t('Aula'),
          $this->t('Estado'),
          $this->t('Ordem'),
          $this->t('Atividades'),
          $this->t('Ações'),
        ],
        '#rows' => $rows,
        '#empty' => $this->t('Este curso ainda não tem aulas.'),
      ],
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
