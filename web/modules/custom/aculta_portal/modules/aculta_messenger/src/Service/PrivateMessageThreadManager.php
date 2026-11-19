<?php

declare(strict_types=1);

namespace Drupal\aculta_messenger\Service;

use Drupal\aculta_messenger\Entity\PrivateMessageInterface;
use Drupal\aculta_messenger\Entity\PrivateMessageThreadInterface;

/**
 * The Private Message generator class.
 */
class PrivateMessageThreadManager implements PrivateMessageThreadManagerInterface {

  public function __construct(
    protected readonly PrivateMessageServiceInterface $privateMessageService,
  ) {}

  /**
   * {@inheritdoc}
   *
   * @param PrivateMessageInterface $message
   *   A mensagem.
   * @param \Drupal\user\UserInterface[] $members
   *   Os membros da conversa.
   * @param PrivateMessageThreadInterface|null $thread
   *   A conversa.
   */
  public function saveThread(PrivateMessageInterface $message, array $members = [], ?PrivateMessageThreadInterface $thread = NULL): void {
    if ($thread === NULL && $members) {
      $thread = $this->privateMessageService->getThreadForMembers($members);
    }

    if ($thread instanceof PrivateMessageThreadInterface) {
      $thread->addMessage($message)->save();
    }
  }

}
