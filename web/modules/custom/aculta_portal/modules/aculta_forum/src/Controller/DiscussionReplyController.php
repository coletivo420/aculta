<?php

declare(strict_types=1);

namespace Drupal\aculta_forum\Controller;

use Drupal\aculta_forum\Context\DiscussionContextResolver;
use Drupal\aculta_forum\Service\DiscussionReplyAccess;
use Drupal\aculta_forum\Service\DiscussionReplyFormBuilder;
use Drupal\aculta_forum\Service\ForumQuoteBuilder;
use Drupal\aculta_forum\Form\TopicDeleteConfirmForm;
use Drupal\aculta_forum\Form\TopicOpeningEditForm;
use Drupal\comment\CommentInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\node\NodeInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Controller for rendering the inline discussion reply composer via HTMX or fallback.
 */
class DiscussionReplyController extends ControllerBase {

  public function __construct(
    protected readonly EntityTypeManagerInterface $forumEntityTypeManager,
    protected readonly DiscussionReplyFormBuilder $replyFormBuilder,
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
    $host = $storage->load($entity_id);
    if ($host === NULL) {
      throw new NotFoundHttpException();
    }

    if ($host instanceof NodeInterface && $host->bundle() === 'aculta_forum_topic') {
      if ($request->query->get('excluir_topico') || $request->request->get('form_id') === 'aculta_forum_topic_delete_confirm_form') {
        $form = $this->formBuilder()->getForm(TopicDeleteConfirmForm::class, $host);
        return [
          '#theme' => 'discussion_reply_composer',
          '#status' => 'editando',
          '#form' => $form,
          '#mode' => 'excluir',
          '#host_title' => $host->label(),
          '#target_id' => 'discussion-composer-' . $host->getEntityTypeId() . '-' . $host->id(),
        ];
      }

      if ($request->query->get('editar_topico') || $request->request->get('form_id') === 'aculta_forum_topic_opening_edit_form') {
        $form = $this->formBuilder()->getForm(TopicOpeningEditForm::class, $host);
        return [
          '#theme' => 'discussion_reply_composer',
          '#status' => 'editando',
          '#form' => $form,
          '#mode' => 'editar',
          '#host_title' => $host->label(),
          '#target_id' => 'discussion-composer-' . $host->getEntityTypeId() . '-' . $host->id(),
        ];
      }
    }

    $editId = $request->query->get('editar');
    if ($editId !== NULL && ctype_digit((string) $editId)) {
      $editComment = $this->forumEntityTypeManager->getStorage('comment')->load((int) $editId);
      $tombstoned = $editComment instanceof CommentInterface && (bool) \Drupal::keyValue('aculta_forum_comment_tombstones')->get((string) $editComment->id());
      // Resposta excluída pelo autor não pode mais ser editada, nem pelo administrador.
      if ($tombstoned) {
        throw new \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException('Resposta excluída.');
      }
      if ($editComment instanceof CommentInterface && (string) $editComment->getCommentedEntityId() === (string) $host->id()) {
        return $this->replyFormBuilder->buildEditForm($editComment, $this->forumCurrentUser);
      }
      throw new NotFoundHttpException();
    }

    $parentId = $request->query->get('resposta');
    $parentId = ($parentId !== NULL && $parentId !== '' && ctype_digit((string) $parentId)) ? (int) $parentId : NULL;

    $quote = NULL;
    if ($host instanceof NodeInterface && $request->query->get('citar_topico') && $host->hasField('body') && !$host->get('body')->isEmpty()) {
      $bodyItem = $host->get('body')->first();
      $rawBody = (string) ($bodyItem->get('value')->getValue() ?? '');
      $quote = $this->quoteBuilder->build($rawBody, $host->getOwner()->getDisplayName());
    }

    $quoteId = $request->query->get('citar') ?? $request->query->get('citacao');
    if ($quoteId !== NULL && ctype_digit((string) $quoteId)) {
      $quoteComment = $this->forumEntityTypeManager->getStorage('comment')->load((int) $quoteId);
      if ($quoteComment instanceof CommentInterface && $quoteComment->isPublished() && $quoteComment->hasField('comment_body')) {
        $bodyItem = $quoteComment->get('comment_body')->get(0);
        if ($bodyItem !== NULL) {
          $quote = $this->quoteBuilder->build((string) $bodyItem->get('processed')->getValue(), $quoteComment->getAuthorName());
        }
      }
    }

    return $this->replyFormBuilder->buildReplyForm($host, $this->forumCurrentUser, $parentId, $quote);
  }

}
