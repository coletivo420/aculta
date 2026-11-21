<?php

declare(strict_types=1);

namespace Drupal\aculta_forum\Service;

use Drupal\aculta_forum\Context\DiscussionContextResolver;
use Drupal\aculta_forum\Entity\ForumPost;
use Drupal\comment\CommentInterface;
use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\Unicode;
use Drupal\Core\Entity\EntityFormBuilderInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\node\NodeInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Builds contextual discussion reply forms for Comment and ForumPost entities.
 */
class DiscussionReplyFormBuilder {

  use StringTranslationTrait;

  public function __construct(
    protected readonly DiscussionContextResolver $contextResolver,
    protected readonly EntityTypeManagerInterface $entityTypeManager,
    protected readonly EntityFormBuilderInterface $entityFormBuilder,
    protected readonly DiscussionReplyAccess $replyAccess,
    protected readonly ForumQuoteBuilder $quoteBuilder,
    protected readonly DiscussionThreadDepthResolver $depthResolver,
  ) {}

  /**
   * Builds the render array for a discussion reply composer.
   *
   * @param \Drupal\Core\Entity\EntityInterface $host
   *   The host entity.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The account responding.
   * @param int|null $parentId
   *   Optional parent comment or post ID.
   * @param string|null $quote
   *   Optional quote text to prefill.
   *
   * @return array<string, mixed>
   *   A render array containing the form or access notification.
   */
  public function buildReplyForm(EntityInterface $host, AccountInterface $account, ?int $parentId = NULL, ?string $quote = NULL): array {
    $access = $this->replyAccess->checkAccess($host, $account, $parentId);
    if (!$access->isAllowed()) {
      return [
        '#theme' => 'discussion_reply_composer',
        '#status' => 'acesso_revogado',
        '#target_id' => 'discussion-composer-' . $host->getEntityTypeId() . '-' . $host->id() . ($parentId ? '-' . $parentId : ''),
        '#messages' => [
          '#markup' => '<div class="alert alert--warning">' . $this->t('Você não tem permissão para responder a esta discussão.') . '</div>',
        ],
        '#cache' => [
          'contexts' => ['user.permissions'],
        ],
      ];
    }

    if ($host instanceof NodeInterface && $host->bundle() === 'aculta_forum_topic') {
      return $this->buildForumPostReplyForm($host, $parentId, $quote);
    }

    $commentField = 'field_aculta_discussion';

    $commentStorage = $this->entityTypeManager->getStorage('comment');
    $effectiveParentId = $parentId;
    if ($parentId !== NULL && $parentId > 0) {
      $parentComment = $commentStorage->load($parentId);
      if ($parentComment instanceof CommentInterface) {
        $effectiveParentId = $this->depthResolver->resolveEffectiveParentId($parentComment);
      }
    }

    $commentValues = [
      'entity_type' => $host->getEntityTypeId(),
      'entity_id' => $host->id(),
      'field_name' => $commentField,
      'comment_type' => 'aculta_discussion_post',
      'pid' => $effectiveParentId,
    ];

    /** @var \Drupal\comment\CommentInterface $comment */
    $comment = $commentStorage->create($commentValues);

    $form = $this->entityFormBuilder->getForm($comment);

    // Apply quote if provided and form has comment_body. The entity form is
    // already processed when it comes back from the form builder, so the
    // rendered textarea reads #value; #default_value alone never reaches it.
    if ($quote !== NULL && $quote !== '') {
      if (isset($form['comment_body']) && is_array($form['comment_body'])) {
        $commentBody = &$form['comment_body'];
        if (isset($commentBody['widget']) && is_array($commentBody['widget'])) {
          $widget = &$commentBody['widget'];
          if (isset($widget[0]) && is_array($widget[0])) {
            $firstWidget = &$widget[0];
            $body = NULL;
            if (isset($firstWidget['value']) && is_array($firstWidget['value'])) {
              $body = &$firstWidget['value'];
            }
            elseif (array_key_exists('#default_value', $firstWidget)) {
              $body = &$firstWidget;
            }
            if (is_array($body) && empty($body['#default_value']) && empty($body['#value'])) {
              $body['#default_value'] = $quote;
              // Processed text fields render #value, so set both values.
              $body['#value'] = $quote;
            }
            unset($body);
          }
        }
      }
    }

    // The composer posts back to the host page, which returns the full main
    // content. Swap only the discussion thread to avoid rendering the topic
    // aside twice after a reply.
    // O compositor vive no diálogo modal. O envio troca só o corpo e busca o
    // mesmo compositor para exibir erros de validação.
    $dialogBodyId = 'aculta-reply-dialog-body';
    if (!isset($form['#attributes']) || !is_array($form['#attributes'])) {
      $form['#attributes'] = [];
    }
    $form['#attributes']['data-hx-target'] = '#' . $dialogBodyId;
    $form['#attributes']['data-hx-select'] = '[data-discussion-reply-composer]';
    $form['#attributes']['data-hx-swap'] = 'innerHTML';

    $parentSubject = NULL;
    $parentAuthor = NULL;
    $parentCacheTags = [];
    if ($parentId !== NULL && $parentId > 0) {
      $parent = $commentStorage->load($parentId);
      if ($parent instanceof CommentInterface) {
        $parentSubject = trim((string) $parent->label());
        if ($parentSubject === '') {
          $parentSubject = $this->parentBodyExcerpt($parent);
        }
        $parentAuthor = $parent->getAuthorName();
        $parentCacheTags = $parent->getCacheTags();
      }
    }

    $composerId = 'discussion-composer-' . $host->getEntityTypeId() . '-' . $host->id() . ($parentId ? '-' . $parentId : '');

    return [
      '#theme' => 'discussion_reply_composer',
      '#status' => 'editando',
      '#target_id' => $composerId,
      '#parent_id' => $parentId,
      '#parent_author' => $parentAuthor,
      '#parent_subject' => $parentSubject,
      '#host_title' => $host->label(),
      '#form' => $form,
      '#cache' => [
        'contexts' => ['user.permissions', 'url.query_args'],
        'tags' => $parentCacheTags,
      ],
    ];
  }

