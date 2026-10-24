<?php

declare(strict_types=1);

namespace Drupal\aculta_messenger\Hook;

use Drupal\aculta_messenger\Aviso\PersonalNoticeAdapterInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Session\AccountInterface;
use Psr\Container\ContainerInterface;

/**
 * Dispatches optional domain adapters into the shared notice list and count.
 */
final class PersonalNoticeAdapterHooks {

  /**
   * Optional personal-notice adapters available in this site container.
   *
   * @var string[]
   */
  private const ADAPTERS = ['lms', 'wiki', 'commerce', 'donation'];

  /**
   * Constructs the hook dispatcher.
   *
   * @param array<string, object>|\Psr\Container\ContainerInterface $adapters
   *   Optional adapter managers registered by the Messenger service provider.
   */
  public function __construct(
    private readonly array|ContainerInterface $adapters,
  ) {}

  /**
   * Adds each enabled adapter's authorized notices to the shared list.
   */
  #[Hook('aculta_messenger_personal_notices_alter')]
  public function noticesAlter(array &$items, AccountInterface $account, array $filters = []): void {
    foreach (self::ADAPTERS as $id) {
      $adapter = $this->adapter($id);
      if ($adapter instanceof PersonalNoticeAdapterInterface) {
        $adapter->addPersonalNotices($items, $account, $filters);
      }
    }
  }

  /**
   * Adds each enabled adapter's accessible unread notices to the shared count.
   */
  #[Hook('aculta_messenger_personal_notice_count_alter')]
  public function unreadCountAlter(int &$count, int $uid): void {
    foreach (self::ADAPTERS as $id) {
      $adapter = $this->adapter($id);
      if ($adapter instanceof PersonalNoticeAdapterInterface) {
        $count += $adapter->unreadCount($uid);
      }
    }
  }

  /**
   * Returns one enabled optional adapter, if present in the service locator.
   */
  private function adapter(string $id): mixed {
    if ($this->adapters instanceof ContainerInterface) {
      return $this->adapters->has($id) ? $this->adapters->get($id) : NULL;
    }
    return $this->adapters[$id] ?? NULL;
  }

}
