<?php

declare(strict_types=1);

namespace Drupal\aculta_user\Event;

use Symfony\Contracts\EventDispatcher\Event;

/**
 * Transição de amizade que outros módulos podem notificar (pedido e aceite).
 *
 * Carrega identificadores e o instante da transição, sem dados pessoais.
 * O Messenger usa o evento para criar avisos e e-mail mínimo; o aculta_user
 * não conhece o Messenger.
 */
final class FriendshipChangedEvent extends Event {

  /**
   * Unique identifier for this persisted relationship transition.
   *
   * The changed timestamp has second precision and is not unique when a member
   * withdraws and re-sends a request within the same second.
   */
  public readonly string $transitionId;

  /**
   * Nome do evento.
   */
  public const NAME = 'aculta_user.friendship_changed';

  /**
   * Pedido de amizade registrado (pendente).
   */
  public const REQUESTED = 'requested';

  /**
   * Pedido aceito.
   */
  public const ACCEPTED = 'accepted';

  public function __construct(
    public readonly string $transition,
    public readonly string $friendshipId,
    public readonly int $requesterId,
    public readonly int $recipientId,
    public readonly int $changed,
    ?string $transitionId = NULL,
  ) {
    $this->transitionId = $transitionId ?? bin2hex(random_bytes(16));
  }

}
