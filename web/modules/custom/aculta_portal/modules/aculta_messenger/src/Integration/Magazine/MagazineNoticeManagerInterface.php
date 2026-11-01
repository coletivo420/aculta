<?php

declare(strict_types=1);

namespace Drupal\aculta_messenger\Integration\Magazine;

use Drupal\aculta_messenger\Aviso\AvisoDigestProviderIdentityInterface;
use Drupal\aculta_messenger\Aviso\AvisoDigestSelectiveDiscardInterface;
use Drupal\aculta_messenger\Aviso\PaginatedPersonalNoticeAdapterInterface;
use Drupal\aculta_messenger\Event\MessengerNoticeEvent;
use Drupal\Core\Session\AccountInterface;
use Drupal\message\MessageInterface;

/**
 * Public adapter contract for Revista notices in the Messenger. */
interface MagazineNoticeManagerInterface extends PaginatedPersonalNoticeAdapterInterface, AvisoDigestProviderIdentityInterface, AvisoDigestSelectiveDiscardInterface {

  /**
   * Persists an eligible Revista publication event for its recipient. */
  public function record(MessengerNoticeEvent $event): ?MessageInterface;

  /**
   * Resolves a current accessible publication and safe notice presentation. */
  public function resolve(MessageInterface $message, AccountInterface $account): ?array;

  /**
   * Changes read state after revalidating ownership and publication access. */
  public function setRead(MessageInterface $message, AccountInterface $account, bool $read): bool;

}
