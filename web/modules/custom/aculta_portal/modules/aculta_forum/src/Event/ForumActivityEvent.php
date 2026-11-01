<?php

declare(strict_types=1);

namespace Drupal\aculta_forum\Event;

use Symfony\Contracts\EventDispatcher\Event;

/**
 * Atividade criada no Fórum: tópico, Comment legado ou post revisionável.
 *
 * Produtor de eventos para `aculta_statistics` e outros consumidores.
 * Carrega só o identificador da entidade e o instante da criação: nenhum autor,
 * texto ou e-mail. Consumidores buscam o restante pelas APIs do dono, se
 * preciso.
 */
final class ForumActivityEvent extends Event {

  /**
   * Nome do evento para tópico criado (nó `aculta_forum_topic`).
   */
  public const TOPIC_CREATED = 'aculta_forum.topic_created';

  /**
   * Nome do evento para comentário criado num tópico do Fórum.
   */
  public const COMMENT_CREATED = 'aculta_forum.comment_created';

  /**
   * Nome do evento para post revisionável criado no Fórum.
   */
  public const POST_CREATED = 'aculta_forum.post_created';

  public function __construct(
    public readonly int $entityId,
    public readonly int $occurredAt,
  ) {}

}
