<?php

declare(strict_types=1);

namespace Drupal\aculta_messenger\Hook;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\private_message\Entity\PrivateMessageThreadInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/** Applies the six-month inactivity retention policy to private conversations. */
final class PrivateMessageRetentionHooks {

  private const RETENTION_MONTHS = 6;

  private const MAX_THREADS_PER_CRON = 20;

  public function __construct(
    #[Autowire(service: 'entity_type.manager')]
    private readonly EntityTypeManagerInterface $entityTypeManager,
    #[Autowire(service: 'datetime.time')]
    private readonly TimeInterface $time,
    #[Autowire(service: 'logger.factory')]
    private readonly LoggerChannelFactoryInterface $loggerFactory,
  ) {}

  /** Deletes dormant threads through Private Message's entity lifecycle. */
  #[Hook('cron')]
  public function expireDormantThreads(): void {
    if (!$this->entityTypeManager->hasDefinition('private_message_thread')) {
      return;
    }

    $cutoff = (new \DateTimeImmutable('@' . $this->time->getRequestTime()))
      ->modify('-' . self::RETENTION_MONTHS . ' months')
      ->getTimestamp();
    $storage = $this->entityTypeManager->getStorage('private_message_thread');
    $ids = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('updated', $cutoff, '<=')
      ->sort('updated', 'ASC')
      ->range(0, self::MAX_THREADS_PER_CRON)
      ->execute();
    if ($ids === []) {
      return;
    }

    $deleted = 0;
    foreach ($storage->loadMultiple($ids) as $thread) {
      if (!$thread instanceof PrivateMessageThreadInterface || (int) $thread->getUpdatedTime() > $cutoff) {
        continue;
      }
      try {
        // The contrib entity's preDelete lifecycle deletes its referenced
        // private messages and participant history using its supported API.
        $thread->delete();
        $deleted++;
      }
      catch (\Throwable $exception) {
        $this->loggerFactory->get('aculta_messenger')->error('A private conversation could not be removed by the six-month retention task: @type.', [
          '@type' => $exception::class,
        ]);
      }
    }

    if ($deleted > 0) {
      $this->loggerFactory->get('aculta_messenger')->notice('Removed @count private conversations after six months without activity.', ['@count' => $deleted]);
    }
  }

}
