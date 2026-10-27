<?php

declare(strict_types=1);

namespace Drupal\aculta_messenger\Hook;

use Drupal\aculta_user\Avatar\AvatarResolver;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Session\AccountInterface;
use Drupal\private_message\Entity\PrivateMessageInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/** Prepares privacy-aware, theme-neutral sender data for message rendering. */
final class PrivateMessagePresentationHooks {

  public function __construct(
    #[Autowire(service: 'current_user')]
    private readonly AccountInterface $currentUser,
    #[Autowire(service: 'aculta_user.avatar_resolver')]
    private readonly AvatarResolver $avatars,
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

    // Mesma regra do avatar público: foto, depois acervo ilustrativo, depois inicial.
    $name = (string) $sender->getDisplayName();
    $cacheability = (new CacheableMetadata())
      ->addCacheableDependency($sender)
      ->addCacheContexts(['user'])
      ->addCacheTags($access->getCacheTags());
    $avatar = $this->avatars->build($sender, $name, TRUE, $cacheability);
    $avatar ??= [
      '#type' => 'html_tag',
      '#tag' => 'span',
      '#value' => mb_strtoupper(mb_substr($name, 0, 1)),
    ];
    $cacheability->applyTo($avatar);
    $variables['aculta_messenger_sender_avatar'] = $avatar;
  }

}
