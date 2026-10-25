<?php

declare(strict_types=1);

namespace Drupal\aculta_forum\Context\Provider;

use Drupal\aculta_forum\Context\DiscussionContext;
use Drupal\aculta_forum\Context\DiscussionContextInterface;
use Drupal\aculta_forum\Context\DiscussionContextProviderInterface;
use Drupal\comment\CommentingStatus;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\node\NodeInterface;

/**
 * Provides Comment contexts hosted by Magazine publication nodes.
 */
final class PublicationContextProvider implements DiscussionContextProviderInterface {

  private const HOST_BUNDLES = ['revista'];

  private const FIELD = 'field_aculta_discussion';

  /**
   * Checks for a publication bundle with the shared discussion field.
   */
  public function supports(EntityInterface $entity): bool {
    return $entity instanceof NodeInterface
      && in_array($entity->bundle(), self::HOST_BUNDLES, TRUE)
      && $entity->hasField(self::FIELD);
  }

  /**
   * Builds the editorial context and evaluates current access.
   */
  public function getContext(EntityInterface $entity, AccountInterface $account): DiscussionContextInterface {
    if (!$this->supports($entity)) {
      throw new \LogicException('Publication discussion context requires a revista node with the shared Comment field.');
    }
    if ($entity->isNew()) {
      throw new \LogicException('A discussion context requires a persisted host entity.');
    }
    $uuid = $entity->uuid();
    if ($uuid === NULL || $uuid === '') {
      throw new \LogicException('A discussion host must have a stable UUID.');
    }

    $field = $entity->get(self::FIELD);
    $readAccess = $entity->access('view', $account, TRUE)
      ->andIf($field->access('view', $account, TRUE));
    $fieldValue = $field->getValue()[0] ?? [];
    $isOpen = (int) ($fieldValue['status'] ?? CommentingStatus::Closed->value) === CommentingStatus::Open->value;
    $writeAccess = $readAccess
      ->andIf(AccessResult::allowedIf($isOpen)->addCacheableDependency($entity))
      ->andIf(AccessResult::allowedIf($account->hasPermission('post comments'))->addCacheContexts(['user.permissions']));
    $moderationAccess = $readAccess
      ->andIf(AccessResult::allowedIf($account->hasPermission('administer comments'))->addCacheContexts(['user.permissions']));

    return new DiscussionContext(
      $entity,
      'publication',
      'publication:node:' . $uuid,
      $readAccess,
      $writeAccess,
      $moderationAccess,
      $entity->toUrl('canonical'),
      'editorial_entity_access',
      'editorial_entity_access',
      'editorial_opt_in',
    );
  }

}
