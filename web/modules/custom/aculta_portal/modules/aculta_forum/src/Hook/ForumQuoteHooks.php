<?php

declare(strict_types=1);

namespace Drupal\aculta_forum\Hook;

use Drupal\aculta_forum\Context\DiscussionContextResolver;
use Drupal\aculta_forum\Service\DiscussionReplyUrlBuilder;
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
    #[Autowire(service: 'aculta_forum.discussion_reply_url_builder')]
    private readonly ?DiscussionReplyUrlBuilder $urlBuilder = NULL,
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
    // COM-09: Revalida leitura E escrita do contexto da discussão.
    if ($discussion === NULL
      || !$discussion->getReadAccess()->isAllowed()
      || !$discussion->getWriteAccess()->isAllowed()) {
      return;
    }

    $urlBuilder = $this->urlBuilder ?? new DiscussionReplyUrlBuilder();
    $fallbackUrl = $urlBuilder->buildHostReplyUrl($host, (int) $comment->id(), 'citar');
    $composerId = 'discussion-composer-' . $host->getEntityTypeId() . '-' . $host->id() . '-' . $comment->id();

    // Endpoint for loading composer with quote prefilled via HTMX.
    $endpointUrl = Url::fromRoute('aculta_forum.discussion_composer', [
      'entity_type' => $host->getEntityTypeId(),
      'entity_id' => $host->id(),
    ], [
      'query' => [
        'resposta' => (int) $comment->id(),
        'citacao' => (int) $comment->id(),
        'modo' => 'citar',
      ],
    ]);

    // COM-08/COM-10/COM-11: Estrutura neutra de links Drupal, com atributos semânticos,
    // inserida no grupo 'comment' para ser coletada por Link::preRenderLinks.
    $quoteLink = [
      'title' => $this->translation->translate('Citar'),
      'url' => $fallbackUrl,
      'attributes' => [
        'class' => ['comment-action', 'comment-action--quote', 'use-htmx'],
        'data-action' => 'comment-quote',
        'data-quote-id' => (string) $comment->id(),
        'data-hx-get' => $endpointUrl->toString(),
        'data-hx-target' => '#' . $composerId,
        'data-hx-swap' => 'outerHTML',
        'aria-label' => (string) $this->translation->translate('Citar comentário #@id', ['@id' => $comment->id()]),
      ],
    ];

    $cacheability = (new CacheableMetadata())
      ->addCacheableDependency($comment)
      ->addCacheableDependency($commentAccess)
      ->addCacheableDependency($replyAccess)
      ->addCacheableDependency($discussion)
      ->addCacheableDependency($discussion->getReadAccess())
      ->addCacheableDependency($discussion->getWriteAccess())
      ->addCacheContexts(['user.permissions']);

    if (isset($links['comment'])) {
      $links['comment']['#links']['comment-quote'] = $quoteLink;
      $cacheability->applyTo($links['comment']);
    }
    else {
      $links['#links']['comment-quote'] = $quoteLink;
      $cacheability->applyTo($links);
    }
  }

  /**
   * Prefills only the exact published parent comment reached by the link.
   */
  #[Hook('form_comment_form_alter')]
  public function commentFormAlter(array &$form, FormStateInterface $formState, string $formId): void {
    $request = $this->requestStack->getCurrentRequest();
    if ($request === NULL) {
      return;
    }
    $quoteId = $request->query->get('citacao') ?? $request->query->get('quote');
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
    // COM-09: Revalida também escrita no formulário
    if ($discussion === NULL
      || !$discussion->getReadAccess()->isAllowed()
      || !$discussion->getWriteAccess()->isAllowed()
      || !$sourceAccess->isAllowed()) {
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
    $processed = isset($form['comment_body']['widget'][0]['value']);
    if ($processed) {
      $body =& $form['comment_body']['widget'][0]['value'];
    }
    elseif (array_key_exists('#default_value', $form['comment_body']['widget'][0] ?? [])) {
      $body =& $form['comment_body']['widget'][0];
    }
    else {
      return;
    }
    if (!empty($body['#default_value']) || !empty($body['#value'])) {
      return;
    }
    $body['#default_value'] = $quote;
    if ($processed) {
      // A processed textarea renders #value, not #default_value.
      $body['#value'] = $quote;
    }

    (new CacheableMetadata())
      ->addCacheableDependency($parent)
      ->addCacheableDependency($host)
      ->addCacheableDependency($discussion)
      ->addCacheableDependency($discussion->getReadAccess())
      ->addCacheableDependency($discussion->getWriteAccess())
      ->addCacheableDependency($sourceAccess)
      ->addCacheableDependency($processedBody)
      ->addCacheContexts(['url.query_args:citacao', 'url.query_args:quote'])
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
