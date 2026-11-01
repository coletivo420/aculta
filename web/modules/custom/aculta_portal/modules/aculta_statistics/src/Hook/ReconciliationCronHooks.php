<?php

declare(strict_types=1);

namespace Drupal\aculta_statistics\Hook;

use Drupal\aculta_statistics\Reconciliation\CommerceReconciler;
use Drupal\aculta_statistics\Storage\AggregateStorageInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;

/**
 * Reconcilia o dia anterior com os pedidos oficiais do Commerce, no cron.
 *
 * Diferença é registrada no log do canal `aculta_statistics` (nível warning). Não altera
 * contadores nem dados do Commerce. Sem o módulo Commerce ativo, não faz nada.
 */
final class ReconciliationCronHooks {

  public function __construct(
    private readonly CommerceReconciler $reconciler,
    private readonly AggregateStorageInterface $storage,
    private readonly ModuleHandlerInterface $moduleHandler,
    private readonly LoggerChannelFactoryInterface $loggerFactory,
  ) {}

  /**
   * Compara o dia UTC anterior e registra diferenças.
   */
  #[Hook('cron')]
  public function cron(): void {
    if (!$this->moduleHandler->moduleExists('commerce_order')) {
      return;
    }

    $day = $this->storage->currentDay() - 1;
    $differences = $this->reconciler->reconcileDay($day);
    if ($differences === []) {
      return;
    }

    $this->loggerFactory->get('aculta_statistics')->warning(
      'Divergência de pedidos pagos no dia @day (contado menos oficial): @details',
      ['@day' => gmdate('Y-m-d', $day * 86400), '@details' => json_encode($differences, JSON_UNESCAPED_UNICODE)],
    );
  }

}
