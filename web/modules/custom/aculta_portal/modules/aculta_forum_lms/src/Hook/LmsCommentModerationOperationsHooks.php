<?php

declare(strict_types=1);

namespace Drupal\aculta_forum_lms\Hook;

use Drupal\aculta_forum_lms\Access\LmsForumModerationManager;
use Drupal\comment\CommentInterface;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Adds contextual moderation actions to LMS Comment operation lists.
 */
final class LmsCommentModerationOperationsHooks {

  public function __construct(
    #[Autowire(service: 'aculta_forum_lms.moderation_manager')]
    private readonly LmsForumModerationManager $moderationManager,
    #[Autowire(service: 'current_user')]
    private readonly AccountInterface $account,
  ) {}

  /**
   * Implements hook_entity_operation().
   */
  #[Hook('entity_operation')]
  public function entityOperation(EntityInterface $entity, CacheableMetadata $cacheability): array {
    if (!$entity instanceof CommentInterface) {
      return [];
    }

    $group = $this->moderationManager->getCommentGroup($entity);
    if ($group === NULL) {
      return [];
    }

    $operations = [];
    $decisions = [
      'approve' => new TranslatableMarkup('Aprovar'),
      'reject' => new TranslatableMarkup('Rejeitar'),
    ];
    foreach ($decisions as $decision => $label) {
      $access = $this->moderationManager->commentModerationAccess($group, $entity, $decision, $this->account);
      $cacheability->addCacheableDependency($access);
      if (!$access->isAllowed()) {
        continue;
      }
      $operations['aculta_lms_' . $decision] = [
        'title' => $label,
        'weight' => $decision === 'approve' ? 20 : 21,
        'url' => Url::fromRoute('aculta_forum_lms.comment_moderate', [
          'course' => $this->moderationManager->courseIdForGroup($group),
          'group' => $group->id(),
          'comment' => $entity->id(),
          'decision' => $decision,
        ]),
      ];
    }

    return $operations;
  }

}
