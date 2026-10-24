<?php

declare(strict_types=1);

namespace Drupal\aculta_wiki_messenger\Controller;

use Drupal\aculta_wiki_messenger\WikiNoticeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\message\MessageInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Opens an owned Wiki notice after revalidating its source access. */
final class WikiNoticeOpenController {

  public function __construct(
    private readonly WikiNoticeManagerInterface $notices,
    private readonly AccountInterface $currentUser,
  ) {}

  /**
   * Marks an authorized notice read and redirects to the source. */
  public function open(MessageInterface $message): RedirectResponse {
    $resolved = $this->notices->resolve($message, $this->currentUser);
    if ($resolved === NULL) {
      throw new NotFoundHttpException();
    }
    if (!(bool) $message->get('field_aculta_wiki_read')->value) {
      $message->set('field_aculta_wiki_read', 1)->save();
    }
    return new RedirectResponse($resolved['url']->toString());
  }

}
