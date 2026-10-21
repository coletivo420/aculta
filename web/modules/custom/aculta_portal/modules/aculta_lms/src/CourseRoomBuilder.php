<?php

declare(strict_types=1);

namespace Drupal\aculta_lms;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Entity\EntityPublishedInterface;
use Drupal\Core\Link;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\Core\Url;
use Drupal\aculta_lms\Form\CourseEnrollmentPurchaseForm;
use Drupal\lms\Entity\Bundle\CourseInterface;

/**
 * Builds the learner facing overview on the native course page. */
final class CourseRoomBuilder {

  public function __construct(
    private readonly AccountProxyInterface $currentUser,
    private readonly TranslationInterface $translation,
    private readonly FormBuilderInterface $formBuilder,
    private readonly ClassRosterManager $classRosters,
  ) {}

  /**
   * Creates an accessible lesson outline using Drupal LMS routes and access.
   *
   * @return array<string,mixed>
   *   Render array suitable for an entity view build.
   */
  public function build(CourseInterface $course): array {
    $takeAccess = $course->takeAccess($this->currentUser);
    $cacheability = (new CacheableMetadata())
      ->addCacheableDependency($course)
      ->addCacheableDependency($takeAccess)
      ->addCacheContexts(['user', 'user.group_permissions']);

    $build = [
      '#type' => 'container',
      '#cache' => [],
    ];

    if (!$takeAccess->isAllowed()) {
      if (!$course->hasPermission('administer members', $this->currentUser)) {
        $build['enrollment'] = $this->formBuilder->getForm(CourseEnrollmentPurchaseForm::class, $course);
      }
      $cacheability->applyTo($build);
      return $build;
    }

    $items = [];
    foreach ($course->get('lessons') as $delta => $reference) {
      $lesson = $reference->entity;
      if ($lesson === NULL) {
        continue;
      }
      $cacheability->addCacheableDependency($lesson);
      // The LMS answer route checks takeAccess() on this parent course. Generic
      // lesson view access is not the learner's contextual curriculum access.
      if (!$lesson instanceof EntityPublishedInterface || !$lesson->isPublished()) {
        continue;
      }
      $activity = $lesson->get('activities')->first();
      if ($activity === NULL || $activity->entity === NULL) {
        $items[] = ['#plain_text' => (string) $lesson->label()];
        continue;
      }
      $cacheability->addCacheableDependency($activity->entity);
      $items[] = Link::fromTextAndUrl(
        (string) $lesson->label(),
        Url::fromRoute('lms.group.answer_form', [
          'group' => $course,
          'lesson_delta' => (int) $delta,
          'activity_delta' => 0,
        ]),
      )->toRenderable();
    }

    $build['title'] = [
      '#type' => 'html_tag',
      '#tag' => 'h2',
      '#value' => $this->translation->translate('Conteúdo do curso'),
    ];
    $build['lessons'] = $items === []
      ? ['#plain_text' => $this->translation->translate('Este curso ainda não tem aulas.')]
      : ['#theme' => 'item_list', '#items' => $items];
    $rosters = $this->classRosters->build($course, $this->currentUser);
    if (isset($rosters['classes'])) {
      $build['participant_rosters'] = $rosters;
    }
    $cacheability->addCacheableDependency(CacheableMetadata::createFromRenderArray($rosters));
    $cacheability->applyTo($build);
    return $build;
  }

}
