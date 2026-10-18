<?php

declare(strict_types=1);

namespace Drupal\aculta_lms\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/** Loads the ACULTA LMS icon for users who can access administration. */
final class AdminToolbarIconHooks {

  public function __construct(
    #[Autowire(service: 'current_user')]
    private readonly AccountInterface $currentUser,
  ) {}

  #[Hook('page_attachments')]
  public function pageAttachments(array &$attachments): void {
    if (!$this->currentUser->hasPermission('access administration pages')) {
      return;
    }

    $attachments['#attached']['library'][] = 'aculta_lms/admin_toolbar_icon';
    $attachments['#cache']['contexts'][] = 'user.permissions';
  }

}
