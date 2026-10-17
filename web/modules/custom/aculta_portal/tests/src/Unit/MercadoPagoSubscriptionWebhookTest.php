<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_portal\Unit;

use Drupal\aculta_portal\Commerce\MercadoPago\WebhookGuard;
use Drupal\aculta_portal\MercadoPago\MercadoPagoSubscriptionNotificationService;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\HttpFoundation\Request;

/**
 * Webhook de assinatura: assinatura HMAC válida encaminha o tópico; assinatura inválida é recusada.
 */
#[Group('aculta_portal')]
final class MercadoPagoSubscriptionWebhookTest extends UnitTestCase {

  private const SEGREDO = 'segredo-de-teste-nao-real';

  /**
   * Requisição assinada pelo mesmo algoritmo do SDK: manifesto id/request-id/ts, HMAC-SHA256.
   */
  private function requisicao(string $tipo, string $id, ?string $assinaturaErrada = NULL): Request {
    $ts = (string) time();
    $manifesto = "id:$id;request-id:req-1;ts:$ts;";
    $hash = $assinaturaErrada ?? hash_hmac('sha256', $manifesto, self::SEGREDO);
    $corpo = json_encode(['type' => $tipo, 'data' => ['id' => $id]], JSON_THROW_ON_ERROR);
    return Request::create('/notificacoes', 'POST', [], [], [], [
      'CONTENT_TYPE' => 'application/json',
      'HTTP_X_SIGNATURE' => "ts=$ts,v1=$hash",
      'HTTP_X_REQUEST_ID' => 'req-1',
    ], $corpo);
  }

  private function logger(): LoggerChannelInterface {
    return $this->createMock(LoggerChannelInterface::class);
  }

  public function testAssinaturaValidaEncaminhaOTopicoEDevolve200(): void {
    $servico = $this->createMock(MercadoPagoSubscriptionNotificationService::class);
    $servico->expects($this->once())->method('processar')->with('subscription_preapproval', 'abc123');
    $resposta = (new WebhookGuard($servico, $this->logger()))->validateAndNormalize($this->requisicao('subscription_preapproval', 'abc123'), TRUE, self::SEGREDO);
    $this->assertSame(200, $resposta->getStatusCode());
  }

  public function testAssinaturaInvalidaEhRecusadaSemProcessar(): void {
    $servico = $this->createMock(MercadoPagoSubscriptionNotificationService::class);
    $servico->expects($this->never())->method('processar');
    $resposta = (new WebhookGuard($servico, $this->logger()))->validateAndNormalize($this->requisicao('subscription_preapproval', 'abc123', str_repeat('0', 64)), TRUE, self::SEGREDO);
    $this->assertSame(401, $resposta->getStatusCode());
  }

  public function testFalhaDoServicoDevolve500ParaORetry(): void {
    $servico = $this->createMock(MercadoPagoSubscriptionNotificationService::class);
    $servico->method('processar')->willThrowException(new \RuntimeException('falha simulada'));
    $resposta = (new WebhookGuard($servico, $this->logger()))->validateAndNormalize($this->requisicao('subscription_authorized_payment', 'cob-9'), TRUE, self::SEGREDO);
    $this->assertSame(500, $resposta->getStatusCode());
  }

  public function testSemServicoTopicoDeAssinaturaContinuaSendoReconhecido(): void {
    $resposta = (new WebhookGuard())->validateAndNormalize($this->requisicao('subscription_preapproval_plan', 'plan-1'), TRUE, self::SEGREDO);
    $this->assertSame(200, $resposta->getStatusCode());
  }

}
