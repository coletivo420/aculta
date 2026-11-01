<?php

declare(strict_types=1);

namespace Drupal\aculta_forum\Hook;

use Drupal\aculta_portal\Domain\DomainPurposeManager;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Session\AccountInterface;
use Drupal\node\NodeInterface;
use Drupal\user\EntityOwnerInterface;

/**
 * Applies the public Forum context to revisionable post entity access.
 */
final class ForumPostAccessHooks {

  public function __construct(
    private readonly DomainPurposeManager $domainPurposeManager,
  ) {}

  /**
   * Limits post access to the Forum purpose and its published topic.
   */
  #[Hook('entity_access')]
  public function entityAccess(EntityInterface $entity, string $operation, AccountInterface $account): ?AccessResultInterface {
    if ($entity->getEntityTypeId() !== 'aculta_forum_post') {
      return NULL;
    }

    if (!$account->hasPermission('administer aculta forum posts')) {
      return $this->publicAccess($entity, $operation, $account);
    }

    // The generic entity handler grants every operation to accounts with the
    // entity admin permission, and it runs only when no hook forbids access.
    // Without an active purpose (CLI, cron) or in MAIN the grant applies as
    // before. On any other purpose the grant must not apply, so the public
    // rules decide and every outcome they do not allow becomes forbidden.
    $purpose = $this->domainPurposeManager->getCurrentPurpose();
    if ($purpose === NULL || $purpose === 'main') {
      return AccessResult::neutral()
        ->addCacheableDependency($entity)
        ->addCacheContexts(['domain'])
        ->cachePerPermissions();
    }

    $publicOperation = $operation === 'view label' ? 'view' : $operation;
    $access = $this->publicAccess($entity, $publicOperation, $account);
    if ($access?->isAllowed()) {
      return $access;
    }

    return AccessResult::forbidden('Administradores só operam publicações pelo domínio principal.')
      ->addCacheableDependency($entity)
      ->addCacheContexts(['domain'])
      ->cachePerPermissions();
  }

  /**
   * Applies the public Forum rules to view and update access.
   */
  private function publicAccess(EntityInterface $entity, string $operation, AccountInterface $account): ?AccessResultInterface {
    if (!in_array($operation, ['view', 'update'], TRUE)) {
      return NULL;
    }

    $topic = $entity instanceof FieldableEntityInterface && $entity->hasField('topic')
      ? $entity->get('topic')->entity
      : NULL;
    if (!$topic instanceof NodeInterface || $topic->bundle() !== 'aculta_forum_topic') {
      return AccessResult::forbidden('A publicação precisa pertencer a um tópico público válido.')
        ->addCacheableDependency($entity);
    }

    $topicDomainPurpose = $topic->hasField('field_domain_source') && !$topic->get('field_domain_source')->isEmpty()
      ? $this->domainPurposeManager->getPurposeForDomainId((string) $topic->get('field_domain_source')->target_id)
      : NULL;
    $inForumContext = $this->domainPurposeManager->getCurrentPurpose() === 'forum'
      && $topicDomainPurpose === 'forum'
      && $topic->isPublished();
    $contextAccess = ($inForumContext ? AccessResult::allowed() : AccessResult::forbidden())
      ->addCacheableDependency($entity)
      ->addCacheableDependency($topic)
      ->addCacheContexts(['domain']);
    if (!$contextAccess->isAllowed()) {
      return $contextAccess;
    }

    $contextAccess = $contextAccess->andIf($topic->access('view', $account, TRUE));
    if (!$contextAccess->isAllowed()) {
      return $contextAccess;
    }

    if ($operation === 'view') {
      if ($entity->isPublished()) {
        return $contextAccess;
      }

      $canViewUnpublished = $account->hasPermission('view any unpublished content')
        || ($entity instanceof EntityOwnerInterface
          && (string) $entity->getOwnerId() === (string) $account->id()
          && $account->hasPermission('view own unpublished content'));
      $visibilityAccess = $canViewUnpublished ? AccessResult::allowed() : AccessResult::forbidden();
      return $contextAccess->andIf($visibilityAccess
        ->cachePerPermissions()
        ->addCacheContexts(['user']));
    }

    // The entity's admin permission and the Core Content Moderation hook still
    // govern administrators and valid workflow transitions. This grant only
    // supplies the ownership part of the access decision.
    if (!$entity instanceof EntityOwnerInterface || (string) $entity->getOwnerId() !== (string) $account->id()) {
      return AccessResult::neutral()->addCacheContexts(['user']);
    }

    return $contextAccess->andIf(AccessResult::allowed()->addCacheContexts(['user']));
  }

  /**
   * Allows post creation only on the public Forum purpose.
   */
  #[Hook('entity_create_access')]
  public function entityCreateAccess(AccountInterface $account, array $context, ?string $entity_bundle): ?AccessResultInterface {
    if (($context['entity_type_id'] ?? NULL) !== 'aculta_forum_post' || $entity_bundle !== 'aculta_forum_post') {
      return NULL;
    }

    $purposeAccess = $this->domainPurposeManager->getCurrentPurpose() === 'forum'
      ? AccessResult::allowed()
      : AccessResult::forbidden();
    $permissionAccess = $account->hasPermission('create aculta forum posts')
      ? AccessResult::allowed()->cachePerPermissions()
      : AccessResult::forbidden()->cachePerPermissions();
    return $purposeAccess->andIf($permissionAccess)
      ->addCacheContexts(['domain', 'user.permissions']);
  }

}
