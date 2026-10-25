<?php

declare(strict_types=1);

namespace Drupal\aculta_forum\Hook;

use Drupal\aculta_forum\Context\DiscussionContextResolver;
use Drupal\aculta_forum\Service\ForumQuoteBuilder;
use Drupal\comment\CommentInterface;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityFormInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Adds access-checked quoting for supported public discussion comments.
 */
final class ForumQuoteHooks {

  public function __construct(
    #[Autowire(service: 'aculta_forum.discussion_context_resolver')]
    private readonly DiscussionContextResolver $contextResolver,
    private readonly AccountProxyInterface $currentUser,
    #[Autowire(service: 'aculta_forum.quote_builder')]
    private readonly ForumQuoteBuilder $quoteBuilder,
    private readonly RequestStack $requestStack,
    private readonly TranslationInterface $translation,
  ) {}

  /**
   * Adds a reply action that passes the source ID as a validated quote hint.
   */
  #[Hook('comment_links_alter')]
  public function commentLinksAlter(array &$links, CommentInterface $comment, array &$context): void {
    if (!$this->isQuoteable($comment)) {
      return;
    }

    $commentAccess = $comment->access('view', $this->currentUser, TRUE);
    $replyAccess = $comment->access('create', $this->currentUser, TRUE);
    $host = $comment->getCommentedEntity();
    if (!$host instanceof EntityInterface || !$commentAccess->isAllowed() || !$replyAccess->isAllowed()) {
      return;
    }
    $discussion = $this->contextResolver->resolve($host, $this->currentUser);
    if ($discussion === NULL || !$discussion->getReadAccess()->isAllowed()) {
      return;
    }

    $url = Url::fromRoute('comment.reply', [
      'entity_type' => $comment->getCommentedEntityTypeId(),
      'entity' => $comment->getCommentedEntityId(),
      'field_name' => $comment->getFieldName(),
      'pid' => $comment->id(),
    ], [
      'query' => ['quote' => (int) $comment->id()],
      'fragment' => 'comment-form',
    ]);

    $linkBuild = [
      '#theme' => 'links__comment__comment',
      '#links' => [
        'aculta-forum-quote' => [
          'title' => $this->translation->translate('Citar'),
          'url' => $url,
          'attributes' => ['data-portal-ui' => 'button-secondary'],
        ],
      ],
    ];
    (new CacheableMetadata())
      ->addCacheableDependency($comment)
      ->addCacheableDependency($commentAccess)
      ->addCacheableDependency($replyAccess)
      ->addCacheableDependency($discussion)
      ->addCacheableDependency($discussion->getReadAccess())
      ->addCacheContexts(['user.permissions'])
      ->applyTo($linkBuild);
    $links['aculta_forum_quote'] = $linkBuild;
  }

  /**
   * Prefills only the exact published parent comment reached by the link.
   */
  #[Hook('form_comment_form_alter')]
  public function commentFormAlter(array &$form, FormStateInterface $formState, string $formId): void {
    $request = $this->requestStack->getCurrentRequest();
    if ($request === NULL || $request->attributes->get('_route') !== 'comment.reply') {
      return;
    }
    $quoteId = $request->query->get('quote');
    if (!is_scalar($quoteId) || !ctype_digit((string) $quoteId) || (int) $quoteId < 1) {
      return;
    }

    $formObject = $formState->getFormObject();
    if (!$formObject instanceof EntityFormInterface) {
      return;
    }
    $reply = $formObject->getEntity();
    if (!$reply instanceof CommentInterface || !$reply->isNew() || !$reply->hasParentComment()) {
      return;
    }
    $parent = $reply->getParentComment();
    if (!$parent instanceof CommentInterface || (int) $parent->id() !== (int) $quoteId
      || !$this->isQuoteable($parent)) {
      return;
    }

    $host = $reply->getCommentedEntity();
    if (!$host instanceof EntityInterface || $parent->getCommentedEntityTypeId() !== $reply->getCommentedEntityTypeId()
      || (string) $parent->getCommentedEntityId() !== (string) $reply->getCommentedEntityId()) {
      return;
    }
    $discussion = $this->contextResolver->resolve($host, $this->currentUser);
    $sourceAccess = $parent->access('view', $this->currentUser, TRUE);
    if ($discussion === NULL || !$discussion->getReadAccess()->isAllowed() || !$sourceAccess->isAllowed()) {
      return;
    }

    if (!$parent->hasField('comment_body') || $parent->get('comment_body')->isEmpty()) {
      return;
    }
    $commentBody = $parent->get('comment_body');
    $bodyItem = $commentBody->get(0);
    if ($bodyItem === NULL) {
      return;
    }
    $processedBody = $bodyItem->get('processed');
    $quote = $this->quoteBuilder->build((string) $processedBody->getValue(), $parent->getAuthorName());
    if ($quote === NULL) {
      return;
    }

    // Before processing, text_format keeps #default_value on the element
    // itself; the nested value/format children only exist afterwards.
    // #default_value is NULL for a new reply, so array_key_exists() is used.
    if (isset($form['comment_body']['widget'][0]['value'])) {
      $body =& $form['comment_body']['widget'][0]['value'];
    }
    elseif (array_key_exists('#default_value', $form['comment_body']['widget'][0] ?? [])) {
      $body =& $form['comment_body']['widget'][0];
    }
    else {
      return;
    }
    if (!empty($body['#default_value'])) {
      return;
    }
    $body['#default_value'] = $quote;

    (new CacheableMetadata())
      ->addCacheableDependency($parent)
      ->addCacheableDependency($host)
      ->addCacheableDependency($discussion)
      ->addCacheableDependency($discussion->getReadAccess())
      ->addCacheableDependency($sourceAccess)
      ->addCacheableDependency($processedBody)
      ->addCacheContexts(['url.query_args:quote'])
      ->applyTo($form);
  }

  /**
   * Returns whether a comment can be offered as a quote source.
   */
  private function isQuoteable(CommentInterface $comment): bool {
    if ($comment->bundle() !== 'aculta_discussion_post'
      || $comment->getFieldName() !== 'field_aculta_discussion'
      || !$comment->isPublished()) {
      return FALSE;
    }
    return $comment->getCommentedEntity() instanceof EntityInterface;
  }

}
