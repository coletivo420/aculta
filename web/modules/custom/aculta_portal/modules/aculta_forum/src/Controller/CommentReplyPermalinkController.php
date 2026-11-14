<?php

declare(strict_types=1);

namespace Drupal\aculta_forum\Controller;

use Drupal\aculta_forum\Service\DiscussionCommentPermalinkBuilderInterface;
use Drupal\comment\CommentInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Redireciona /resposta/{cid} para o tópico, na página e na âncora do comentário.
 */
final class CommentReplyPermalinkController {

  public function __construct(
    private readonly DiscussionCommentPermalinkBuilderInterface $permalinkBuilder,
  ) {}

  /**
   * Redireciona para o tópico onde o comentário está.
   */
  public function redirectToComment(CommentInterface $comment): RedirectResponse {
    $url = $this->permalinkBuilder->hostUrl($comment);
    if ($url === NULL) {
      throw new NotFoundHttpException();
    }
    $url->setAbsolute(TRUE);
    return new RedirectResponse($url->toString());
  }

}
