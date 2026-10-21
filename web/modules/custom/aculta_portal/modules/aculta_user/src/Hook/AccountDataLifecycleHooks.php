<?php

declare(strict_types=1);

namespace Drupal\aculta_user\Hook;

use Drupal\aculta_user\Event\AccountDeletedEvent;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\user\UserInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Avisa os demais módulos quando uma conta é excluída, para apagarem os dados que dependem dela.
 */
final class AccountDataLifecycleHooks {

  public function __construct(
    private readonly EventDispatcherInterface $dispatcher,
  ) {}

  /**
   * Emite AccountDeletedEvent depois da exclusão da conta.
   */
  #[Hook('user_delete')]
  public function accountDeleted(UserInterface $account): void {
    $this->dispatcher->dispatch(new AccountDeletedEvent((int) $account->id()), AccountDeletedEvent::NAME);
  }

}
