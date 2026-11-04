<?php

declare(strict_types=1);

namespace Drupal\aculta_lms\Controller;

use Drupal\aculta_lms\Access\CourseLessonPreviewAccessCheck;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Link;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\Core\Url;
use Drupal\group\Entity\GroupInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Renders a private student-facing preview without learner data or submissions.
 */
final class CourseLessonPreviewController {

  use StringTranslationTrait;

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly CourseLessonPreviewAccessCheck $previewAccess,
    TranslationInterface $stringTranslation,
  ) {
    $this->stringTranslation = $stringTranslation;
  }

  /**
   * Builds a preview of one lesson and its question prompts.
   */
  public function preview(GroupInterface $course, ContentEntityInterface $lesson): array {
    if (!$this->previewAccess->access($course, $lesson)->isAllowed()) {
      throw new AccessDeniedHttpException();
    }
    $cache = (new CacheableMetadata())
      ->addCacheableDependency($course)
      ->addCacheableDependency($lesson)
      ->addCacheContexts(['user', 'user.permissions', 'user.group_permissions'])
      ->setCacheMaxAge(0);
    $activityIds = $this->entityTypeManager->getStorage('aculta_lms_activity')->getQuery()
      ->accessCheck(FALSE)
      ->condition('lesson_id', (int) $lesson->id())
      ->sort('weight')
      ->sort('id')
      ->execute();
    $activities = $this->entityTypeManager->getStorage('aculta_lms_activity')->loadMultiple($activityIds);
    $items = [];
    foreach ($activities as $activity) {
      if (!$activity instanceof ContentEntityInterface || $activity->getEntityTypeId() !== 'aculta_lms_activity') {
        continue;
      }
      $cache->addCacheableDependency($activity);
      $question = $this->question((string) ($activity->get('answer_config')->value ?? '{}'));
      $items[] = [
        '#type' => 'container',
        '#attributes' => ['class' => ['aculta-lms-lesson-preview__activity']],
        'type' => ['#plain_text' => $this->activityLabel((string) $activity->get('type')->value)],
        'question' => $question === ''
          ? []
          : ['#plain_text' => $question],
      ];
    }

    $build = [
      '#type' => 'container',
      '#attributes' => ['data-aculta-lms' => 'course-preview', 'data-portal-ui' => 'aculta-course-lesson-preview'],
      '#attached' => ['library' => ['aculta_lms/course_structure']],
      'back' => Link::fromTextAndUrl(
        $this->t('Voltar à estrutura do curso'),
        Url::fromRoute('aculta_lms.course_structure', ['course' => $course->id()]),
      )->toRenderable(),
      'label' => ['#plain_text' => $this->t('Prévia de estudante — somente educadores do curso')],
      'title' => [
        '#type' => 'inline_template',
        '#template' => '<h1>{{ title }}</h1>',
        '#context' => ['title' => (string) $lesson->get('title')->value],
      ],
      'body' => trim((string) ($lesson->get('body')->value ?? '')) === ''
        ? ['#plain_text' => $this->t('O conteúdo desta aula ainda não foi adicionado.')]
        : [
          '#type' => 'processed_text',
          '#text' => (string) $lesson->get('body')->value,
          '#format' => (string) ($lesson->get('body')->format ?? 'plain_text'),
          '#langcode' => $lesson->language()->getId(),
        ],
      'activities_heading' => ['#type' => 'html_tag', '#tag' => 'h2', '#value' => $this->t('Atividades')],
      'activities' => $items === []
        ? ['#plain_text' => $this->t('Esta aula ainda não tem atividades.')]
        : ['#theme' => 'item_list', '#items' => $items],
      'note' => ['#plain_text' => $this->t('A prévia não carrega respostas, notas ou dados de estudantes, nem permite enviar respostas.')],
      '#cache' => [],
    ];
    $cache->applyTo($build);
    return $build;
  }

  /**
   * Decodes only the public question prompt from an activity configuration.
   */
  private function question(string $json): string {
    try {
      $config = json_decode($json === '' ? '{}' : $json, TRUE, 512, JSON_THROW_ON_ERROR);
      return is_array($config) && is_string($config['question'] ?? NULL) ? $config['question'] : '';
    }
    catch (\JsonException) {
      return '';
    }
  }

  /**
   * Returns a translated label for a supported activity type.
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
