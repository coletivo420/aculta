<?php

declare(strict_types=1);

namespace Drupal\aculta_statistics\Hook;

use Drupal\aculta_statistics\Recorder\StatisticsRecorder;
use Drupal\aculta_statistics\Reconciliation\AbandonedCartWindow;
use Drupal\aculta_statistics\Storage\AggregateStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Hook\Attribute\Hook;

/**
 * Conta carrinhos abandonados (com itens, sem finalizar, sem alteração há 24 horas).
 *
 * Só carrinhos (`cart`) em estado `draft` com itens. Carrinhos vazios não contam.
 * Não identifica o cliente: a métrica é apenas o total do dia.
 */
final class CartAbandonmentCronHooks {

  /**
   * Métrica de carrinhos abandonados.
   */
  public const METRIC = 'commerce.carts.abandoned';

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly StatisticsRecorder $recorder,
    private readonly AggregateStorageInterface $storage,
    private readonly ModuleHandlerInterface $moduleHandler,
  ) {}

  /**
   * Conta os carrinhos abandonados da janela do dia atual.
   */
  #[Hook('cron')]
  public function cron(): void {
    if (!$this->moduleHandler->moduleExists('commerce_cart')) {
      return;
    }

    [$from, $to] = AbandonedCartWindow::bounds($this->storage->currentDay());
    $ids = $this->entityTypeManager->getStorage('commerce_order')->getQuery()
      ->accessCheck(FALSE)
      ->condition('cart', TRUE)
      ->condition('state', 'draft')
      ->condition('changed', [$from, $to], 'BETWEEN')
      ->execute();

    foreach ($this->entityTypeManager->getStorage('commerce_order')->loadMultiple($ids) as $cart) {
      if ($cart->hasItems()) {
        $this->recorder->record(self::METRIC);
      }
    }
  }

}
