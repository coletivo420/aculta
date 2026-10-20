<?php

declare(strict_types=1);

namespace Drupal\aculta_mail_confirmer\Hook;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;

/**
 * Limpeza periódica dos pedidos de confirmação antigos.
 */
final class CronHooks {

  public function __construct(
    private readonly ConfigFactoryInterface $configFactory,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly TimeInterface $time,
    private readonly LoggerChannelFactoryInterface $loggerFactory,
  ) {}

  /**
   * Apaga os pedidos mais antigos que confirmation_lifetime.
   */
  #[Hook('cron')]
  public function cron(): void {
    $lifetime = $this->configFactory->get('aculta_mail_confirmer.settings')->get('confirmation_lifetime');
    if (empty($lifetime)) {
      return;
    }
    $storage = $this->entityTypeManager->getStorage('aculta_mail_confirmer');
    $old = $storage->getQuery()
      ->condition('created', $this->time->getRequestTime() - $lifetime, '<')
      ->accessCheck(FALSE)
      ->execute();
    if (!empty($old)) {
      $storage->delete($storage->loadMultiple($old));
      $this->loggerFactory->get('aculta_mail_confirmer')->info('Purged @count old email confirmations.', ['@count' => count($old)]);
    }
  }

}
