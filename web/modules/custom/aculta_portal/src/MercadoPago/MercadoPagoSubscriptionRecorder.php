<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\MercadoPago;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_payment\Entity\PaymentGatewayInterface;
use Drupal\commerce_price\Price;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Grava no pedido de assinatura o que o Mercado Pago informa, e aplica as ações do doador (pausar, reativar, cancelar).
 *
 * O pedido de assinatura (tipo `assinatura`, workflow order_recurring) agrupa as cobranças. A ligação com o Mercado Pago
 * é o id da assinatura (preapproval), guardado em `data` do pedido, e o `external_reference` da assinatura, que é o id
 * do pedido. Regras:
 * - cobrança só vira pagamento quando o pagamento vinculado está `approved`. O status `processed` da cobrança não basta:
 *   ele também marca parcela que esgotou as tentativas sem aprovação;
 * - cada pagamento aprovado é gravado uma vez (deduplicado pelo id do pagamento no Mercado Pago), porque o webhook
 *   pode ser reenviado;
 * - a primeira cobrança aprovada leva o pedido a `completed` (transição mark_paid); o cancelamento leva a `canceled`;
 * - doações não são estornadas: não há tratamento de estorno aqui.
 *
 * Não grava método de pagamento (o Mercado Pago cobra o cartão da autorização) e não registra e-mail nem dados do pagador.
 *
 * Status da assinatura (preapproval) no Mercado Pago: `pending`, `authorized`, `paused`, `cancelled`. Os status de
 * alteração (`paused`, `authorized`, `cancelled`) vêm do plano de integração (docs/portal/MERCADO-PAGO-PLANO.md); a
 * referência da API não pôde ser lida na sessão, então a confirmação com o sandbox fica pendente.
 */
final class MercadoPagoSubscriptionRecorder {

  /** Chave em `data` do pedido com o id da assinatura no Mercado Pago. */
  public const CHAVE_ASSINATURA = 'mp_preapproval_id';

  /** Chave em `data` do pedido com o último status da assinatura informado pelo Mercado Pago. */
  public const CHAVE_STATUS = 'mp_status';

  /** Tipo de pedido de assinatura de doação. */
  public const TIPO_PEDIDO = 'assinatura';

  /** Status de assinatura (preapproval) conhecidos. */
  public const STATUS_PENDENTE = 'pending';
  public const STATUS_ATIVA = 'authorized';
  public const STATUS_PAUSADA = 'paused';
  public const STATUS_CANCELADA = 'cancelled';

  /** Ordem de exibição dos status no painel. */
  public const STATUS_ORDEM = [self::STATUS_PENDENTE, self::STATUS_ATIVA, self::STATUS_PAUSADA, self::STATUS_CANCELADA];

  /**
   * Ações do doador: status de origem permitidos e status de destino no Mercado Pago.
   */
  public const ACOES = [
    'pausar' => ['de' => [self::STATUS_ATIVA], 'para' => self::STATUS_PAUSADA],
    'reativar' => ['de' => [self::STATUS_PAUSADA], 'para' => self::STATUS_ATIVA],
    'cancelar' => ['de' => [self::STATUS_PENDENTE, self::STATUS_ATIVA, self::STATUS_PAUSADA], 'para' => self::STATUS_CANCELADA],
  ];

  /** Status de cobrança (authorized_payments) conhecidos; os demais são registrados como desconhecidos. */
  public const STATUS_COBRANCA = ['processed', 'recycling', 'waiting for gateway', 'scheduled'];

  /** Chave em `data` do pedido com a situação da última cobrança (ver situacaoCobranca). */
  public const CHAVE_COBRANCA = 'mp_cobranca_situacao';

