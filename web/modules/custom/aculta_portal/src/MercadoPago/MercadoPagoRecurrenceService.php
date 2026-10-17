<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\MercadoPago;

use Drupal\commerce_payment\Entity\PaymentGatewayInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use MercadoPago\Exceptions\MPApiException;

/**
 * Recorrência pela API de assinaturas (preapproval) do Mercado Pago.
 *
 * Planos (preapproval_plan) são modelos de cobrança; assinaturas (preapproval) ligam um pagador a um plano
 * ou a uma cobrança própria. A assinatura nasce em pending, e o pagador a autoriza no site do Mercado Pago
 * pelo init_point. A aplicação não guarda cartão nem dado de pagamento: só o id da assinatura.
 *
 * Os webhooks de assinatura (tópicos subscription_preapproval e subscription_authorized_payment) não são
 * tratados aqui; ficam para a fase de integração com a loja e as doações (ver docs/portal/MERCADO-PAGO-PLANO.md).
 */
class MercadoPagoRecurrenceService {

  public function __construct(
    private readonly MercadoPagoClientFactory $clientes,
    private readonly RecurrenceRequestBuilder $montagem,
    private readonly LoggerChannelInterface $logger,
  ) {}

  /**
   * Cria um plano de assinatura. Retorna o id do plano e o link de inscrição (init_point).
   *
   * @param array{motivo: string, valor: string, moeda: string, frequencia: int, back_url: string} $dados
   *
   * @return array{id: string, init_point: ?string}
   */
  public function criarPlano(PaymentGatewayInterface $gateway, array $dados): array {
    $payload = $this->montagem->plano($dados);
    $plano = $this->executar('criar plano', fn () => $this->clientes->criar('planos_assinatura', $gateway)->create($payload));
    $this->logger->notice('Plano de assinatura @id criado no Mercado Pago.', ['@id' => $plano->id]);
    return ['id' => (string) $plano->id, 'init_point' => $plano->init_point];
  }

  /**
   * Consulta um plano de assinatura.
   */
  public function obterPlano(PaymentGatewayInterface $gateway, string $id): object {
    return $this->executar('consultar plano', fn () => $this->clientes->criar('planos_assinatura', $gateway)->get($id));
  }

  /**
   * Cria uma assinatura para o pagador. Retorna o id e o link de autorização (init_point).
   *
   * O e-mail do pagador vai no payload e não é registrado em log.
   *
   * @param array{email: string, back_url: string, referencia: string, plano_id?: ?string, motivo?: string, valor?: string, moeda?: string, frequencia?: int} $dados
   *
   * @return array{id: string, init_point: ?string, status: ?string}
   */
  public function criarAssinatura(PaymentGatewayInterface $gateway, array $dados): array {
    $payload = $this->montagem->assinatura($dados);
    $assinatura = $this->executar('criar assinatura', fn () => $this->clientes->criar('assinaturas', $gateway)->create($payload));
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
   * @template T
   * @param callable(): T $chamada
   * @return T
   */
  private function executar(string $operacao, callable $chamada): mixed {
    try {
      return $chamada();
    }
    catch (MPApiException $e) {
      $this->logger->error('Falha ao @operacao no Mercado Pago (HTTP @codigo).', ['@operacao' => $operacao, '@codigo' => $e->getStatusCode()]);
      throw new MercadoPagoApiException((string) new TranslatableMarkup('O Mercado Pago não respondeu como esperado. Tente de novo em instantes.'), $e->getStatusCode(), $e);
    }
  }

}
