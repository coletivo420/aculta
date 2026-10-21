<?php

declare(strict_types=1);

namespace Drupal\aculta_user\Event;

/**
 * Uma conta de usuário foi excluída.
 *
 * Consumidores apagam os dados pessoais que a conta deixou fora da própria entidade. Carrega só o identificador.
 */
final readonly class AccountDeletedEvent {

  public const NAME = 'aculta_user.account_deleted';

  public function __construct(
    public int $uid,
  ) {}

}