  /**
   * Builds a revisionable ForumPost form for a current Growroom topic.
   *
   * @return array<string, mixed>
   *   A composer wrapping the ForumPost submission form.
   */
  private function buildForumPostReplyForm(NodeInterface $topic, ?int $parentId, ?string $quote): array {
    $parent = NULL;
    if ($parentId !== NULL && $parentId > 0) {
      $loadedParent = $this->entityTypeManager->getStorage('aculta_forum_post')->load($parentId);
      if (!$loadedParent instanceof ForumPost) {
        return [
          '#theme' => 'discussion_reply_composer',
          '#status' => 'acesso_revogado',
          '#target_id' => 'discussion-composer-node-' . $topic->id() . '-' . $parentId,
          '#cache' => ['contexts' => ['user.permissions']],
        ];
      }
      $parent = $loadedParent;
    }

    $post = $this->entityTypeManager->getStorage('aculta_forum_post')->create([
      'topic' => $topic->id(),
      'parent' => $parent?->id(),
    ]);
    $form = $this->entityFormBuilder->getForm($post, 'public_submission');
    if ($quote !== NULL && $quote !== '') {
      $this->applyQuoteToField($form, 'body', $quote);
    }
    if (!isset($form['#attributes']) || !is_array($form['#attributes'])) {
      $form['#attributes'] = [];
    }
    $form['#attributes']['data-hx-target'] = '#aculta-reply-dialog-body';
    $form['#attributes']['data-hx-select'] = '[data-discussion-reply-composer]';
    $form['#attributes']['data-hx-swap'] = 'innerHTML';

    $parentSubject = $parent instanceof ForumPost ? trim((string) $parent->label()) : NULL;
    $parentAuthor = $parent instanceof ForumPost ? $parent->getOwner()->getDisplayName() : NULL;
    return [
      '#theme' => 'discussion_reply_composer',
      '#status' => 'editando',
      '#target_id' => 'discussion-composer-node-' . $topic->id() . ($parentId ? '-' . $parentId : ''),
      '#parent_id' => $parentId,
      '#parent_author' => $parentAuthor,
      '#parent_subject' => $parentSubject,
      '#host_title' => $topic->label(),
      '#form' => $form,
      '#cache' => [
        'contexts' => ['user.permissions', 'url.query_args'],
        'tags' => $parent?->getCacheTags() ?? [],
      ],
    ];
  }

