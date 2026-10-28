<?php

declare(strict_types=1);

namespace Drupal\aculta_pagamentos_mercadopago\MercadoPago;

use Drupal\commerce_payment\Entity\PaymentGatewayInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use MercadoPago\Client\Common\RequestOptions;
use MercadoPago\Exceptions\MPApiException;

/**
 * Manages recurring plans and subscriptions through Mercado Pago.
 *
 * Planos (preapproval_plan) são modelos de cobrança; assinaturas (preapproval)
 * associam um pagador a um plano ou cobrança própria. Nascem em pending e o
 * pagador autoriza no Mercado Pago pelo init_point. A aplicação não guarda
 * cartão nem dado de pagamento, só o ID da assinatura.
 *
 * Os webhooks subscription_preapproval e subscription_authorized_payment são
 * tratados pelo serviço de notificações. Ver docs/portal/MERCADO-PAGO-PLANO.md.
 */
class MercadoPagoRecurrenceService {

  public function __construct(
    private readonly MercadoPagoClientFactory $clientes,
    private readonly RecurrenceRequestBuilder $montagem,
    private readonly LoggerChannelInterface $logger,
  ) {}

  /**
   * Creates a subscription plan and returns its ID and init_point.
   *
   * @param \Drupal\commerce_payment\Entity\PaymentGatewayInterface $gateway
   *   Gateway com credenciais do ambiente ativo.
   * @param array{motivo: string, valor: string, moeda: string, frequencia: int, back_url: string} $dados
   *   Dados validados para o plano.
   *
   * @return array{id: string, init_point: ?string}
   *   Identificador do plano e URL de autorização.
   */
  public function criarPlano(PaymentGatewayInterface $gateway, array $dados): array {
    $payload = $this->montagem->plano($dados);
    $opcoes = $this->opcoesIdempotencia('plan-create', $payload);
    $plano = $this->executar('criar plano', fn () => $this->clientes->criar('planos_assinatura', $gateway)->create($payload, $opcoes));
    $id = $this->idPlano($plano->id ?? NULL);
    if ($id === '') {
      throw new MercadoPagoApiException((string) new TranslatableMarkup('O Mercado Pago não devolveu um identificador de plano válido.'), 502);
    }
    $this->logger->notice('Plano de assinatura @id criado no Mercado Pago.', ['@id' => $plano->id]);
    return ['id' => $id, 'init_point' => is_string($plano->init_point ?? NULL) ? $plano->init_point : NULL];
  }

  /**
   * Consulta um plano de assinatura.
   */
  public function obterPlano(PaymentGatewayInterface $gateway, string $id): object {
    $id = trim($id);
    if ($this->idPlano($id) === '') {
      throw new \InvalidArgumentException('Identificador do plano inválido.');
    }
    $plano = $this->executar('consultar plano', fn () => $this->clientes->criar('planos_assinatura', $gateway)->get($id));
    if ($this->idPlano($plano->id ?? NULL) !== $id) {
      throw new MercadoPagoApiException((string) new TranslatableMarkup('O Mercado Pago retornou um plano inconsistente.'), 502);
    }
    return $plano;
  }

  /**
   * Updates a subscription plan with a stable key for safe retries.
   *
   * @param \Drupal\commerce_payment\Entity\PaymentGatewayInterface $gateway
   *   Gateway com credenciais do ambiente ativo.
   * @param string $id
   *   Identificador do plano existente.
   * @param array{motivo: string, valor: string, moeda: string, frequencia: int, back_url: string} $dados
   *   Dados validados do plano.
   *
   * @return object
   *   Plano atualizado, com o ID conferido.
   */
  public function alterarPlano(PaymentGatewayInterface $gateway, string $id, array $dados): object {
    $id = trim($id);
    if ($this->idPlano($id) === '') {
      throw new \InvalidArgumentException('Identificador do plano inválido.');
    }
    $payload = $this->montagem->plano($dados);
    $opcoes = $this->opcoesIdempotencia('plan-update|' . $id, $payload);
    $plano = $this->executar('alterar plano', fn () => $this->clientes->criar('planos_assinatura', $gateway)->update($id, $payload, $opcoes));
    if ($this->idPlano($plano->id ?? NULL) !== $id) {
      throw new MercadoPagoApiException((string) new TranslatableMarkup('O Mercado Pago retornou um plano inconsistente.'), 502);
    }
    $this->logger->notice('Plano de assinatura @id atualizado no Mercado Pago.', ['@id' => $id]);
    return $plano;
  }

