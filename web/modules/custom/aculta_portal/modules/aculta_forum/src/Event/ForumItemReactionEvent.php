<?php

declare(strict_types=1);

namespace Drupal\aculta_forum\Event;

use Symfony\Contracts\EventDispatcher\Event;

/**
 * Curtida positiva nova em um item público do Fórum, com alvo e autoria.
 *
 * Evento interno para marcos do Game (tópico e resposta). Diferente de
 * ForumReactionEvent, que é anônimo e alimenta estatística, este carrega o
 * alvo e a autoria; por isso só deve ser consumido por serviços do próprio Fórum
 * e do Game. Emitido somente em inclusão ou troca para reação positiva pública.
 */
final class ForumItemReactionEvent extends Event {

  /**
   * Nome do evento de curtida positiva em item.
   */
  public const ADDED = 'aculta_forum.item_reaction_positive_added';

  public function __construct(
    public readonly string $entityType,
    public readonly int $entityId,
    public readonly int $authorUid,
    public readonly int $uid,
  ) {}

}
