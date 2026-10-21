<?php

declare(strict_types=1);

namespace Drupal\aculta_statistics\Hook;

use Drupal\aculta_statistics\Recorder\StatisticsRecorder;
use Drupal\commerce_payment\Entity\PaymentInterface;
use Drupal\commerce_price\Price;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;

/**
 * Conta reembolsos pelo incremento do valor reembolsado de cada pagamento.
 *
 * O Commerce guarda o total reembolsado acumulado. Cada atualização registra só a
 * diferença em relação à versão original, então salvar o mesmo reembolso duas vezes
 * não conta duas vezes. A receita líquida é calculada na leitura (bruta menos reembolsos).
 */
final class CommercePaymentRefundHooks {

  /**
   * Métrica de reembolsos, em centavos por moeda.
   */
  public const METRIC_REFUNDS = 'commerce.refunds.total';

  public function __construct(
    private readonly StatisticsRecorder $recorder,
    private readonly LoggerChannelFactoryInterface $loggerFactory,
  ) {}

  /**
   * Registra o incremento de reembolso do pagamento atualizado.
   */
  #[Hook('commerce_payment_update')]
  public function paymentUpdate(PaymentInterface $payment): void {
    $original = $payment->original ?? NULL;
    $before = $original instanceof PaymentInterface ? $original->getRefundedAmount() : NULL;
    $increment = self::refundIncrement($payment->getRefundedAmount(), $before);
    if ($increment === NULL) {
      return;
    }

    try {
      $this->recorder->record(self::METRIC_REFUNDS, $increment['currency'], $increment['cents']);
    }
    catch (\Throwable $exception) {
      $this->loggerFactory->get('aculta_statistics')->error('Falha ao registrar reembolso: @message', ['@message' => $exception->getMessage()]);
    }
  }

  /**
   * Incremento de reembolso entre duas versões do pagamento, em centavos.
   *
   * @return array{currency: string, cents: int}|null
   *   Moeda e centavos novos, ou NULL quando não há incremento positivo.
   */
  public static function refundIncrement(?Price $now, ?Price $before): ?array {
    if ($now === NULL) {
      return NULL;
    }
    if ($before !== NULL && $before->getCurrencyCode() !== $now->getCurrencyCode()) {
      return NULL;
    }
    $cents = (int) round((float) $now->getNumber() * 100) - (int) round((float) ($before?->getNumber() ?? '0') * 100);
    if ($cents <= 0) {
      return NULL;
    }
    return ['currency' => $now->getCurrencyCode(), 'cents' => $cents];
  }

}