  /**
   * Creates a subscription for the payer and returns its ID and init_point.
   *
   * O e-mail do pagador vai no payload e não é registrado em log.
   *
   * @param \Drupal\commerce_payment\Entity\PaymentGatewayInterface $gateway
   *   Gateway com credenciais do ambiente ativo.
   * @param array{email: string, back_url: string, referencia: string, plano_id?: ?string, motivo?: string, valor?: string, moeda?: string, frequencia?: int} $dados
   *   Dados validados para a assinatura.
   *
   * @return array{id: string, init_point: ?string, status: ?string}
   *   Identificador, URL de autorização e status remoto.
   */
  public function criarAssinatura(PaymentGatewayInterface $gateway, array $dados): array {
    $payload = $this->montagem->assinatura($dados);
    // The SDK creates a random idempotency key for each POST by default. That
    // does not protect a retry after a transport timeout, so scope a stable key
    // to the Commerce order reference and exact provider payload.
    $referencia = (string) $payload['external_reference'];
    $opcoes = $this->opcoesIdempotencia('subscription|' . $referencia, $payload);
    $assinatura = $this->executar('criar assinatura', fn () => $this->clientes->criar('assinaturas', $gateway)->create($payload, $opcoes));
    $this->logger->notice('Assinatura @id criada no Mercado Pago.', ['@id' => $assinatura->id]);
    return ['id' => (string) $assinatura->id, 'init_point' => $assinatura->init_point, 'status' => $assinatura->status];
  }

  /**
   * Consulta uma assinatura (status, próxima cobrança, plano).
   */
  public function obterAssinatura(PaymentGatewayInterface $gateway, string $id): object {
    return $this->executar('consultar assinatura', fn () => $this->clientes->criar('assinaturas', $gateway)->get($id));
  }

  /**
   * Pausa, reativa ou cancela uma assinatura existente.
   *
   * @throws \InvalidArgumentException
   *   Status fora de RecurrenceRequestBuilder::STATUS_ALTERAVEIS.
   */
  public function alterarStatusAssinatura(PaymentGatewayInterface $gateway, string $id, string $status): object {
    $payload = $this->montagem->statusAssinatura($status);
    $assinatura = $this->executar('alterar assinatura', fn () => $this->clientes->criar('assinaturas', $gateway)->update($id, $payload));
    $this->logger->notice('Assinatura @id alterada para @status no Mercado Pago.', ['@id' => $id, '@status' => $status]);
    return $assinatura;
  }

  /**
   * Executa a chamada ao SDK e traduz falhas em exceção segura.
   *
   * @param string $operacao
   *   Nome da operação para o registro de erro.
   * @param callable(): mixed $chamada
   *   Chamada ao SDK.
   *
   * @return mixed
   *   Resultado da chamada ao SDK.
   */
  private function executar(string $operacao, callable $chamada): mixed {
    try {
      return $chamada();
    }
    catch (MPApiException $e) {
      $this->logger->error('Falha ao @operacao no Mercado Pago (HTTP @codigo).', [
        '@operacao' => $operacao,
        '@codigo' => $e->getStatusCode(),
      ]);
      throw new MercadoPagoApiException((string) new TranslatableMarkup('O Mercado Pago não respondeu como esperado. Tente de novo em instantes.'), $e->getStatusCode(), $e);
    }
    catch (\Exception $exception) {
      // Transport exceptions can include request URLs or provider details.
      // Keep the log categorical and return the same safe message to callers.
      $this->logger->error('Falha de transporte ao @operacao no Mercado Pago.', [
        '@operacao' => $operacao,
      ]);
      throw new MercadoPagoApiException((string) new TranslatableMarkup('O Mercado Pago não respondeu como esperado. Tente de novo em instantes.'), 0, $exception);
    }
  }

  /**
   * Builds deterministic provider idempotency options for the exact operation.
   *
   * @param string $escopo
   *   Operation and resource scope included in the key.
   * @param array<string, mixed> $payload
   *   Validated provider payload.
   */
  private function opcoesIdempotencia(string $escopo, array $payload): RequestOptions {
    $serializado = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    $chave = hash('sha256', 'aculta-mercadopago|' . $escopo . '|' . $serializado);
    return new RequestOptions(NULL, NULL, ['x-idempotency-key' => $chave]);
  }

  /**
   * Validates a scalar provider plan ID without coercing other values.
   */
  private function idPlano(mixed $id): string {
    if ((!is_string($id) && !is_int($id)) || preg_match('/^[A-Za-z0-9_-]{1,64}$/', (string) $id) !== 1) {
      return '';
    }
    return (string) $id;
  }

}
