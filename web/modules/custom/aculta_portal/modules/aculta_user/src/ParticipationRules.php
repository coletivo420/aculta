<?php

declare(strict_types=1);

namespace Drupal\aculta_user;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\comment\CommentInterface;
use Drupal\node\NodeInterface;

/**
 * Regras de participação no Fórum (plano aculta_user, seções E6 e E7).
 *
 * Tudo é derivado de contagens reais de comentários publicados.
 * Não há contador persistido.
 * Editor e administrador são configuráveis como isentos. Participação LMS
 * segue a regra específica descrita nesta classe.
 */
final class ParticipationRules {

  public const COMMENT_TYPE = 'comment_forum';

  public const DISCUSSION_COMMENT_TYPE = 'aculta_discussion_post';

  public const TOPIC_TYPE = 'forum';

  public function __construct(
    private readonly ConfigFactoryInterface $configFactory,
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * Returns the active settings with safe defaults for missing keys.
   */
  public function settings(): array {
    return $this->configFactory->get('aculta_user.settings')->getRawData() + [
      'topico_limite_ativo' => TRUE,
      'topico_comentarios_minimos' => 10,
      'primeiro_comentario_moderado' => TRUE,
      'primeiro_comentario_discussao_moderado' => TRUE,
      'bloqueio_ate_aprovacao' => TRUE,
      'papeis_isentos' => ['content_editor', 'administrator'],
    ];
  }

  /**
   * Whether the account is exempt by role.
   */
  public function isExempt(AccountInterface $account): bool {
    $exempt = $this->settings()['papeis_isentos'];
    return array_intersect($exempt, $account->getRoles()) !== [];
  }

  /**
   * Number of published legacy Forum comments by the account.
   */
  public function publishedCount(int $uid): int {
    return (int) $this->commentQuery($uid, TRUE)->count()->execute();
  }

  /**
   * Number of unpublished legacy Forum comments by the account.
   */
  public function pendingCount(int $uid): int {
    return (int) $this->commentQuery($uid, FALSE)->count()->execute();
  }

  /**
   * Whether the account has pending legacy comments and no published ones.
   */
  public function firstCommentPending(int $uid): bool {
    return $this->publishedCount($uid) === 0 && $this->pendingCount($uid) > 0;
  }

  /**
   * Whether a new shared comment belongs to a non-LMS discussion context.
   */
  public function isNonLmsDiscussionComment(CommentInterface $comment): bool {
    if ($comment->bundle() !== self::DISCUSSION_COMMENT_TYPE ||
      $comment->getFieldName() !== 'field_aculta_discussion' ||
      $comment->getCommentedEntityTypeId() !== 'node') {
      return FALSE;
    }

    $host = $comment->getCommentedEntity();
    return $host instanceof NodeInterface && $host->bundle() !== 'aculta_lms_topic';
  }

  /**
   * Whether an account has any published shared discussion comment.
   *
   * A published LMS comment counts as accepted participation for the other
   * contexts, even though LMS comments never enter public moderation.
   */
  public function hasPublishedDiscussionComment(int $uid): bool {
    return $this->discussionCommentQuery($uid, TRUE, TRUE)->range(0, 1)->count()->execute() > 0;
  }

  /**
   * Whether an account has a pending comment in a non-LMS context.
   */
  public function hasPendingDiscussionComment(int $uid): bool {
    return $this->discussionCommentQuery($uid, FALSE)->range(0, 1)->count()->execute() > 0;
  }

  /**
   * Whether the first non-LMS discussion comment is still awaiting approval.
   */
  public function firstDiscussionCommentPending(int $uid): bool {
    return !$this->hasPublishedDiscussionComment($uid) && $this->hasPendingDiscussionComment($uid);
  }

  /**
   * Number of published replies made in public Forum topics.
   */
  public function publishedPublicForumCommentCount(int $uid): int {
    return (int) $this->discussionCommentQuery($uid, TRUE)
      ->condition('entity_id.entity:node.type', 'aculta_forum_topic')
      ->count()
      ->execute();
  }

  /**
   * Builds a query over the shared discussion field.
   */
  private function discussionCommentQuery(int $uid, bool $published, bool $includeLms = FALSE) {
    $query = $this->entityTypeManager->getStorage('comment')->getQuery()
      ->accessCheck(FALSE)
      ->condition('comment_type', self::DISCUSSION_COMMENT_TYPE)
      ->condition('entity_type', 'node')
      ->condition('field_name', 'field_aculta_discussion')
      ->condition('uid', $uid)
      ->condition('status', $published ? 1 : 0);

    if (!$includeLms) {
      $query->condition('entity_id.entity:node.type', 'aculta_lms_topic', '<>');
    }

    return $query;
  }

  /**
   * Builds the legacy Forum comment query used during migration.
   */
  private function commentQuery(int $uid, bool $published) {
    return $this->entityTypeManager->getStorage('comment')->getQuery()
      ->accessCheck(FALSE)
      ->condition('comment_type', self::COMMENT_TYPE)
      ->condition('entity_type', 'node')
      ->condition('uid', $uid)
      ->condition('status', $published ? 1 : 0);
  }

}
