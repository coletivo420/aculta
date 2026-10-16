<?php

declare(strict_types=1);

namespace Drupal\aculta_messenger\Hook;

use Drupal\comment\CommentInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\aculta_messenger\Aviso\AvisoService;

/**
 * Respostas em tópico seguido geram aviso no site (ACULTA Fórum, F5.2).
 */
final class ForumAvisoHooks {

  public function __construct(
    private readonly AvisoService $avisos,
  ) {}

  /**
   * Implements hook_comment_insert().
   */
  #[Hook('comment_insert')]
  public function commentInsert(CommentInterface $comment): void {
    $this->avisos->registrarResposta($comment);
  }

  /**
   * Implements hook_comment_update(). Comentário aprovado depois da criação (moderação) conta na aprovação.
   *
   * A deduplicação por identificador de comentário impede contagem dupla se a atualização se repetir.
   */
  #[Hook('comment_update')]
  public function commentUpdate(CommentInterface $comment): void {
    if ($comment->isPublished() && $comment->original !== NULL && !$comment->original->isPublished()) {
      $this->avisos->registrarResposta($comment);
    }
  }

}
