<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\MercadoPago;

use Drupal\aculta_portal\Sales\MercadoPagoPaymentPolicy;
use Drupal\commerce_payment\Entity\PaymentInterface;
use Drupal\commerce_price\Price;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use MercadoPago\Exceptions\MPApiException;

/**
 * Atualiza e cancela pagamentos do Checkout Pro pela API do Mercado Pago.
 *
 * O estorno não é feito aqui: ele é a operação refund do Commerce, que o gateway do módulo implementa
 * (refundPayment). Este serviço cobre o que o Commerce não oferece: consultar o status atual, cancelar
 * pagamento pendente e espelhar no pagamento local o que o Mercado Pago informa.
 */
final class MercadoPagoPaymentService {

  public function __construct(
    private readonly MercadoPagoClientFactory $clientes,
    private readonly MercadoPagoPaymentPolicy $politica,
    private readonly LoggerChannelInterface $logger,
  ) {}

  /**
   * Consulta o pagamento no Mercado Pago e espelha o status no pagamento local.
   *
   * @return string
   *   Status remoto consultado (approved, refunded, in_process...).
   *
   * @throws \Drupal\aculta_portal\MercadoPago\MercadoPagoApiException
   */
  public function atualizar(PaymentInterface $pagamento): string {
    $remoto = $this->consultar($pagamento);
    $status = (string) ($remoto->status ?? '');
    $valor = $pagamento->getAmount();
    $decisao = $this->politica->decidirSincronizacao(
      $status,
      $pagamento->getState()->getId(),
      $this->decimal($valor?->getNumber()),
      $this->decimal($remoto->transaction_amount_refunded ?? 0),
    );

    $pagamento->setRemoteState($status);
    if ($decisao['estornado'] !== NULL && $valor !== NULL) {
      $pagamento->setRefundedAmount(new Price($decisao['estornado'], $valor->getCurrencyCode()));
    }
    if ($decisao['estado'] !== NULL) {
      $pagamento->setState($decisao['estado']);
    }
    $pagamento->save();

    if ($decisao['contestacao']) {
      // Chargeback exige tratamento manual; o log registra só o identificador do pagamento.
      $this->logger->warning('Pagamento @id com contestação (chargeback) no Mercado Pago. Tratar manualmente.', ['@id' => $pagamento->getRemoteId()]);
    }
    $this->logger->info('Pagamento @id atualizado a partir do Mercado Pago: status remoto @status.', ['@id' => $pagamento->getRemoteId(), '@status' => $status]);
    return $status;
  }

  /**
   * Cancela um pagamento pendente no Mercado Pago.
   *
   * Confere o status remoto antes de cancelar, porque o pagamento local pode estar desatualizado.
   *
   * @throws \DomainException
   *   Pagamento que não pode ser cancelado no estado atual.
   * @throws \Drupal\aculta_portal\MercadoPago\MercadoPagoApiException
   */
  public function cancelar(PaymentInterface $pagamento): void {
    $remoto = $this->consultar($pagamento);
    $status = (string) ($remoto->status ?? '');
    $local = $pagamento->getState()->getId();
    if (!$this->politica->podeCancelar($status, $local)) {
      throw new \DomainException((string) new TranslatableMarkup('Este pagamento não pode ser cancelado no estado atual (@status).', ['@status' => $status]));
    }

    try {
      $cancelado = $this->clientes->criar('pagamentos', $pagamento->getPaymentGateway())->cancel((int) $pagamento->getRemoteId());
    }
    catch (MPApiException $e) {
      throw $this->falha('cancelar', $e);
    }

    $pagamento->setRemoteState((string) ($cancelado->status ?? 'cancelled'));
    // Pendente local em authorization: transição void do workflow payment_default.
    $pagamento->setState('authorization_voided');
    $pagamento->save();
    $this->logger->notice('Pagamento @id cancelado no Mercado Pago (status anterior @status).', ['@id' => $pagamento->getRemoteId(), '@status' => $status]);
  }

  /**
   * Busca o pagamento remoto, traduzindo falhas da API em exceção segura.
   */
  private function consultar(PaymentInterface $pagamento): object {
    if (trim((string) $pagamento->getRemoteId()) === '' || !ctype_digit((string) $pagamento->getRemoteId())) {
      throw new \DomainException((string) new TranslatableMarkup('Pagamento sem identificador válido no Mercado Pago.'));
    }
    try {
      return $this->clientes->criar('pagamentos', $pagamento->getPaymentGateway())->get((int) $pagamento->getRemoteId());
    }
    catch (MPApiException $e) {
      throw $this->falha('consultar', $e);
    }
  }

  /**
   * Registra o detalhe da falha no log de depuração e devolve uma exceção com mensagem segura.
   */
  private function falha(string $operacao, MPApiException $e): MercadoPagoApiException {
    $codigo = $e->getStatusCode();
    $this->logger->error('Falha ao @operacao pagamento no Mercado Pago (HTTP @codigo).', ['@operacao' => $operacao, '@codigo' => $codigo]);
    return new MercadoPagoApiException((string) new TranslatableMarkup('O Mercado Pago não respondeu como esperado. Tente de novo em instantes.'), $codigo, $e);
  }

  /**
   * Valor decimal com duas casas, sem aritmética em float (bcadd sobre a representação textual).
   */
  private function decimal(string|int|float|null $valor): string {
    return bcadd((string) ($valor ?? '0'), '0', 2);
  }

}