  public const COBRANCA_PAGA = 'paga';
  public const COBRANCA_RECUSADA = 'recusada';
  public const COBRANCA_PROCESSANDO = 'processando';
  public const COBRANCA_AGENDADA = 'agendada';
  public const COBRANCA_NAO_COBRADA = 'nao_cobrada';
  public const COBRANCA_CANCELADA = 'cancelada';
  public const COBRANCA_DESCONHECIDA = 'desconhecida';

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly MercadoPagoRecurrenceService $recorrencia,
    private readonly LoggerChannelInterface $logger,
    private readonly TimeInterface $time,
  ) {}

  /**
   * Rótulo da situação da assinatura para o doador e para o painel.
   */
  public static function rotulo(string $status): TranslatableMarkup {
    return match ($status) {
      self::STATUS_PENDENTE => new TranslatableMarkup('Aguardando autorização'),
      self::STATUS_ATIVA => new TranslatableMarkup('Ativa'),
      self::STATUS_PAUSADA => new TranslatableMarkup('Pausada'),
      self::STATUS_CANCELADA => new TranslatableMarkup('Cancelada'),
      default => new TranslatableMarkup('Situação não informada'),
    };
  }

  /**
   * Status atual da assinatura guardado no pedido (vazio se não há assinatura).
   */
  public function statusDoPedido(OrderInterface $pedido): string {
    return (string) $pedido->getData(self::CHAVE_STATUS, '');
  }

  /**
   * Indica se a ação pode ser aplicada agora: pedido de assinatura com id no Mercado Pago e status de origem permitido.
   */
  public function podeAlterar(OrderInterface $pedido, string $acao): bool {
    if (!isset(self::ACOES[$acao]) || $pedido->bundle() !== self::TIPO_PEDIDO || !$pedido->getData(self::CHAVE_ASSINATURA)) {
      return FALSE;
    }
    return in_array($this->statusDoPedido($pedido), self::ACOES[$acao]['de'], TRUE);
  }

  /**
   * Aplica a ação no Mercado Pago e atualiza o pedido.
   *
   * @throws \InvalidArgumentException
   *   Ação ou situação não permitida.
   * @throws \Drupal\aculta_portal\MercadoPago\MercadoPagoApiException
   *   Falha do Mercado Pago (nada é gravado no pedido).
   */
  public function alterar(PaymentGatewayInterface $gateway, OrderInterface $pedido, string $acao): void {
    if (!$this->podeAlterar($pedido, $acao)) {
      throw new \InvalidArgumentException('Ação não permitida para esta assinatura.');
    }
    $destino = self::ACOES[$acao]['para'];
    $resposta = $this->recorrencia->alterarStatusAssinatura($gateway, (string) $pedido->getData(self::CHAVE_ASSINATURA), $destino);
    $pedido->setData(self::CHAVE_STATUS, (string) ($resposta->status ?? $destino));
    if ($destino === self::STATUS_CANCELADA) {
      $this->transicionar($pedido, 'cancel');
    }
    $pedido->save();
    $this->logger->notice('Assinatura de doação do pedido @pedido: ação @acao.', ['@pedido' => $pedido->id(), '@acao' => $acao]);
  }

  /**
   * Atualiza o pedido com o status da assinatura (tópico subscription_preapproval).
   *
   * @param object $assinatura
   *   Resposta da assinatura (status, external_reference).
   */
  public function assinatura(object $assinatura): void {
    $pedido = $this->pedido((string) ($assinatura->external_reference ?? ''));
    if ($pedido === NULL) {
      $this->logger->warning('Assinatura @id sem pedido de doação correspondente.', ['@id' => (string) ($assinatura->id ?? '')]);
      return;
    }
    $status = (string) ($assinatura->status ?? '');
    if (!in_array($status, [self::STATUS_PENDENTE, self::STATUS_ATIVA, self::STATUS_PAUSADA, self::STATUS_CANCELADA], TRUE)) {
      $this->logger->warning('Assinatura @id com status desconhecido: @status.', ['@id' => (string) ($assinatura->id ?? ''), '@status' => $status]);
    }
    $pedido->setData(self::CHAVE_STATUS, $status);
    if ($status === self::STATUS_CANCELADA) {
      $this->transicionar($pedido, 'cancel');
    }
    $pedido->save();
  }

  /**
   * Situação da última cobrança, a partir do status da cobrança (authorized_payments) e de o pagamento estar aprovado.
   */
  public static function situacaoCobranca(string $status, bool $aprovado): string {
    if ($aprovado) {
      return self::COBRANCA_PAGA;
    }
    return match ($status) {
      'processed' => self::COBRANCA_NAO_COBRADA,
      'recycling' => self::COBRANCA_RECUSADA,
      'waiting for gateway' => self::COBRANCA_PROCESSANDO,
      'scheduled' => self::COBRANCA_AGENDADA,
      'cancelled' => self::COBRANCA_CANCELADA,
      default => self::COBRANCA_DESCONHECIDA,
    };
  }

  /**
   * Rótulo da situação da última cobrança para o doador e para o painel.
   */
  public static function rotuloCobranca(string $situacao): TranslatableMarkup {
    return match ($situacao) {
      self::COBRANCA_PAGA => new TranslatableMarkup('Paga'),
      self::COBRANCA_RECUSADA => new TranslatableMarkup('Recusada, nova tentativa'),
      self::COBRANCA_PROCESSANDO => new TranslatableMarkup('Em processamento'),
      self::COBRANCA_AGENDADA => new TranslatableMarkup('Agendada'),
      self::COBRANCA_NAO_COBRADA => new TranslatableMarkup('Não cobrada (tentativas esgotadas)'),
      self::COBRANCA_CANCELADA => new TranslatableMarkup('Cancelada no Mercado Pago'),
      default => new TranslatableMarkup('Situação não informada'),
    };
  }

  /**
   * Situação da última cobrança guardada no pedido (vazia se ainda não há cobrança).
   */
  public function situacaoDaUltimaCobranca(OrderInterface $pedido): string {
    return (string) $pedido->getData(self::CHAVE_COBRANCA, '');
  }

  /**
   * Grava a cobrança (tópico subscription_authorized_payment): a situação fica no pedido; só cobrança com pagamento
   * aprovado vira pagamento, uma vez.
   *
   * @param array<string, mixed> $cobranca
   *   Resposta de GET /authorized_payments/{id}.
   */
  public function cobranca(PaymentGatewayInterface $gateway, array $cobranca): void {
    $status = (string) ($cobranca['status'] ?? '');
    $pagamento = is_array($cobranca['payment'] ?? NULL) ? $cobranca['payment'] : [];
    $remoto = (string) ($pagamento['id'] ?? '');
    $aprovado = ($pagamento['status'] ?? '') === 'approved' && $remoto !== '';
    if (!$aprovado && !in_array($status, self::STATUS_COBRANCA, TRUE)) {
      $this->logger->warning('Cobrança @id com status desconhecido: @status.', ['@id' => (string) ($cobranca['id'] ?? ''), '@status' => $status]);
    }
    $assinatura = $this->recorrencia->obterAssinatura($gateway, (string) ($cobranca['preapproval_id'] ?? ''));
    $pedido = $this->pedido((string) ($assinatura->external_reference ?? ''));
    if ($pedido === NULL) {
      $this->logger->warning('Cobrança @id sem pedido de doação correspondente.', ['@id' => (string) ($cobranca['id'] ?? '')]);
      return;
    }
    $pedido->setData(self::CHAVE_COBRANCA, self::situacaoCobranca($status, $aprovado));
    if (!$aprovado) {
      $pedido->save();
      $this->logger->notice('Cobrança @id do pedido @pedido: status @status, sem pagamento aprovado.', [
        '@id' => (string) ($cobranca['id'] ?? ''),
        '@pedido' => $pedido->id(),
        '@status' => $status,
      ]);
      return;
    }
    if (!$this->pagamentoExiste($pedido, $remoto)) {
      $pagamento = $this->entidades('commerce_payment')->create([
        'state' => 'completed',
        'amount' => new Price((string) ($cobranca['transaction_amount'] ?? '0'), $pedido->getTotalPrice()->getCurrencyCode()),
        'payment_gateway' => $gateway->id(),
        'order_id' => $pedido->id(),
        'remote_id' => $remoto,
        'remote_state' => 'approved',
        'authorized' => $this->time->getRequestTime(),
      ]);
      $pagamento->save();
      if ($pedido->getState()->getId() === 'needs_payment') {
        $this->transicionar($pedido, 'mark_paid');
      }
    }
    $pedido->save();
  }

  /**
   * Pedido de doação (tipo assinatura) pelo id, ou NULL.
   */
  private function pedido(string $id): ?OrderInterface {
    if ($id === '' || !ctype_digit($id)) {
      return NULL;
    }
    $pedido = $this->entidades('commerce_order')->load((int) $id);
    return $pedido instanceof OrderInterface && $pedido->bundle() === self::TIPO_PEDIDO ? $pedido : NULL;
  }

  /**
   * Aplica a transição se o workflow a permitir a partir do estado atual.
   */
  private function transicionar(OrderInterface $pedido, string $transicao): void {
    $estado = $pedido->getState();
    if (isset($estado->getTransitions()[$transicao])) {
      $estado->applyTransitionById($transicao);
    }
  }

  private function pagamentoExiste(OrderInterface $pedido, string $remoto): bool {
    return $this->entidades('commerce_payment')->loadByProperties([
      'order_id' => $pedido->id(),
      'remote_id' => $remoto,
    ]) !== [];
  }

  private function entidades(string $tipo): \Drupal\Core\Entity\EntityStorageInterface {
    return $this->entityTypeManager->getStorage($tipo);
  }

}