  /**
   * Prefills a body widget with a sanitized quote.
   *
   * @param array<array-key, mixed> $form
   *   The processed entity form render array.
   * @param string $fieldName
   *   The body field machine name.
   * @param string $quote
   *   The quote text.
   */
  private function applyQuoteToField(array &$form, string $fieldName, string $quote): void {
    if (!isset($form[$fieldName]) || !is_array($form[$fieldName])) {
      return;
    }
    $field = &$form[$fieldName];
    if (!isset($field['widget']) || !is_array($field['widget'])
      || !isset($field['widget'][0]) || !is_array($field['widget'][0])) {
      return;
    }
    $widget = &$field['widget'][0];
    $body = NULL;
    if (isset($widget['value']) && is_array($widget['value'])) {
      $body = &$widget['value'];
    }
    elseif (array_key_exists('#default_value', $widget)) {
      $body = &$widget;
    }
    if (is_array($body) && empty($body['#default_value']) && empty($body['#value'])) {
      $body['#default_value'] = $quote;
      $body['#value'] = $quote;
    }
  }

  /**
   * Builds an edit composer for a discussion comment inside the popup.
   *
   * Saves through the comment edit route and redirects to its permalink.
   *
   * @return array<string, mixed>
   *   A composer render array containing the edit form.
   */
  public function buildEditForm(CommentInterface $comment, AccountInterface $account): array {
    $host = $comment->getCommentedEntity();
    $composerId = 'discussion-composer-' . $comment->getCommentedEntityTypeId() . '-' . $comment->getCommentedEntityId() . '-' . $comment->id();
    if (!$host instanceof EntityInterface || !$comment->access('update', $account)) {
      return [
        '#theme' => 'discussion_reply_composer',
        '#status' => 'acesso_revogado',
        '#mode' => 'editar',
        '#target_id' => $composerId,
        '#messages' => [
          '#markup' => '<div class="alert alert--warning">' . $this->t('Você não tem permissão para editar este comentário.') . '</div>',
        ],
        '#cache' => [
          'contexts' => ['user.permissions'],
        ],
      ];
    }

    $form = $this->entityFormBuilder->getForm($comment);
    // The popup replaces its dialog body after form submission.
    if (!isset($form['#attributes']) || !is_array($form['#attributes'])) {
      $form['#attributes'] = [];
    }
    $form['#attributes']['data-hx-target'] = '#aculta-reply-dialog-body';
    $form['#attributes']['data-hx-select'] = '[data-discussion-reply-composer]';
    $form['#attributes']['data-hx-swap'] = 'innerHTML';

    return [
      '#theme' => 'discussion_reply_composer',
      '#status' => 'editando',
      '#mode' => 'editar',
      '#target_id' => $composerId,
      '#host_title' => $host->label(),
      '#form' => $form,
      '#cache' => [
        'contexts' => ['user.permissions', 'url.query_args'],
        'tags' => $comment->getCacheTags(),
      ],
    ];
  }

  /**
   * Builds a short plain-text excerpt of a comment body.
   */
  private function parentBodyExcerpt(CommentInterface $parent): ?string {
    if (!$parent->hasField('comment_body') || $parent->get('comment_body')->isEmpty()) {
      return NULL;
    }
    $bodyItem = $parent->get('comment_body')->get(0);
    if ($bodyItem === NULL) {
      return NULL;
    }
    $processedValue = $bodyItem->get('processed')->getValue();
    $processed = is_scalar($processedValue) ? (string) $processedValue : '';
    $text = trim((string) preg_replace('/[\s\x{00A0}]+/u', ' ', Html::decodeEntities(strip_tags($processed))));
    if ($text === '') {
      return NULL;
    }
    return Unicode::truncate($text, 60, TRUE, TRUE);
  }

}
