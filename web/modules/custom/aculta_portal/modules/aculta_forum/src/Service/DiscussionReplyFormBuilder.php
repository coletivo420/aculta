<?php

declare(strict_types=1);

namespace Drupal\aculta_forum\Service;

use Drupal\aculta_forum\Context\DiscussionContextResolver;
use Drupal\comment\CommentInterface;
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

    // Apply quote if provided and form has comment_body.
    if ($quote !== NULL && $quote !== '') {
      if (isset($form['comment_body']['widget'][0]['value'])) {
        if (empty($form['comment_body']['widget'][0]['value']['#default_value'])) {
          $form['comment_body']['widget'][0]['value']['#default_value'] = $quote;
        }
      }
      elseif (isset($form['comment_body']['widget'][0]) && array_key_exists('#default_value', $form['comment_body']['widget'][0])) {
        if (empty($form['comment_body']['widget'][0]['#default_value'])) {
          $form['comment_body']['widget'][0]['#default_value'] = $quote;
        }
      }
    }

    $parentSubject = NULL;
    $parentAuthor = NULL;
    if ($parentId !== NULL && $parentId > 0) {
      $parent = $commentStorage->load($parentId);
      if ($parent instanceof CommentInterface) {
        $parentSubject = $parent->label();
        $parentAuthor = $parent->getAuthorName();
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
      ],
    ];
  }

}
