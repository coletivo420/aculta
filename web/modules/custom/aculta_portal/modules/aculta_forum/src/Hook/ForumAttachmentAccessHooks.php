<?php

declare(strict_types=1);

namespace Drupal\aculta_forum\Hook;

use Drupal\aculta_forum\Attachment\ForumAttachmentPublication;
use Drupal\aculta_portal\Domain\DomainPurposeManager;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Session\AccountInterface;
use Drupal\media\MediaInterface;
use Drupal\media_gallery\MediaGalleryInterface;

/**
 * Restricts Forum attachment Media to currently public Forum references.
 */
final class ForumAttachmentAccessHooks {

  public function __construct(
    private readonly DomainPurposeManager $domainPurposeManager,
    private readonly ForumAttachmentPublication $attachmentPublication,
  ) {}

  /**
   * Applies purpose and current-host access to Forum Gallery.
   *
   * A published gallery follows the requesting account access to its host
   * post or comment. The slideshow still filters each image by media_access.
   */
  #[Hook('entity_access')]
  public function entityAccess(EntityInterface $entity, string $operation, AccountInterface $account): ?AccessResultInterface {
    if (!$entity instanceof MediaGalleryInterface
      || !$entity->hasField('field_gallery_purpose')
      || $entity->get('field_gallery_purpose')->value !== 'forum'
      || !in_array($operation, ['view', 'view label'], TRUE)) {
      return NULL;
    }

    if ($this->domainPurposeManager->getCurrentPurpose() !== 'forum') {
      return AccessResult::forbidden('Galerias do Fórum só podem ser acessadas no purpose Fórum.')
        ->addCacheableDependency($entity)
        ->addCacheContexts(['domain', 'user.permissions']);
    }

    // Unpublished galleries stay governed by the Gallery owner/moderator.
    if (!$entity->isPublished()) {
      return AccessResult::neutral()
        ->addCacheableDependency($entity)
        ->addCacheContexts(['user.permissions'])
        ->setCacheMaxAge(0);
    }

    if (!$this->attachmentPublication->hasCurrentPublicGalleryReference($entity, $account)) {
      return AccessResult::forbidden('A galeria não tem referência acessível à conta no Fórum.')
        ->addCacheableDependency($entity)
        ->addCacheContexts(['domain', 'user'])
        ->setCacheMaxAge(0);
    }

    return AccessResult::allowed()
      ->addCacheableDependency($entity)
      ->addCacheContexts(['domain', 'user'])
      ->setCacheMaxAge(0);
  }

  /**
   * Applies purpose and current-host access to Forum attachment Media.
   */
  #[Hook('media_access')]
  public function mediaAccess(EntityInterface $entity, string $operation, AccountInterface $account): ?AccessResultInterface {
    if (!$entity instanceof MediaInterface
      || $entity->bundle() !== ForumAttachmentPublication::BUNDLE
      || !in_array($operation, ['view', 'view label'], TRUE)) {
      return NULL;
    }

    $access = AccessResult::neutral()
      ->addCacheableDependency($entity)
      ->addCacheContexts(['domain']);
    if ($this->domainPurposeManager->getCurrentPurpose() !== 'forum') {
      return AccessResult::forbidden('Anexos do Fórum só podem ser acessados no purpose Fórum.')
        ->addCacheableDependency($entity)
        ->addCacheContexts(['domain', 'user.permissions']);
    }

    // Unpublished attachments stay governed by Media's owner/moderator access.
    if (!$entity->isPublished()) {
      return $access->addCacheContexts(['user.permissions'])->setCacheMaxAge(0);
    }

    // A published attachment follows the access of the requesting account to
    // the published host. The restricted topic policy decides through
    // node_access, so a non-member is denied even though the Media is
    // published. The private file download handler rechecks this Media access.
    if (!$this->attachmentPublication->hasCurrentPublicReference($entity, $account)) {
      return AccessResult::forbidden('O anexo não tem referência acessível à conta no Fórum.')
        ->addCacheableDependency($entity)
        ->addCacheContexts(['domain', 'user'])
        ->setCacheMaxAge(0);
    }

    // Host grants can change without saving the Media entity. Recompute access
    // on the next request instead of caching this result beyond this request.
    return AccessResult::allowed()
      ->addCacheableDependency($entity)
      ->addCacheContexts(['domain', 'user'])
      ->setCacheMaxAge(0);
  }

}
