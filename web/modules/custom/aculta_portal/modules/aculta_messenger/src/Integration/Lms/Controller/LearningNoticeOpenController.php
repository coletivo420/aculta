<?php

declare(strict_types=1);

namespace Drupal\aculta_messenger\Integration\Lms\Controller;

use Drupal\Core\Session\AccountInterface;
use Drupal\message\MessageInterface;
use Drupal\aculta_messenger\Integration\Lms\LearningNoticeManagerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Drupal\Core\Routing\TrustedRedirectResponse;

/**
 * Marks an owned learning notice read before opening its current target.
 */
final class LearningNoticeOpenController {

  public function __construct(
    private readonly LearningNoticeManagerInterface $notices,
    private readonly AccountInterface $currentUser,
  ) {}

  /**
   * Marks the notice read and redirects to its currently valid target.
   */
  public function open(MessageInterface $message): Response {
    if ((int) $message->getOwnerId() !== (int) $this->currentUser->id()) {
      throw new NotFoundHttpException();
    }
    $target = $this->notices->resolve($message, $this->currentUser);
    if ($target === NULL) {
      throw new NotFoundHttpException();
    }
    $this->notices->markRead($message);
    return new TrustedRedirectResponse($target['url']->toString());
  }

}
