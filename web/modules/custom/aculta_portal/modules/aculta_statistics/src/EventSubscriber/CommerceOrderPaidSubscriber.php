<?php

declare(strict_types=1);

namespace Drupal\aculta_statistics\EventSubscriber;

use Drupal\aculta_statistics\Hook\CommerceOrderDomainHooks;
use Drupal\aculta_statistics\Recorder\StatisticsRecorder;
use Drupal\commerce_order\Event\OrderEvent;
use Drupal\commerce_order\Event\OrderEvents;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Conta pedidos pagos e a receita bruta por moeda.
 *
 * O Commerce dispara `commerce_order.order.paid` uma única vez por pedido, na transição
 * para pago (flag `paid_event_dispatched`). Por isso a contagem não soma mudanças de estado.
 *
 * O evento roda dentro do salvamento do pedido: um erro aqui não pode derrubar a venda.
 * Por isso falhas viram aviso no log, e o pedido segue normalmente.
 */
final class CommerceOrderPaidSubscriber implements EventSubscriberInterface {

  /**
   * Métrica de pedidos pagos.
   */
  public const METRIC_ORDERS = 'commerce.orders.paid';

  /**
   * Métrica de receita bruta, em centavos.
   */
  public const METRIC_REVENUE = 'commerce.revenue.gross';

  /**
   * Métrica de unidades vendidas, por variação de produto.
   */
  public const METRIC_ITEMS = 'commerce.items.sold';

  /**
   * Métrica de pedidos pagos por purpose de origem (dimensão `purpose|moeda`).
   */
  public const METRIC_ORDERS_BY_DOMAIN = 'commerce.orders.by_domain';

  /**
   * Métrica de receita bruta por purpose de origem (dimensão `purpose|moeda`), em centavos.
   */
  public const METRIC_REVENUE_BY_DOMAIN = 'commerce.revenue.by_domain';

  /**
   * Código ISO de moeda aceito como dimensão.
   */
  private const CURRENCY_PATTERN = '/^[A-Z]{3}$/';

  private readonly LoggerInterface $logger;

  public function __construct(
    private readonly StatisticsRecorder $recorder,
    LoggerChannelFactoryInterface $loggerFactory,
  ) {
    $this->logger = $loggerFactory->get('aculta_statistics');
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [OrderEvents::ORDER_PAID => 'onOrderPaid'];
  }

  /**
   * Dimensão `purpose|moeda` do pedido, ou `sem_dominio` quando o purpose não foi gravado.
   */
  private function domainCurrencyKey(object $order, string $currency): string {
    $purpose = (string) ($order->getData(CommerceOrderDomainHooks::DATA_KEY) ?? '');
    if (preg_match('/^[a-z0-9_-]{1,32}$/', $purpose) !== 1) {
      $purpose = 'sem_dominio';
    }
    return $purpose . '|' . $currency;
  }

  /**
   * Soma as unidades de cada item pago, por variação de produto.
   *
   * Item sem entidade comprada ou com quantidade não positiva é ignorado.
   */
  private function recordItems(object $order): void {
    foreach ($order->getItems() as $item) {
      $entityId = $item->getPurchasedEntityId();
      $quantity = (int) round((float) $item->getQuantity());
      if ($entityId === NULL || $entityId === '' || $quantity < 1) {
        continue;
      }
      $this->recorder->record(self::METRIC_ITEMS, (string) $entityId, $quantity);
    }
  }

  /**
   * Registra o pedido pago e a receita da moeda do pedido.
   */
  public function onOrderPaid(OrderEvent $event): void {
    $order = $event->getOrder();
    $price = $order->getTotalPrice();
    if ($price === NULL) {
      return;
    }

    $currency = $price->getCurrencyCode();
    if (preg_match(self::CURRENCY_PATTERN, $currency) !== 1) {
      $this->logger->warning('Pedido pago sem moeda ISO válida; não contado.', ['@order' => $order->id()]);
      return;
    }

    try {
      $this->recorder->record(self::METRIC_ORDERS, $currency);
      $cents = (int) round((float) $price->getNumber() * 100);
      if ($cents > 0) {
        $this->recorder->record(self::METRIC_REVENUE, $currency, $cents);
      }
      $this->recordItems($order);
      $key = $this->domainCurrencyKey($order, $currency);
      $this->recorder->record(self::METRIC_ORDERS_BY_DOMAIN, $key);
      if ($cents > 0) {
        $this->recorder->record(self::METRIC_REVENUE_BY_DOMAIN, $key, $cents);
      }
    }
    catch (\Throwable $exception) {
      $this->logger->error('Falha ao registrar pedido pago: @message', ['@message' => $exception->getMessage()]);
    }
  }

}
