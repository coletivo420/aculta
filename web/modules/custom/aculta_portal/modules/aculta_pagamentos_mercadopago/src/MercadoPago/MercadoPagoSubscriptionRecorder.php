<?php

declare(strict_types=1);

namespace Drupal\aculta_pagamentos_mercadopago\MercadoPago;

use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\aculta_portal\Contract\SubscriptionOperationsInterface;
use Drupal\aculta_portal\Contract\SubscriptionStatusInterface;
use Drupal\aculta_portal\Exception\PaymentOperationException;
use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_payment\Entity\PaymentGatewayInterface;
use Drupal\commerce_price\Price;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Persists provider status and donor actions for recurring donation orders.
 *
 * Subscription orders group recurring charges. The provider subscription ID is
 * stored in order data, and its external reference points to the order ID.
 *
 * Only an approved linked payment is recorded. The `processed` invoice state
 * alone is insufficient because retries can be exhausted without approval.
 * Payment IDs are deduplicated and serialized. Donations are not refundable.
 *
 * The provider charges the authorized card. Card and payer data are not stored.
 * Subscription states are `pending`, `authorized`, `paused`, and `canceled`.
 */
final class MercadoPagoSubscriptionRecorder implements SubscriptionStatusInterface, SubscriptionOperationsInterface {

  /**
   * Chave em `data` do pedido com o id da assinatura no Mercado Pago. */
  public const CHAVE_ASSINATURA = 'mp_preapproval_id';

  /**
   * Chave em `data` do pedido com o último status da assinatura informado pelo Mercado Pago. */
  public const CHAVE_STATUS = 'mp_status';

  /**
   * Versão monotônica da assinatura remota já refletida no pedido.
   */
  public const CHAVE_VERSAO = 'mp_subscription_version';

  /**
   * Marker retained when remote creation may have succeeded without a response.
   */
  public const CHAVE_CRIACAO_PENDENTE = 'mp_subscription_creation_pending';

  /**
   * Tipo de pedido de assinatura de doação. */
  public const TIPO_PEDIDO = 'assinatura';

  /**
   * Status de assinatura (preapproval) conhecidos. */
  public const STATUS_PENDENTE = 'pending';
  public const STATUS_ATIVA = 'authorized';
  public const STATUS_PAUSADA = 'paused';
  public const STATUS_CANCELADA = 'canceled';

  private const LOCK_PREFIX = 'aculta_pagamentos_mercadopago.subscription.';

  /**
   * Ordem de exibição dos status no painel. */
  public const STATUS_ORDEM = [
    self::STATUS_PENDENTE,
    self::STATUS_ATIVA,
    self::STATUS_PAUSADA,
    self::STATUS_CANCELADA,
  ];

  /**
   * Donor actions and their permitted provider status transitions.
   */
  public const ACOES = [
    'pausar' => ['de' => [self::STATUS_ATIVA], 'para' => self::STATUS_PAUSADA],
    'reativar' => ['de' => [self::STATUS_PAUSADA], 'para' => self::STATUS_ATIVA],
    'cancelar' => [
      'de' => [self::STATUS_PENDENTE, self::STATUS_ATIVA, self::STATUS_PAUSADA],
      'para' => self::STATUS_CANCELADA,
    ],
  ];

  /**
   * Status de cobrança (authorized_payments) conhecidos; os demais são registrados como desconhecidos. */
  public const STATUS_COBRANCA = ['processed', 'recycling', 'waiting for gateway', 'scheduled'];

  /**
   * Chave em `data` do pedido com a situação da última cobrança (ver situacaoCobranca). */
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
    private readonly LockBackendInterface $lock,
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
   * {@inheritdoc}
   */
  public function subscriptionOrderType(): string {
    return self::TIPO_PEDIDO;
  }

  /**
   * {@inheritdoc}
   */
  public function pendingStatus(): string {
    return self::STATUS_PENDENTE;
  }

  /**
   * {@inheritdoc}
   */
  public function orderedStatuses(): array {
    return self::STATUS_ORDEM;
  }

  /**
   * {@inheritdoc}
   */
  public function statusForOrder(OrderInterface $order): string {
    return $this->statusDoPedido($order);
  }

  /**
   * {@inheritdoc}
   */
  public function latestChargeStatus(OrderInterface $order): string {
    return $this->situacaoDaUltimaCobranca($order);
  }

