<?php

declare(strict_types=1);

namespace Drupal\aculta_messenger\Ajax;

use Drupal\Core\Ajax\CommandInterface;

/**
 * Ajax command to update the number of unread threads.
 *
 * This command is implemented by
 * Drupal.AjaxCommands.prototype.privateMessageUpdateUnreadItemsCount()
 */
class PrivateMessageUpdateUnreadItemsCountCommand implements CommandInterface {

  public function __construct(protected readonly int $unreadThreadsCount) {}

  /**
   * {@inheritdoc}
   *
   * @return array<string, mixed>
   */
  public function render(): array {
    return [
      'command' => 'privateMessageUpdateUnreadItemsCount',
      'unreadItemsCount' => $this->unreadThreadsCount,
    ];
  }

}
