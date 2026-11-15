<?php

declare(strict_types=1);

namespace Drupal\aculta_messenger\Service;

use Drupal\aculta_messenger\Entity\PrivateMessageInterface;
use Drupal\aculta_messenger\Entity\PrivateMessageThreadInterface;

/**
 * Handles the generation/update of private messages, threads, and metadata.
 */
interface PrivateMessageThreadManagerInterface {

  /**
   * Saves a private message thread.
   *
   * A new thread will be created if one does not already exist.
   *
   * @param \Drupal\aculta_messenger\Entity\PrivateMessageInterface $message
   *   The new message object.
   * @param array<string, mixed> $members
   *   (optional) An array of thread members. Defaults to an empty array.
   * @param \Drupal\aculta_messenger\Entity\PrivateMessageThreadInterface|null $thread
   *   (optional) The private message thread. If NULL, one will be loaded
   *   using the members array.
   *
   * @return void
   */
  public function saveThread(PrivateMessageInterface $message, array $members = [], ?PrivateMessageThreadInterface $thread = NULL);

}
