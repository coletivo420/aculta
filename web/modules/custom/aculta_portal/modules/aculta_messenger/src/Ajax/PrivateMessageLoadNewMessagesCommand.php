<?php

declare(strict_types=1);

namespace Drupal\aculta_messenger\Ajax;

use Drupal\Core\Ajax\CommandInterface;

/**
 * Ajax Command to trigger a load of new private messages into a thread.
 */
class PrivateMessageLoadNewMessagesCommand implements CommandInterface {

  /**
   * {@inheritdoc}
   *
   * @return array<string, mixed>
   *   A lista com os valores resultantes.
   */
  public function render(): array {
    return [
      'command' => 'loadNewPrivateMessages',
    ];
  }

}
