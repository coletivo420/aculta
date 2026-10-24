<?php

declare(strict_types=1);

namespace Drupal\aculta_messenger\Integration\Donation\Controller;

use Drupal\aculta_messenger\Integration\Donation\DonationNoticeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\message\MessageInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Opens an owned Donation notice after access is revalidated. */
final class DonationNoticeOpenController {

  public function __construct(
    private readonly DonationNoticeManagerInterface $notices,
    private readonly AccountInterface $currentUser,
  ) {}

  /**
   * Marks the notice read and returns the member to the account dashboard. */
  public function open(MessageInterface $message): RedirectResponse {
    if (!$this->notices->markRead($message, $this->currentUser)) {
      throw new NotFoundHttpException();
    }
    $resolved = $this->notices->resolve($message, $this->currentUser);
    if ($resolved === NULL) {
      throw new NotFoundHttpException();
    }
    return new RedirectResponse($resolved['url']->toString());
  }

}
