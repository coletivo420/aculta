<?php

declare(strict_types=1);

namespace Drupal\aculta_messenger\Service;

use Drupal\aculta_messenger\Integration\Commerce\CommerceOrderNoticeManagerInterface;
use Drupal\aculta_messenger\Integration\Donation\DonationNoticeManagerInterface;
use Drupal\aculta_messenger\Integration\Lms\LearningNoticeManagerInterface;
use Drupal\aculta_messenger\Integration\Wiki\WikiNoticeManagerInterface;
use Drupal\aculta_messenger\Friendship\FriendshipNoticeManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\message\MessageInterface;
use Psr\Container\ContainerInterface;

/**
 * Dispatches central read-state changes to the owner of each notice source.
 */
final class NoticeReadStateManager implements NoticeReadStateManagerInterface {

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly array|ContainerInterface $adapters,
  ) {}

  /**
   * Changes read state only when the owning adapter still grants access.
   */
  public function setRead(int $messageId, AccountInterface $account, bool $read): bool {
    if ($messageId < 1 || !$account->isAuthenticated()) {
      return FALSE;
    }
    $message = $this->entityTypeManager->getStorage('message')->load($messageId);
    if (!$message instanceof MessageInterface) {
      return FALSE;
    }
    if ((int) $message->getOwnerId() !== (int) $account->id()) {
      return FALSE;
    }

    return match ($message->bundle()) {
      'aculta_msg_lms_notice' => $this->setLmsRead($message, $account, $read),
      'aculta_msg_wiki_notice' => $this->setWikiRead($message, $account, $read),
      'aculta_msg_commerce_order_notice' => $this->setCommerceRead($message, $account, $read),
      'aculta_msg_donation_notice' => $this->setDonationRead($message, $account, $read),
      'aculta_msg_friendship' => $this->setFriendshipRead($message, $account, $read),
      default => FALSE,
    };
  }

  /**
   * Delegates LMS read state when its optional adapter is enabled.
   */
  private function setLmsRead(MessageInterface $message, AccountInterface $account, bool $read): bool {
    if (!$this->hasAdapter('lms')) {
      return FALSE;
    }
    $manager = $this->getAdapter('lms');
    return $manager instanceof LearningNoticeManagerInterface
      && $manager->setRead($message, $account, $read);
  }

  /**
   * Delegates Wiki read state when its optional adapter is enabled.
   */
  private function setWikiRead(MessageInterface $message, AccountInterface $account, bool $read): bool {
    if (!$this->hasAdapter('wiki')) {
      return FALSE;
    }
    $manager = $this->getAdapter('wiki');
    return $manager instanceof WikiNoticeManagerInterface
      && $manager->setRead($message, $account, $read);
  }

  /**
   * Delegates Commerce read state when its optional adapter is enabled.
   */
  private function setCommerceRead(MessageInterface $message, AccountInterface $account, bool $read): bool {
    if (!$this->hasAdapter('commerce')) {
      return FALSE;
    }
    $manager = $this->getAdapter('commerce');
    return $manager instanceof CommerceOrderNoticeManagerInterface
      && $manager->setRead($message, $account, $read);
  }

  /**
   * Delegates Donation read state when its optional adapter is enabled.
   */
  private function setDonationRead(MessageInterface $message, AccountInterface $account, bool $read): bool {
    if (!$this->hasAdapter('donation')) {
      return FALSE;
    }
    $manager = $this->getAdapter('donation');
    return $manager instanceof DonationNoticeManagerInterface
      && $manager->setRead($message, $account, $read);
  }

  /**
   * Delegates friendship read state to the core-owned shared adapter.
   */
  private function setFriendshipRead(MessageInterface $message, AccountInterface $account, bool $read): bool {
    if (!$this->hasAdapter('friendship')) {
      return FALSE;
    }
    $manager = $this->getAdapter('friendship');
    return $manager instanceof FriendshipNoticeManagerInterface
      && $manager->setRead($message, $account, $read);
  }

  /**
   * Checks whether an optional adapter exists in the compiled service locator.
   */
  private function hasAdapter(string $adapter): bool {
    return $this->adapters instanceof ContainerInterface
      ? $this->adapters->has($adapter)
      : isset($this->adapters[$adapter]);
  }

  /**
   * Gets an optional adapter without making its source module mandatory.
   */
  private function getAdapter(string $adapter): mixed {
    return $this->adapters instanceof ContainerInterface
      ? $this->adapters->get($adapter)
      : ($this->adapters[$adapter] ?? NULL);
  }

}
