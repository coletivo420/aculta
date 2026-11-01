<?php

declare(strict_types=1);

namespace Drupal\aculta_messenger\Aviso;

use Drupal\aculta_forum\Entity\ForumPost;
use Drupal\comment\CommentInterface;
use Drupal\node\NodeInterface;

/**
 * Persists an eligible Forum notice in the shared notice aggregate.
 */
interface ForumNoticeRegistrarInterface {

  /**
   * Registers a published Comment notice for eligible topic followers.
   */
  public function registrarResposta(CommentInterface $comment, ?int $recipientUid = NULL): int;

  /**
   * Registers a published ForumPost notice for eligible topic followers.
   */
  public function registrarPost(ForumPost $post, ?int $recipientUid = NULL): int;

  /**
   * Registers a topic revision notice for eligible followers.
   */
  public function notificarAtualizacao(NodeInterface $topic, int $revisionId, ?int $recipientUid = NULL): int;

}
