<?php

declare(strict_types=1);

namespace Drupal\aculta_forum\Hook;

use Drupal\aculta_user\ParticipationRules;
use Drupal\comment\CommentInterface;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityPublishedInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\user\EntityOwnerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Records an immutable first-publication timestamp for discussion posts.
 */
final class ForumPublicationTimeHooks {

  public function __construct(
    #[Autowire(service: 'datetime.time')]
    private readonly TimeInterface $time,
    #[Autowire(service: 'aculta_user.participation_rules')]
    private readonly ParticipationRules $participationRules,
  ) {}

  /**
   * Implements hook_entity_presave().
   */
  #[Hook('entity_presave')]
  public function entityPresave(EntityInterface $entity): void {
    if (!$entity instanceof ContentEntityInterface) {
      return;
    }
    if ($entity instanceof CommentInterface
      && $entity->bundle() === 'aculta_discussion_post'
      && $entity->getFieldName() === 'field_aculta_discussion') {
      $this->setOnFirstPublication($entity, 'field_aculta_published_at');
      return;
    }

    if ($entity->getEntityTypeId() === 'aculta_forum_post') {
      $is_syncing = $entity->isSyncing();
      if (!$is_syncing && $entity->isNew() && $entity instanceof EntityOwnerInterface && $entity->hasField('moderation_state')) {
        $owner = $entity->getOwner();
        $entity->set('moderation_state', $this->participationRules->requiresFirstDiscussionModeration($owner)
          ? 'review'
          : 'published');
      }
      if (!$is_syncing) {
        $this->setOnFirstPublication($entity, 'first_published');
      }
    }
  }

  /**
   * Sets the marker once, when the entity first becomes publicly published.
   */
  private function setOnFirstPublication(ContentEntityInterface $entity, string $fieldName): void {
    if (!$entity instanceof EntityPublishedInterface || !$entity->isPublished()
      || !$entity->hasField($fieldName) || !$entity->get($fieldName)->isEmpty()) {
      return;
    }

    $entity->set($fieldName, $this->time->getRequestTime());
  }

}
