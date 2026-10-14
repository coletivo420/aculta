<?php

declare(strict_types=1);

namespace Drupal\aculta_messenger\Hook;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Session\AccountInterface;
use Drupal\private_message\Entity\PrivateMessageInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/** Prepares privacy-aware, theme-neutral sender data for message rendering. */
final class PrivateMessagePresentationHooks {

  public function __construct(
    #[Autowire(service: 'current_user')]
    private readonly AccountInterface $currentUser,
    #[Autowire(service: 'entity_type.manager')]
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /** Adds the sender avatar and direction state without changing message data. */
  #[Hook('preprocess_private_message')]
  public function preprocessPrivateMessage(array &$variables): void {
    $message = $variables['private_message'] ?? $variables['elements']['#private_message'] ?? NULL;
    if (!$message instanceof PrivateMessageInterface) {
      return;
    }

    $variables['aculta_messenger_outgoing'] = (int) $message->getOwnerId() === (int) $this->currentUser->id();
    $sender = $message->getOwner();
    if ($sender === NULL) {
      return;
    }
    $access = $sender->access('view', $this->currentUser, TRUE);
    if (!$access->isAllowed()) {
      return;
    }

    $variables['aculta_messenger_sender_avatar'] = $this->entityTypeManager
      ->getViewBuilder('user')
      ->view($sender, 'compact');
    $variables['aculta_messenger_sender_avatar']['#cache']['contexts'][] = 'user';
    $variables['aculta_messenger_sender_avatar']['#cache']['tags'] = array_merge(
      $variables['aculta_messenger_sender_avatar']['#cache']['tags'] ?? [],
      $access->getCacheTags(),
    );
  }

}
