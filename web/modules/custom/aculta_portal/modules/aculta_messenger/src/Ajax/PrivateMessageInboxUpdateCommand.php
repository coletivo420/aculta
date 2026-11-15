<?php

declare(strict_types=1);

namespace Drupal\aculta_messenger\Ajax;

use Drupal\Core\Ajax\CommandInterface;

/**
 * Ajax command to update the private message inbox block.
 */
class PrivateMessageInboxUpdateCommand implements CommandInterface {

  /**
   * Constructs a new command instance.
   *
   * @param list<int|string> $threadIds
   *   The thread IDs, in the order that they should appear when the inbox is
   *   updated.
   * @param array<int|string, mixed> $newThreads
   *   The HTML for the messages to be inserted in the page.
   */
  public function __construct(
    protected readonly array $threadIds,
    protected readonly array $newThreads,
  ) {}

  /**
   * {@inheritdoc}
   *
   * @return array<string, mixed>
   */
  public function render(): array {
    return [
      'command' => 'privateMessageInboxUpdate',
      'threadIds' => $this->threadIds,
      'newThreads' => $this->newThreads,
    ];
  }

}
