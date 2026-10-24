<?php

declare(strict_types=1);

namespace Drupal\aculta_forum\Access;

use Drupal\aculta_portal\Domain\DomainPurposeManager;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\filter\FilterFormatInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Limits the attachment editor dialog to the active public Forum purpose.
 */
final class ForumAttachmentUploadAccess {

  public function __construct(
    private readonly DomainPurposeManager $domainPurposeManager,
  ) {}

  /**
   * Checks the format and Domain context for the editor upload dialog.
   */
  public function access(FilterFormatInterface $filter_format, AccountInterface $account): AccessResultInterface {
    $forum_domain = $this->domainPurposeManager->getDomain('forum');
    $allowed = $account->isAuthenticated()
      && $filter_format->id() === 'forum'
      && $forum_domain !== NULL
      && $this->domainPurposeManager->getCurrentPurpose() === 'forum';
    $result = AccessResult::allowedIf($allowed)
      ->addCacheContexts(['domain', 'user.roles:authenticated'])
      ->addCacheableDependency($filter_format);
    if ($forum_domain !== NULL) {
      $result->addCacheableDependency($forum_domain);
    }
    return $result;
  }

}
