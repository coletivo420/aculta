<?php

declare(strict_types=1);

namespace Drupal\aculta_messenger\Hook;

use Drupal\aculta_messenger\Aviso\PersonalNoticeAdapterInterface;
use Drupal\aculta_messenger\Aviso\PaginatedPersonalNoticeAdapterInterface;
use Drupal\aculta_messenger\Aviso\PersonalNoticePageState;
use Drupal\aculta_messenger\Service\NoticeListFilter;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Session\AccountInterface;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

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
   * Maps adapter service IDs to central-list origins.
   *
   * @var array<string, string>
   */
  private const ORIGINS = [
    'lms' => 'aprendizagem',
    'wiki' => 'wiki',
    'commerce' => 'loja',
    'donation' => 'doacao',
  ];

  /**
   * Constructs the hook dispatcher.
   *
   * @param array<string, object>|\Psr\Container\ContainerInterface $adapters
   *   Optional adapter managers registered by the Messenger service provider.
   * @param \Psr\Log\LoggerInterface|null $logger
   *   Logger for adapter failures, without user or notice data.
   */
  public function __construct(
    private readonly array|ContainerInterface $adapters,
    private readonly ?LoggerInterface $logger = NULL,
  ) {}

  /**
   * Adds each enabled adapter's authorized notices to the shared list.
   */
  #[Hook('aculta_messenger_personal_notices_alter')]
  public function noticesAlter(array &$items, AccountInterface $account, array $filters = []): void {
    $pageState = $filters['page_state'] ?? NULL;
    $selectedOrigin = $filters['origem'] ?? '';
    if (!is_string($selectedOrigin) || !in_array($selectedOrigin, NoticeListFilter::ORIGINS, TRUE)) {
      $selectedOrigin = '';
    }
    foreach (self::ADAPTERS as $id) {
      $adapterOrigin = self::ORIGINS[$id] ?? NULL;
      if (($pageState instanceof PersonalNoticePageState && $pageState->origin() !== $adapterOrigin)
        || ($selectedOrigin !== '' && $selectedOrigin !== $adapterOrigin)) {
        continue;
      }
      try {
        $adapter = $this->adapter($id);
      }
      catch (\Throwable $exception) {
        $this->logAdapterInitializationFailure($id, $exception, 'list');
        continue;
      }
      if ($adapter instanceof PersonalNoticeAdapterInterface) {
        // Keep partial output from a failing optional adapter out of the shared
        // list while allowing independent origins to continue contributing.
        $contribution = [];
        try {
          if ($pageState instanceof PersonalNoticePageState
            && $adapter instanceof PaginatedPersonalNoticeAdapterInterface) {
            $pageState->setHasNext($adapter->addPersonalNoticesPage(
              $contribution,
              $account,
              $filters,
              PersonalNoticePageState::PAGE_SIZE,
              $pageState->offset(),
            ));
          }
          else {
            $adapter->addPersonalNotices($contribution, $account, $filters);
          }
          if ($contribution !== []) {
            array_push($items, ...$contribution);
          }
        }
        catch (\Throwable $exception) {
          $this->logAdapterFailure($adapter, $exception, 'list');
        }
      }
    }
  }

  /**
   * Adds each enabled adapter's accessible unread notices to the shared count.
   */
  #[Hook('aculta_messenger_personal_notice_count_alter')]
  public function unreadCountAlter(int &$count, int $uid): void {
    foreach (self::ADAPTERS as $id) {
      try {
        $adapter = $this->adapter($id);
      }
      catch (\Throwable $exception) {
        $this->logAdapterInitializationFailure($id, $exception, 'count');
        continue;
      }
      if ($adapter instanceof PersonalNoticeAdapterInterface) {
        try {
          $adapterCount = $adapter->unreadCount($uid);
          if ($adapterCount < 0) {
            throw new \UnexpectedValueException('An adapter returned a negative unread notice count.');
          }
          $count += $adapterCount;
        }
        catch (\Throwable $exception) {
          $this->logAdapterFailure($adapter, $exception, 'count');
        }
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

  /**
   * Logs an optional adapter failure without recording user data or content.
   */
  private function logAdapterFailure(PersonalNoticeAdapterInterface $adapter, \Throwable $exception, string $operation): void {
    $this->logger?->error('Optional personal notice adapter failed during @operation with @exception.', [
      '@adapter' => get_class($adapter),
      '@operation' => $operation,
      '@exception' => get_class($exception),
    ]);
  }

  /**
   * Logs a lazy service construction failure without user data or content.
   */
  private function logAdapterInitializationFailure(string $adapterId, \Throwable $exception, string $operation): void {
    $this->logger?->error('Optional personal notice adapter @adapter could not initialize during @operation with @exception.', [
      '@adapter' => $adapterId,
      '@operation' => $operation,
      '@exception' => get_class($exception),
    ]);
  }

}
