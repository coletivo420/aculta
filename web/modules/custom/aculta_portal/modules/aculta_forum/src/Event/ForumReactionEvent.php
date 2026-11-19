<?php

declare(strict_types=1);

namespace Drupal\aculta_forum\Event;

use Symfony\Contracts\EventDispatcher\Event;

/**
 * Reação nova em conteúdo público do Fórum.
 *
 * Só a inclusão inicial emite o evento. Troca de emoji e remoção não geram
 * `added`; o estoque atual é calculado na fonte. O evento carrega apenas o
 * identificador da reação, sem autoria, alvo ou texto.
 */
final class ForumReactionEvent extends Event {

  /**
   * Nome do evento de reação adicionada.
   */
  public const ADDED = 'aculta_forum.reaction_added';

  public function __construct(
    public readonly string $reaction,
  ) {}

}
