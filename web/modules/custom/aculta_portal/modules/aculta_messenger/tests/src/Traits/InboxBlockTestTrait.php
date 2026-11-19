<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_messenger\Traits;

use Drupal\aculta_messenger\Entity\PrivateMessageThreadInterface;

/**
 * Reusable inbox block code.
 */
trait InboxBlockTestTrait {

  /**
   * Get threads.
   *
   * @return array<\Behat\Mink\Element\NodeElement>
   *   Threads.
   */
  protected function getThreads(): array {
    return $this->getSession()
      ->getPage()
      ->findAll('css', '.private-message-thread-inbox');
  }

  /**
   * Counts threads.
   *
   * @return int
   *   Number of threads.
   */
  protected function countThreads(): int {
    return count($this->getThreads());
  }

  /**
   * Click on thread.
   *
   * @param \Drupal\aculta_messenger\Entity\PrivateMessageThreadInterface $thread
   *   Thread entity.
   */
  protected function clickThread(PrivateMessageThreadInterface $thread): void {
    $this->click('.private-message-inbox-thread-link[data-thread-id="' . $thread->id() . '"]');
  }

}
