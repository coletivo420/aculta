<?php

declare(strict_types=1);

namespace Drupal\aculta_forum\Controller;

use Drupal\aculta_forum\Context\DiscussionContextResolver;
use Drupal\aculta_forum\Service\DiscussionReplyAccess;
use Drupal\aculta_forum\Service\DiscussionReplyFormBuilder;
use Drupal\aculta_forum\Service\ForumQuoteBuilder;
use Drupal\comment\CommentInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Controller for rendering the inline discussion reply composer via HTMX or fallback.
 */
class DiscussionReplyController extends ControllerBase {

  public function __construct(
    protected readonly EntityTypeManagerInterface $forumEntityTypeManager,
    protected readonly DiscussionReplyFormBuilder $formBuilder,
    protected readonly DiscussionReplyAccess $replyAccess,
    protected readonly DiscussionContextResolver $contextResolver,
    protected readonly ForumQuoteBuilder $quoteBuilder,
    protected readonly AccountProxyInterface $forumCurrentUser,
  ) {}

  /**
   * Returns the discussion reply composer render array.
   */
  public function composer(string $entity_type, string $entity_id, Request $request): array {
    $storage = $this->forumEntityTypeManager->getStorage($entity_type);
    if ($storage === NULL) {
      throw new NotFoundHttpException();
    }
    $host = $storage->load($entity_id);
    if ($host === NULL) {
      throw new NotFoundHttpException();
    }

    $parentId = $request->query->get('resposta');
    $parentId = ($parentId !== NULL && $parentId !== '' && ctype_digit((string) $parentId)) ? (int) $parentId : NULL;

    $quote = NULL;
    $quoteId = $request->query->get('quote') ?? $request->query->get('citacao');
    if ($quoteId !== NULL && ctype_digit((string) $quoteId)) {
      $quoteComment = $this->forumEntityTypeManager->getStorage('comment')->load((int) $quoteId);
      if ($quoteComment instanceof CommentInterface && $quoteComment->isPublished() && $quoteComment->hasField('comment_body')) {
        $bodyItem = $quoteComment->get('comment_body')->get(0);
        if ($bodyItem !== NULL) {
          $quote = $this->quoteBuilder->build((string) $bodyItem->get('processed')->getValue(), $quoteComment->getAuthorName());
        }
      }
    }

    return $this->formBuilder->buildReplyForm($host, $this->forumCurrentUser, $parentId, $quote);
  }

}
