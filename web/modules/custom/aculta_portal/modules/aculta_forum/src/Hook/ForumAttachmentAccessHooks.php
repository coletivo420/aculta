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

/**
 * Restricts Forum attachment Media to currently public Forum references.
 */
final class ForumAttachmentAccessHooks {

  public function __construct(
    private readonly DomainPurposeManager $domainPurposeManager,
    private readonly ForumAttachmentPublication $attachmentPublication,
  ) {}

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

    // Do not let a stale published flag bypass current Node grants or host
    // publication. The file download access handler also checks Media access.
    if (!$this->attachmentPublication->hasCurrentPublicReference($entity)) {
      return AccessResult::forbidden('O anexo não tem referência pública acessível no Fórum.')
        ->addCacheableDependency($entity)
        ->addCacheContexts(['domain', 'user.permissions'])
        ->setCacheMaxAge(0);
    }

    // Host grants can change without saving the Media entity. Recompute access
    // on the next request instead of caching this result beyond this request.
    return $access->addCacheContexts(['user.permissions'])->setCacheMaxAge(0);
  }

}
