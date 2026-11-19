<?php

declare(strict_types=1);

namespace Drupal\aculta_forum\Controller;

use Drupal\aculta_forum\Origin\DiscussionOrigin;
use Drupal\aculta_forum\Origin\DiscussionOriginResolverInterface;
use Drupal\aculta_forum\Service\DiscussionReplyAccess;
use Drupal\aculta_forum\Service\DiscussionReplyFormBuilder;
use Drupal\aculta_forum\Service\ForumQuoteBuilder;
use Drupal\aculta_forum\Form\TopicDeleteConfirmForm;
use Drupal\aculta_forum\Form\TopicOpeningEditForm;
use Drupal\comment\CommentInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\KeyValueStore\KeyValueFactoryInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\node\NodeInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Renders the discussion reply composer through HTMX or a full-page fallback.
 */
class DiscussionReplyController extends ControllerBase {

  public function __construct(
    protected readonly EntityTypeManagerInterface $forumEntityTypeManager,
    protected readonly DiscussionReplyFormBuilder $replyFormBuilder,
    protected readonly DiscussionReplyAccess $replyAccess,
    protected readonly DiscussionOriginResolverInterface $originResolver,
    protected readonly ForumQuoteBuilder $quoteBuilder,
    protected readonly AccountProxyInterface $forumCurrentUser,
    protected readonly KeyValueFactoryInterface $forumKeyValue,
  ) {}

  /**
   * Returns the discussion reply composer render array.
   *
   * @return array<mixed>
   *   The composer render array.
   */
  public function composer(string $entity_type, string $entity_id, Request $request): array {
    $storage = $this->forumEntityTypeManager->getStorage($entity_type);
    $host = $storage->load($entity_id);
    if (!$host instanceof EntityInterface) {
      throw new NotFoundHttpException();
    }

    $hostOrigin = $this->originResolver->classify($host);
    if (in_array($hostOrigin->origin, [DiscussionOrigin::ARCHIVE, DiscussionOrigin::UNKNOWN], TRUE)) {
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
    if ($editId !== NULL && ctype_digit($editId)) {
      $editComment = $this->forumEntityTypeManager->getStorage('comment')->load((int) $editId);
      $tombstoned = $editComment instanceof CommentInterface && (bool) $this->forumKeyValue->get('aculta_forum_comment_tombstones')->get((string) $editComment->id());
      // Respostas excluídas pelo autor não podem mais ser editadas.
      if ($tombstoned) {
        throw new AccessDeniedHttpException('Resposta excluída.');
      }
      if ($editComment instanceof CommentInterface
        && $editComment->bundle() === 'aculta_discussion_post'
        && $editComment->getFieldName() === 'field_aculta_discussion'
        && $editComment->getCommentedEntityTypeId() === $host->getEntityTypeId()
        && (string) $editComment->getCommentedEntityId() === (string) $host->id()
        && $this->originResolver->classify($editComment)->origin === DiscussionOrigin::CONTEXTUAL) {
        return $this->replyFormBuilder->buildEditForm($editComment, $this->forumCurrentUser);
      }
      throw new NotFoundHttpException();
    }

    $parentIdRaw = $request->query->get('resposta');
    $parentId = ($parentIdRaw !== NULL && $parentIdRaw !== '' && ctype_digit($parentIdRaw)) ? (int) $parentIdRaw : NULL;

    $quote = NULL;
    if ($host instanceof NodeInterface && $request->query->get('citar_topico') && $host->hasField('body') && !$host->get('body')->isEmpty()) {
      $bodyItem = $host->get('body')->first();
      $rawVal = $bodyItem !== NULL ? $bodyItem->get('value')->getValue() : NULL;
      $rawBody = is_scalar($rawVal) ? (string) $rawVal : '';
      $quote = $this->quoteBuilder->build($rawBody, (string) $host->getOwner()->getDisplayName());
    }

    $quoteId = $request->query->get('citar') ?? $request->query->get('citacao');
    if ($quoteId !== NULL && ctype_digit($quoteId)) {
      $quoteComment = $this->forumEntityTypeManager->getStorage('comment')->load((int) $quoteId);
      // Hints are untrusted IDs. Validate the exact source, including host,
      // entity access and historical archive isolation, before reading text.
      if ($quoteComment instanceof CommentInterface
        && $quoteComment->bundle() === 'aculta_discussion_post'
        && $quoteComment->getFieldName() === 'field_aculta_discussion'
        && $quoteComment->getCommentedEntityTypeId() === $host->getEntityTypeId()
        && (string) $quoteComment->getCommentedEntityId() === (string) $host->id()
        && $quoteComment->isPublished()
        && $quoteComment->access('view', $this->forumCurrentUser)
        && $this->originResolver->classify($quoteComment)->origin === DiscussionOrigin::CONTEXTUAL
        && !$this->forumKeyValue->get('aculta_forum_comment_tombstones')->get((string) $quoteComment->id())
        && $quoteComment->hasField('comment_body')) {
        $bodyItem = $quoteComment->get('comment_body')->get(0);
        if ($bodyItem !== NULL) {
          $rawProcessed = $bodyItem->get('processed')->getValue();
          $processedText = is_scalar($rawProcessed) ? (string) $rawProcessed : '';
          $quote = $this->quoteBuilder->build($processedText, (string) $quoteComment->getAuthorName());
        }
      }
    }

    return $this->replyFormBuilder->buildReplyForm($host, $this->forumCurrentUser, $parentId, $quote);
  }

}
