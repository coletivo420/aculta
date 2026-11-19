<?php

declare(strict_types=1);

namespace Drupal\aculta_messenger\Plugin\Menu;

use Drupal\Core\Menu\LocalTaskDefault;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\aculta_messenger\Entity\PrivateMessage;

/**
 * Adds private message ID to private message tabs.
 */
class PrivateMessageTab extends LocalTaskDefault {

  /**
   * {@inheritdoc}
   *
   * @param RouteMatchInterface $route_match
   *   A correspondência da rota atual.
   *
   * @return array<string, mixed>
   *   A lista com os valores resultantes.
   */
  public function getRouteParameters(RouteMatchInterface $route_match): array {
    $private_message = $route_match->getParameter('private_message');
    if ($private_message instanceof PrivateMessage) {
      $id = $private_message->id();
    }
    else {
      $id = 0;
    }

    return [
      'private_message' => $id,
    ];
  }

}
