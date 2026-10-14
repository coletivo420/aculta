<?php

declare(strict_types=1);

namespace Drupal\aculta_messenger\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Session\AccountInterface;
use Drupal\private_message\Entity\PrivateMessageInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/** Prepares privacy-aware, theme-neutral sender data for message rendering. */
final class PrivateMessagePresentationHooks {

  public function __construct(
    #[Autowire(service: 'current_user')]
    private readonly AccountInterface $currentUser,
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

    // Render only the native picture field. A missing user view mode can fall
    // back to profile actions with unset labels in some site configurations.
    if (!$sender->hasField('user_picture') || $sender->get('user_picture')->isEmpty()) {
      return;
    }
    $variables['aculta_messenger_sender_avatar'] = $sender->get('user_picture')->view('default');
    $variables['aculta_messenger_sender_avatar']['#cache']['contexts'][] = 'user';
    $variables['aculta_messenger_sender_avatar']['#cache']['tags'] = array_merge(
      $variables['aculta_messenger_sender_avatar']['#cache']['tags'] ?? [],
      $access->getCacheTags(),
    );
  }

}
