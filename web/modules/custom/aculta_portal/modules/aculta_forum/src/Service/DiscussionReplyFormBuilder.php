<?php

declare(strict_types=1);

namespace Drupal\aculta_forum\Service;

use Drupal\aculta_forum\Context\DiscussionContextResolver;
use Drupal\comment\CommentInterface;
use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\Unicode;
use Drupal\Core\Entity\EntityFormBuilderInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
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
   * @return array
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

    $commentField = 'field_aculta_discussion';

    $commentStorage = $this->entityTypeManager->getStorage('comment');
    $commentValues = [
      'entity_type' => $host->getEntityTypeId(),
      'entity_id' => $host->id(),
      'field_name' => $commentField,
      'comment_type' => 'aculta_discussion_post',
      'pid' => $parentId,
    ];

    /** @var \Drupal\comment\CommentInterface $comment */
    $comment = $commentStorage->create($commentValues);

    $form = $this->entityFormBuilder->getForm($comment);

    // Apply quote if provided and form has comment_body. The entity form is
    // already processed when it comes back from the form builder, so the
    // rendered textarea reads #value; #default_value alone never reaches it.
    if ($quote !== NULL && $quote !== '') {
      if (isset($form['comment_body']['widget'][0]['value'])) {
        $body =& $form['comment_body']['widget'][0]['value'];
      }
      elseif (array_key_exists('#default_value', $form['comment_body']['widget'][0] ?? [])) {
        $body =& $form['comment_body']['widget'][0];
      }
      else {
        $body = NULL;
      }
      if (is_array($body) && empty($body['#default_value']) && empty($body['#value'])) {
        $body['#default_value'] = $quote;
        // Processed text_format renders #value; without it the quote is lost.
        $body['#value'] = $quote;
      }
      unset($body);
    }

    // The composer posts back to the host page, which returns the whole main
    // content. Swapping only the discussion thread keeps the topic aside (which
    // lives at the end of the main column) out of the replaced fragment, so it
    // is not rendered twice after posting a reply.
    // O compositor vive no diálogo modal. O envio troca só o corpo do diálogo e busca o
    // próprio compositor: se a resposta trouxer o compositor (erro de validação), ele
    // aparece no diálogo; se não trouxer, a postagem foi publicada (ver forum-quote.js).
    $dialogBodyId = 'aculta-reply-dialog-body';
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
   * Builds the composer that edits an existing discussion comment inside the popup.
   *
   * O formulário salva na rota de edição do próprio comentário; o envio redireciona ao permalink.
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
    // Mesmo destino de envio do compositor de resposta: o popup troca o corpo do diálogo.
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
    $processed = (string) $bodyItem->get('processed')->getValue();
    $text = trim((string) preg_replace('/[\s\x{00A0}]+/u', ' ', Html::decodeEntities(strip_tags($processed))));
    if ($text === '') {
      return NULL;
    }
    return Unicode::truncate($text, 60, TRUE, TRUE);
  }

}
