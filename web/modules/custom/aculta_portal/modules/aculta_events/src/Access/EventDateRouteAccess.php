<?php

declare(strict_types=1);

namespace Drupal\aculta_events\Access;

use Drupal\aculta_events\Entity\Event;
use Drupal\aculta_events\Entity\EventDate;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Garante que uma Data só seja editada a partir do Evento ao qual pertence.
 *
 * A rota já exige acesso de atualização ao Evento. Esta verificação impede que
 * uma Data de outro evento seja manipulada trocando o ID na URL.
 */
final class EventDateRouteAccess {

  /**
   * Verifica o vínculo entre a Data e o Evento da rota.
   */
  public static function access(Event $aculta_event, EventDate $aculta_event_date, AccountInterface $account): AccessResultInterface {
    $belongs = (int) $aculta_event_date->get('field_evento')->target_id === (int) $aculta_event->id();

    return AccessResult::allowedIf($belongs)
      ->addCacheableDependency($aculta_event)
      ->addCacheableDependency($aculta_event_date)
      ->addCacheContexts(['user.permissions']);
  }

}
