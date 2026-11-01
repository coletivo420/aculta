<?php

declare(strict_types=1);

namespace Drupal\aculta_forum_lms\Hook;

use Drupal\aculta_forum_lms\Context\Lmsaculta_H5PDiscussionResolver;
use Drupal\aculta_lms_h5p\Access\aculta_H5PCourseAccessManager;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\group\Entity\GroupInterface;
use Drupal\lms\Entity\Bundle\CourseInterface;
use Drupal\node\NodeInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Adds authorized Forum links below H5P content in its course context.
 */
final class Lmsaculta_H5PDiscussionHooks {

  public function __construct(
    private readonly Lmsaculta_H5PDiscussionResolver $resolver,
    private readonly aculta_H5PCourseAccessManager $h5pAccess,
    private readonly AccountProxyInterface $currentUser,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly RequestStack $requestStack,
  ) {}

  /**
   * Implements hook_h5p_formatter_alter().
   */
  #[Hook('h5p_formatter_alter')]
  public function formatterAlter(array &$elements, FieldItemListInterface $items): void {
    $course = $items->getEntity();
    if (!$course instanceof CourseInterface) {
      return;
    }
    foreach ($items as $delta => $item) {
      $contentId = (int) $item->h5p_content_id;
      if ($contentId < 1 || !isset($elements[$delta])) {
        continue;
      }
      $student = $this->h5pAccess->studentAccess((int) $course->id(), $contentId, $this->currentUser);
      $educator = $this->h5pAccess->editorAccess((int) $course->id(), $contentId, $this->currentUser);
      $access = AccessResult::allowedIf($student->isAllowed() || $educator->isAllowed())
        ->addCacheableDependency($student)
        ->addCacheableDependency($educator);
      $discussion = $this->resolver->build($contentId, $course, $this->currentUser);
      $cache = CacheableMetadata::createFromObject($student)
        ->addCacheableDependency($educator)
        ->addCacheableDependency($course)
        ->addCacheableDependency(CacheableMetadata::createFromRenderArray($discussion));
      if (!$access->isAllowed() || empty($discussion['links']['#items'])) {
        $cache->applyTo($elements[$delta]);
        continue;
      }
      $wrapped = [
        '#type' => 'container',
        '#attributes' => ['data-portal-ui' => 'lms-h5p-activity'],
        'player' => $elements[$delta],
        'discussions' => $discussion,
      ];
      $cache->applyTo($wrapped);
      $elements[$delta] = $wrapped;
    }
  }

  /**
   * Adds the Group context to the H5P autocomplete request.
   */
  #[Hook('form_node_aculta_lms_topic_form_alter')]
  public function topicFormAlter(array &$form, FormStateInterface $formState): void {
    if (!isset($form['field_h5p_content']['widget'][0]['target_id'])) {
      return;
    }
    $request = $this->requestStack->getCurrentRequest();
    $group = $request?->attributes->get('group');
    if (!$group instanceof GroupInterface) {
      $topic = $formState->getFormObject()->getEntity();
      if (!$topic instanceof NodeInterface || $topic->isNew()) {
        return;
      }
      $relationships = $this->entityTypeManager->getStorage('group_relationship')
        ->loadByEntity($topic, 'group_node:aculta_lms_topic');
      if (count($relationships) !== 1) {
        return;
      }
      $relationship = reset($relationships);
      $group = $relationship->getGroup();
    }
    if ($group instanceof GroupInterface) {
      $form['field_h5p_content']['widget'][0]['target_id']['#autocomplete_query_parameters']['group_id'] = (string) $group->id();
    }
  }

}