  /**
   * {@inheritdoc}
   */
  public function statusLabel(string $status): TranslatableMarkup {
    return self::rotulo($status);
  }

  /**
   * {@inheritdoc}
   */
  public function chargeStatusLabel(string $status): TranslatableMarkup {
    return self::rotuloCobranca($status);
  }

  /**
   * Creates or recovers the single Mercado Pago subscription for an order.
   *
   * The per-order lock closes concurrent checkout submissions. The order is
   * reloaded while holding the lock so a request that waited for another
   * submission can resume its existing subscription instead of creating a
   * second one.
   *
   * @param \Drupal\commerce_payment\Entity\PaymentGatewayInterface $gateway
   *   The active Commerce Mercado Pago gateway.
   * @param \Drupal\commerce_order\Entity\OrderInterface $pedido
   *   The recurring donation order to associate with the subscription.
   * @param array<string, mixed> $dados
   *   Validated subscription fields. The order ID always supplies the external
   *   reference, and the order email always supplies the payer email.
   *
   * @return array{id: string, status: string, init_point: string}
   *   The linked subscription and its authorization URL.
   */
  public function criarAssinatura(PaymentGatewayInterface $gateway, OrderInterface $pedido, array $dados): array {
    if ($pedido->bundle() !== self::TIPO_PEDIDO || !$pedido->id()) {
      throw new \InvalidArgumentException('Pedido inválido para assinatura recorrente.');
    }
    $pedido_id = (string) $pedido->id();
    $lock_id = self::LOCK_PREFIX . 'create-order.' . $pedido_id;
    if (!$this->lock->acquire($lock_id, 30)) {
      throw new MercadoPagoApiException((string) new TranslatableMarkup('A autorização da doação já está sendo iniciada. Tente novamente.'), 503);
    }

    try {
      $pedido_atual = $this->entidades('commerce_order')->load($pedido_id);
      if (!$pedido_atual instanceof OrderInterface || $pedido_atual->bundle() !== self::TIPO_PEDIDO) {
        throw new \InvalidArgumentException('Pedido de assinatura não encontrado.');
      }

      $assinatura_id = (string) $pedido_atual->getData(self::CHAVE_ASSINATURA, '');
      if ($assinatura_id !== '') {
        $assinatura = $this->recorrencia->obterAssinatura($gateway, $assinatura_id);
        $this->validarAssinaturaRemota($assinatura, $assinatura_id, $pedido_id);
        $status = $this->providerString($assinatura->status ?? NULL);
        $init_point = $this->providerString($assinatura->init_point ?? NULL);
        if ($status === self::STATUS_ATIVA) {
          return ['id' => $assinatura_id, 'status' => $status, 'init_point' => ''];
        }
        if ($status !== self::STATUS_PENDENTE || $init_point === '') {
          throw new MercadoPagoApiException((string) new TranslatableMarkup('A assinatura existente não pode ser autorizada neste checkout. Atualize a página.'), 409);
        }
        return ['id' => $assinatura_id, 'status' => $status, 'init_point' => $init_point];
      }

      if ($pedido_atual->getData(self::CHAVE_CRIACAO_PENDENTE)) {
        throw new MercadoPagoApiException((string) new TranslatableMarkup('A tentativa anterior precisa ser conciliada antes de iniciar outra autorização. Entre em contato com o suporte.'), 409);
      }

      $dados['email'] = (string) $pedido_atual->getEmail();
      $dados['referencia'] = $pedido_id;
      $pedido_atual->setData(self::CHAVE_CRIACAO_PENDENTE, $this->time->getRequestTime());
      $pedido_atual->save();
      try {
        $assinatura = $this->recorrencia->criarAssinatura($gateway, $dados);
      }
      catch (\InvalidArgumentException $exception) {
        // The request builder rejected the payload before any remote call.
        $pedido_atual->setData(self::CHAVE_CRIACAO_PENDENTE, NULL);
        $pedido_atual->save();
        throw $exception;
      }
      catch (MercadoPagoApiException $exception) {
        if (!$this->falhaRemotaAmbigua($exception)) {
          $pedido_atual->setData(self::CHAVE_CRIACAO_PENDENTE, NULL);
          $pedido_atual->save();
        }
        throw $exception;
      }
      $assinatura_id = trim((string) ($assinatura['id'] ?? ''));
      $init_point = trim((string) ($assinatura['init_point'] ?? ''));
      $status = trim((string) ($assinatura['status'] ?? self::STATUS_PENDENTE));
      if ($assinatura_id === ''
        || ($status === self::STATUS_PENDENTE && $init_point === '')
        || !in_array($status, [self::STATUS_PENDENTE, self::STATUS_ATIVA], TRUE)) {
        throw new MercadoPagoApiException((string) new TranslatableMarkup('O Mercado Pago não devolveu uma assinatura válida para o pedido.'), 502);
      }

      $pedido_atual->setData(self::CHAVE_ASSINATURA, $assinatura_id);
      $pedido_atual->setData(self::CHAVE_STATUS, $status);
      $pedido_atual->setData(self::CHAVE_CRIACAO_PENDENTE, NULL);
      $estado = $pedido_atual->getState();
      if (isset($estado->getTransitions()['place'])) {
        $estado->applyTransitionById('place');
      }
      $pedido_atual->save();

      return ['id' => $assinatura_id, 'status' => $status, 'init_point' => $init_point];
    }
    finally {
      $this->lock->release($lock_id);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function availableActions(): array {
    return [
      'pausar' => new TranslatableMarkup('Pausar'),
      'reativar' => new TranslatableMarkup('Reativar'),
      'cancelar' => new TranslatableMarkup('Cancelar assinatura'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function canChange(OrderInterface $order, string $action): bool {
    return $this->podeAlterar($order, $action);
  }

  /**
   * {@inheritdoc}
   */
  public function change(PaymentGatewayInterface $gateway, OrderInterface $order, string $action): void {
    try {
      $this->alterar($gateway, $order, $action);
    }
    catch (MercadoPagoApiException $exception) {
      $this->logger->error('Falha de API ao alterar assinatura do pedido @order (HTTP @status).', [
        '@order' => $order->id(),
        '@status' => $exception->codigoHttp() ?: 'indisponível',
      ]);
      throw new PaymentOperationException($exception->getMessage());
    }
  }

  /**
   * Status atual da assinatura guardado no pedido (vazio se não há assinatura).
   */
  public function statusDoPedido(OrderInterface $pedido): string {
    $status = (string) $pedido->getData(self::CHAVE_STATUS, '');
    // Normalize the former local spelling without rewriting existing orders.
    return $status === 'cancelled' ? self::STATUS_CANCELADA : $status;
  }

  /**
   * Checks for a subscription order and an allowed source status.
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
   * @throws \Drupal\aculta_pagamentos_mercadopago\MercadoPago\MercadoPagoApiException
   *   Falha do Mercado Pago (nada é gravado no pedido).
   */
  public function alterar(PaymentGatewayInterface $gateway, OrderInterface $pedido, string $acao): void {
    if (!$this->podeAlterar($pedido, $acao)) {
      throw new \InvalidArgumentException('Ação não permitida para esta assinatura.');
    }
    $assinatura_id = (string) $pedido->getData(self::CHAVE_ASSINATURA);
    $lock_id = self::LOCK_PREFIX . $assinatura_id;
    if (!$this->lock->acquire($lock_id, 30)) {
      throw new MercadoPagoApiException((string) new TranslatableMarkup('A assinatura está sendo atualizada. Tente novamente.'), 503);
    }
    try {
      // Reload after taking the lock. Another request may have changed the
      // order while this action was waiting.
      $pedido_atual = $this->entidades('commerce_order')->load($pedido->id());
      if (!$pedido_atual instanceof OrderInterface || !$this->podeAlterar($pedido_atual, $acao)
        || (string) $pedido_atual->getData(self::CHAVE_ASSINATURA) !== $assinatura_id) {
        throw new \InvalidArgumentException('A situação da assinatura mudou. Atualize a página e tente novamente.');
      }

      $regra = self::ACOES[$acao];
      $destino = $regra['para'];
      $remota = $this->recorrencia->obterAssinatura($gateway, $assinatura_id);
      $this->validarAssinaturaRemota($remota, $assinatura_id, (string) $pedido_atual->id());
      $versao_remota = $this->versaoRemota($remota);
      $versao_local = $this->versaoLocal($pedido_atual);
      if ($versao_remota < $versao_local) {
        throw new MercadoPagoApiException((string) new TranslatableMarkup('O Mercado Pago retornou uma versão antiga da assinatura.'), 502);
      }
      $status_remoto = $this->providerString($remota->status ?? NULL);
      if ($status_remoto === $destino) {
        // A previous request may have succeeded remotely but timed out before
        // the local order was saved. Reconcile without issuing a second PUT.
        $this->salvarStatusAssinatura($pedido_atual, $destino, $versao_remota);
        return;
      }
      $status_conhecidos = [
        self::STATUS_PENDENTE,
        self::STATUS_ATIVA,
        self::STATUS_PAUSADA,
        self::STATUS_CANCELADA,
      ];
      if (!in_array($status_remoto, $status_conhecidos, TRUE)) {
        throw new MercadoPagoApiException((string) new TranslatableMarkup('O Mercado Pago retornou uma situação de assinatura inconsistente.'), 502);
      }
      if (!in_array($status_remoto, $regra['de'], TRUE)) {
        $this->salvarStatusAssinatura($pedido_atual, $status_remoto, $versao_remota);
        throw new \InvalidArgumentException('A situação remota da assinatura mudou. Atualize a página e tente novamente.');
      }

      $resposta = $this->recorrencia->alterarStatusAssinatura($gateway, $assinatura_id, $destino);
      $this->validarAssinaturaRemota($resposta, $assinatura_id, (string) $pedido_atual->id());
      if ($this->providerString($resposta->status ?? NULL) !== $destino) {
        throw new MercadoPagoApiException((string) new TranslatableMarkup('O Mercado Pago não confirmou a alteração da assinatura.'), 502);
      }
      $versao_resposta = $this->versaoRemota($resposta);
      if ($versao_resposta <= $versao_remota) {
        throw new MercadoPagoApiException((string) new TranslatableMarkup('O Mercado Pago não avançou a versão da assinatura alterada.'), 502);
      }

      $this->salvarStatusAssinatura($pedido_atual, $destino, $versao_resposta);
      $this->logger->notice('Assinatura de doação do pedido @pedido: ação @acao.', [
        '@pedido' => $pedido_atual->id(),
        '@acao' => $acao,
      ]);
    }
    finally {
      $this->lock->release($lock_id);
    }
  }

  /**
   * Updates an order from a subscription_preapproval notification.
   *
   * @param object $assinatura
   *   Resposta da assinatura (status, external_reference).
   */
  public function assinatura(object $assinatura): void {
    $remote_id = $this->providerString($assinatura->id ?? NULL);
    $pedido_id = $this->providerString($assinatura->external_reference ?? NULL);
    if ($remote_id === '' || $pedido_id === '') {
      $this->logger->error('Notificação de assinatura sem ID ou referência de pedido válidos.');
      return;
    }
    $lock_id = self::LOCK_PREFIX . $remote_id;
    if (!$this->lock->acquire($lock_id, 30)) {
      throw new MercadoPagoApiException((string) new TranslatableMarkup('A assinatura está sendo processada. Tente novamente.'), 503);
    }
    try {
      // A ordem é carregada novamente depois do mesmo lock usado pelas ações.
      $pedido = $this->pedido($pedido_id);
      if ($pedido === NULL) {
        $this->logger->warning('Assinatura @id sem pedido de doação correspondente.', ['@id' => $remote_id]);
        return;
      }
      if ($remote_id !== (string) $pedido->getData(self::CHAVE_ASSINATURA, '')) {
        $this->logger->error('Assinatura @id não corresponde à assinatura registrada no pedido @pedido.', [
          '@id' => $remote_id,
          '@pedido' => $pedido->id(),
        ]);
        return;
      }

      $versao = $this->versaoRemota($assinatura);
      $versao_local = $this->versaoLocal($pedido);
      $status = $this->providerString($assinatura->status ?? NULL);
      if ($versao < $versao_local) {
        $this->logger->notice('Evento antigo de assinatura @id ignorado (versão @versao).', [
          '@id' => $remote_id,
          '@versao' => $versao,
        ]);
        return;
      }
      if ($versao === $versao_local) {
        if ($this->statusDoPedido($pedido) !== $status) {
          throw new MercadoPagoApiException((string) new TranslatableMarkup('O Mercado Pago retornou estados conflitantes para a mesma versão de assinatura.'), 502);
        }
        // Materializa o marcador em pedidos que já tinham o mesmo status antes
        // da nova chave ser introduzida.
        if ($pedido->getData(self::CHAVE_VERSAO) === NULL) {
          $pedido->setData(self::CHAVE_VERSAO, $versao);
          $pedido->save();
        }
        return;
      }
      if (!in_array($status, [self::STATUS_PENDENTE, self::STATUS_ATIVA, self::STATUS_PAUSADA, self::STATUS_CANCELADA], TRUE)) {
        $this->logger->warning('Assinatura @id com status desconhecido: @status.', [
          '@id' => $remote_id,
          '@status' => $status,
        ]);
      }
      $this->salvarStatusAssinatura($pedido, $status, $versao);
    }
    finally {
      $this->lock->release($lock_id);
    }
  }

  /**
   * Maps invoice status and payment approval to a local charge state.
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
   * Returns the last charge state stored on the order.
   */
  public function situacaoDaUltimaCobranca(OrderInterface $pedido): string {
    return (string) $pedido->getData(self::CHAVE_COBRANCA, '');
  }

  /**
   * Grava uma cobrança autorizada no pedido.
   *
   * Only an approved linked payment creates a Commerce payment.
   *
   * @param \Drupal\commerce_payment\Entity\PaymentGatewayInterface $gateway
   *   Gateway com credenciais do ambiente ativo.
   * @param array<string, mixed> $cobranca
   *   Resposta de GET /authorized_payments/{id}.
   */
  public function cobranca(PaymentGatewayInterface $gateway, array $cobranca): void {
    $status = $this->providerString($cobranca['status'] ?? NULL);
    $pagamento = is_array($cobranca['payment'] ?? NULL) ? $cobranca['payment'] : [];
    $remoto = $this->providerString($pagamento['id'] ?? NULL);
    $aprovado = $this->providerString($pagamento['status'] ?? NULL) === 'approved'
      && ctype_digit($remoto)
      && (int) $remoto > 0;
    if (!$aprovado && !in_array($status, self::STATUS_COBRANCA, TRUE)) {
      $this->logger->warning('Cobrança @id com status desconhecido: @status.', [
        '@id' => $this->providerString($cobranca['id'] ?? NULL),
        '@status' => $status,
      ]);
    }
    $preapproval_id = $this->providerString($cobranca['preapproval_id'] ?? NULL);
    if ($preapproval_id === '') {
      $this->logger->error('Cobrança @id não informa a assinatura vinculada.', ['@id' => $this->providerString($cobranca['id'] ?? NULL) ?: 'unknown']);
      return;
    }
    $assinatura = $this->recorrencia->obterAssinatura($gateway, $preapproval_id);
    if ($this->providerString($assinatura->id ?? NULL) !== $preapproval_id) {
      throw new MercadoPagoApiException((string) new TranslatableMarkup('O Mercado Pago retornou uma assinatura inconsistente.'), 502);
    }
    $pedido = $this->pedido($this->providerString($assinatura->external_reference ?? NULL));
    if ($pedido === NULL) {
      $this->logger->warning('Cobrança @id sem pedido de doação correspondente.', [
        '@id' => $this->providerString($cobranca['id'] ?? NULL),
      ]);
      return;
    }
    if ((string) $pedido->getData(self::CHAVE_ASSINATURA, '') !== $preapproval_id) {
      $this->logger->error('Cobrança @id não corresponde à assinatura registrada no pedido @pedido.', [
        '@id' => $this->providerString($cobranca['id'] ?? NULL) ?: 'unknown',
        '@pedido' => $pedido->id(),
      ]);
      return;
    }
    $lock_subject = $remoto !== '' ? $remoto : (string) ($cobranca['id'] ?? 'invalid');
    $lock_id = 'aculta_pagamentos_mercadopago.subscription_payment.' . $gateway->id() . '.' . $lock_subject;
    if (!$this->lock->acquire($lock_id, 30)) {
      throw new MercadoPagoApiException((string) new TranslatableMarkup('A cobrança está sendo processada. Tente novamente.'), 503);
    }
    try {
      $this->registrarCobranca($gateway, $pedido, $cobranca, $status, $remoto, $aprovado);
    }
    finally {
      $this->lock->release($lock_id);
    }
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

  /**
   * Confirms a provider subscription response belongs to the expected order.
   */
  private function validarAssinaturaRemota(object $assinatura, string $id, string $pedido_id): void {
    if ($this->providerString($assinatura->id ?? NULL) !== $id
      || $this->providerString($assinatura->external_reference ?? NULL) !== $pedido_id) {
      throw new MercadoPagoApiException((string) new TranslatableMarkup('O Mercado Pago retornou uma assinatura inconsistente.'), 502);
    }
  }

  /**
   * Determines whether a failed request could have created the remote object.
   */
  private function falhaRemotaAmbigua(MercadoPagoApiException $exception): bool {
    $status = $exception->codigoHttp();
    return $status === 0 || $status === 408 || $status === 409 || $status === 429 || $status >= 500;
  }

  /**
   * Reads the integer version returned by Mercado Pago.
   */
  private function versaoRemota(object $assinatura): int {
    $versao = $this->providerVersion($assinatura->version ?? NULL);
    if ($versao === NULL) {
      throw new MercadoPagoApiException((string) new TranslatableMarkup('O Mercado Pago não informou a versão da assinatura.'), 502);
    }
    return $versao;
  }

  /**
   * Reads the locally persisted provider version; old orders have no marker.
   */
  private function versaoLocal(OrderInterface $pedido): int {
    $value = $pedido->getData(self::CHAVE_VERSAO);
    if ($value === NULL) {
      // Existing orders have no provider version yet. Treat the first
      // verified notification, including version 0, as newer than no marker.
      return -1;
    }
    $versao = $this->providerVersion($value);
    if ($versao === NULL) {
      throw new MercadoPagoApiException((string) new TranslatableMarkup('A versão local da assinatura está inconsistente.'), 500);
    }
    return $versao;
  }

  /**
   * Converts an integer or digit-only provider version without coercion.
   */
  private function providerVersion(mixed $value): ?int {
    if (is_int($value) && $value >= 0) {
      return $value;
    }
    if (is_string($value) && ctype_digit($value)) {
      $version = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
      return $version === FALSE ? NULL : $version;
    }
    return NULL;
  }

  /**
   * Persists a verified provider state on the matching Commerce order.
   */
  private function salvarStatusAssinatura(OrderInterface $pedido, string $status, int $version): void {
    $pedido->setData(self::CHAVE_STATUS, $status);
    $pedido->setData(self::CHAVE_VERSAO, $version);
    if ($status === self::STATUS_CANCELADA) {
      $this->transicionar($pedido, 'cancel');
    }
    $pedido->save();
  }

  /**
   * Performs the pagamento existe operation.
   */
  private function registrarCobranca(PaymentGatewayInterface $gateway, OrderInterface $pedido, array $cobranca, string $status, string $remoto, bool $aprovado): void {
    $pedido_total = $pedido->getTotalPrice();
    $amount_raw = $cobranca['transaction_amount'] ?? NULL;
    $amount_number = is_string($amount_raw) || is_int($amount_raw) || is_float($amount_raw) ? (string) $amount_raw : '';
    $currency = $this->providerString($cobranca['currency_id'] ?? NULL);
    $amount = NULL;
    if ($aprovado) {
      if ($pedido_total === NULL || preg_match('/^[0-9]+(?:\.[0-9]+)?$/D', $amount_number) !== 1 || $currency !== $pedido_total->getCurrencyCode()) {
        $this->logger->error('Cobrança aprovada @id tem valor/moeda inválidos para o pedido @pedido.', [
          '@id' => $remoto,
          '@pedido' => $pedido->id(),
        ]);
        return;
      }
      $amount = new Price($amount_number, $currency);
      if (!$amount->greaterThan(new Price('0', $currency)) || !$amount->equals($pedido_total)) {
        $this->logger->error('Cobrança aprovada @id não corresponde ao total do pedido @pedido.', [
          '@id' => $remoto,
          '@pedido' => $pedido->id(),
        ]);
        return;
      }
    }

    $payments = $this->entidades('commerce_payment');
    $existentes = [];
    if ($aprovado) {
      $existentes = $payments->loadByProperties([
        'payment_gateway' => $gateway->id(),
        'remote_id' => $remoto,
      ]);
      foreach ($existentes as $existing) {
        if ((string) $existing->getOrderId() !== (string) $pedido->id()) {
          $this->logger->error('Pagamento recorrente @id já está associado a outro pedido.', ['@id' => $remoto]);
          return;
        }
      }
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

    if ($existentes === []) {
      $pagamento = $payments->create([
        'state' => 'completed',
        'amount' => $amount,
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
   * Performs the entidades operation.
   */
  private function entidades(string $tipo): EntityStorageInterface {
    return $this->entityTypeManager->getStorage($tipo);
  }

  /**
   * Converts a scalar provider value without coercing arrays or objects.
   */
  private function providerString(mixed $value): string {
    return is_string($value) || is_int($value) ? trim((string) $value) : '';
  }

}
