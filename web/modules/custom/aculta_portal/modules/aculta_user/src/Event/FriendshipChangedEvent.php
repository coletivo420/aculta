<?php

declare(strict_types=1);

namespace Drupal\aculta_user\Event;

use Symfony\Contracts\EventDispatcher\Event;

/**
 * Transição de amizade que outros módulos podem notificar (pedido e aceite).
 *
 * Carrega só identificadores e o instante da transição, sem dados pessoais. O Messenger usa o evento para criar avisos
 * persistentes e o e-mail mínimo; o aculta_user não conhece o Messenger.
 */
final class FriendshipChangedEvent extends Event {

  /** Nome do evento. */
  public const NAME = 'aculta_user.friendship_changed';

  /** Pedido de amizade registrado (pendente). */
  public const REQUESTED = 'requested';

  /** Pedido aceito. */
  public const ACCEPTED = 'accepted';

  public function __construct(
    public readonly string $transition,
    public readonly string $friendshipId,
    public readonly int $requesterId,
    public readonly int $recipientId,
    public readonly int $changed,
  ) {}

}
