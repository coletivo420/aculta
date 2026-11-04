<?php

declare(strict_types=1);

namespace Drupal\aculta_lms\Controller;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Link;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\Core\Url;
use Drupal\aculta_lms\Routing\LmsFriendlySlugs;
use Drupal\aculta_lms\Notice\LearningEligibilityInterface;
use Drupal\group\Entity\GroupInterface;
use Drupal\group\Entity\GroupRelationshipInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Renders an authorized lesson and its available activities.
 */
final class CourseLessonController {

  use StringTranslationTrait;

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly LmsFriendlySlugs $slugs,
    private readonly LearningEligibilityInterface $eligibility,
    TranslationInterface $stringTranslation,
  ) {
    $this->stringTranslation = $stringTranslation;
  }

  /**
   * Builds a learner page for one published lesson.
   */
  public function view(GroupInterface $group, int $lesson_delta): array {
    $lesson = $this->slugs->lessonAt($group, $lesson_delta);
    if (!$lesson instanceof ContentEntityInterface
      || $lesson->getEntityTypeId() !== 'aculta_lms_lesson'
      || !(bool) $lesson->get('published')->value) {
      throw new NotFoundHttpException();
    }

    $ids = $this->entityTypeManager->getStorage('aculta_lms_activity')->getQuery()
      ->accessCheck(FALSE)
      ->condition('lesson_id', (int) $lesson->id())
      ->sort('weight')
      ->sort('id')
      ->execute();
    $activities = $this->entityTypeManager->getStorage('aculta_lms_activity')->loadMultiple($ids);
    $rows = [];
    $activity_delta = 0;
    $cache = (new CacheableMetadata())
      ->addCacheableDependency($group)
      ->addCacheableDependency($lesson)
      ->addCacheContexts(['user', 'user.permissions', 'user.group_permissions'])
      ->addCacheTags([
        'group_relationship_list:plugin:lms_classes:group:' . $group->id(),
        LmsFriendlySlugs::LESSON_LIST_CACHE_TAG,
        LmsFriendlySlugs::ACTIVITY_LIST_CACHE_TAG,
      ]);
    foreach ($group->getRelationships('lms_classes') ?? [] as $relationship) {
      $class = $relationship instanceof GroupRelationshipInterface ? $relationship->getEntity() : NULL;
      if ($class instanceof GroupInterface) {
        $cache->addCacheableDependency($class);
        $cache->addCacheTags(['group_relationship_list:plugin:group_membership:group:' . $class->id()]);
      }
    }
    foreach ($activities as $activity) {
      $source_delta = $activity_delta++;
      if (!$activity instanceof ContentEntityInterface || $activity->getEntityTypeId() !== 'aculta_lms_activity') {
        continue;
      }
      $cache->addCacheableDependency($activity);
      if (!$this->eligibility->isContentAvailable($activity)
        || !$this->eligibility->activityBelongsToCourse($activity, $group)) {
        continue;
      }
      $configuration = $this->decodeConfiguration((string) ($activity->get('answer_config')->value ?? '{}'));
      $type = (string) $activity->get('type')->value;
      $label = $this->activityLabel($type);
      $rows[] = [
        ['data' => [
          'title' => ['#plain_text' => $label],
          'question' => isset($configuration['question']) && is_string($configuration['question'])
            ? ['#plain_text' => $configuration['question']]
            : [],
        ]],
        ['data' => Link::fromTextAndUrl(
          $this->t('Abrir atividade'),
          Url::fromRoute('aculta_lms.answer_submission', [
            'group' => $group->id(),
            'lesson_delta' => $lesson_delta,
            'activity_delta' => $source_delta,
          ]),
        )->toRenderable()],
      ];
    }

    $build = [
      '#type' => 'container',
      '#attributes' => ['data-portal-ui' => 'aculta-learning-room__lesson'],
      'course' => [
        '#type' => 'link',
        '#title' => $group->label(),
        '#url' => Url::fromRoute('entity.group.canonical', ['group' => $group->id()]),
      ],
      'title' => [
        '#type' => 'html_tag',
        '#tag' => 'h1',
        '#value' => (string) $lesson->get('title')->value,
      ],
      'body' => trim((string) ($lesson->get('body')->value ?? '')) === ''
        ? ['#plain_text' => $this->t('O conteúdo desta aula ainda será disponibilizado.')]
        : [
          '#type' => 'processed_text',
          '#text' => (string) $lesson->get('body')->value,
          '#format' => (string) ($lesson->get('body')->format ?? 'plain_text'),
          '#langcode' => $lesson->language()->getId(),
        ],
      'activities' => [
        '#type' => 'table',
        '#header' => [$this->t('Atividade'), $this->t('Ação')],
        '#rows' => $rows,
        '#empty' => $this->t('Esta aula ainda não tem atividades.'),
      ],
      '#cache' => [],
    ];
    $cache->applyTo($build);
    return $build;
  }

  /**
   * Decodes an activity config without exposing malformed payloads.
   */
  private function decodeConfiguration(string $json): array {
    try {
      $config = json_decode($json === '' ? '{}' : $json, TRUE, 512, JSON_THROW_ON_ERROR);
      return is_array($config) ? $config : [];
    }
    catch (\JsonException) {
      return [];
    }
  }

  /**
   * Returns a translated label for the supported stable activity type.
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
