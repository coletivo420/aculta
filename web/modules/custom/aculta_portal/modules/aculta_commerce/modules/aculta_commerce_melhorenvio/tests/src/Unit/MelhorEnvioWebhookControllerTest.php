<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_commerce_melhorenvio\Unit;

use Drupal\aculta_commerce_melhorenvio\Controller\MelhorEnvioWebhookController;
use Drupal\aculta_commerce_melhorenvio\Http\MelhorEnvioConfig;
use Drupal\aculta_commerce_melhorenvio\Webhook\MelhorEnvioWebhookSignature;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\key\KeyInterface;
use Drupal\key\KeyRepositoryInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Controlador do webhook: ordem de decisão, respostas e log sem dados pessoais. Sem rede.
 */
#[CoversClass(MelhorEnvioWebhookController::class)]
#[Group('aculta_commerce_melhorenvio')]
class MelhorEnvioWebhookControllerTest extends UnitTestCase {

  /**
   * Segredo fictício do ambiente sandbox.
   */
  private const SECRET = 'segredo-webhook-fixture-nao-real';

  /**
   * Evento válido com protocolo e status, conforme a documentação.
   */
  private const BODY = '{"event":"order.posted","data":{"id":"0f1e2d3c-4b5a-4968-8777-665544332211","protocol":"ORD-PESSOAL-1","status":"posted"}}';

  /**
   * Assinatura válida do corpo, em base64.
   */
  private static function signature(string $body = self::BODY, string $secret = self::SECRET): string {
    return base64_encode(hash_hmac('sha256', $body, $secret, TRUE));
  }

  /**
   * Monta o controlador. Com $secret NULL, a Key não existe.
   */
  private function controller(?string $secret, ?LoggerInterface $logger = NULL): MelhorEnvioWebhookController {
    $settings = [
      'environment' => 'sandbox',
      'keys' => ['sandbox' => ['client_id' => 'me_sb_id', 'client_secret' => 'me_sb_secret']],
    ];
    $immutable = $this->createMock(ImmutableConfig::class);
    $immutable->method('get')->willReturnCallback(fn (string $key) => $settings[$key] ?? NULL);
    $factory = $this->createMock(ConfigFactoryInterface::class);
    $factory->method('get')->willReturn($immutable);

    $keys = $this->createMock(KeyRepositoryInterface::class);
    $keys->method('getKey')->willReturnCallback(function (string $id) use ($secret): ?KeyInterface {
      if ($id !== 'me_sb_secret' || $secret === NULL) {
        return NULL;
      }
      $key = $this->createMock(KeyInterface::class);
      $key->method('getKeyValue')->willReturn($secret);
      return $key;
    });

    $loggerFactory = $this->createMock(LoggerChannelFactoryInterface::class);
    $loggerFactory->method('get')->willReturn($logger ?? $this->createMock(LoggerInterface::class));

    return new MelhorEnvioWebhookController(new MelhorEnvioConfig($factory), $keys, new MelhorEnvioWebhookSignature(), $loggerFactory);
  }

  /**
   * Requisição POST com corpo e, opcionalmente, assinatura.
   */
  private function request(string $body, ?string $signature): Request {
    $request = Request::create('/integracoes/melhorenvio/webhook', 'POST', [], [], [], [], $body);
    if ($signature !== NULL) {
      $request->headers->set('X-ME-Signature', $signature);
    }
    return $request;
  }

  /**
   * Sem segredo configurado no ambiente, o webhook responde 503 e não lê o corpo.
   */
  public function testMissingSecretIsServiceUnavailable(): void {
    $response = $this->controller(NULL)->receive($this->request(self::BODY, self::signature()));
    $this->assertSame(503, $response->getStatusCode());
  }

  /**
   * Assinatura incorreta responde 403, e a mensagem não revela o motivo além do necessário.
   */
  public function testWrongSignatureIsForbidden(): void {
    $response = $this->controller(self::SECRET)->receive($this->request(self::BODY, self::signature(self::BODY, 'outro')));
    $this->assertSame(403, $response->getStatusCode());
    $this->assertStringNotContainsString(self::SECRET, (string) $response->getContent());
  }

  /**
   * Assinatura ausente também responde 403.
   */
  public function testMissingSignatureIsForbidden(): void {
    $response = $this->controller(self::SECRET)->receive($this->request(self::BODY, NULL));
    $this->assertSame(403, $response->getStatusCode());
  }

  /**
   * Corpo assinado mas fora do formato documentado responde 400.
   */
  public function testSignedInvalidBodyIsBadRequest(): void {
    $body = '{"event":"order.inventado","data":{"id":"0f1e2d3c-4b5a-4968-8777-665544332211","status":"posted"}}';
    $response = $this->controller(self::SECRET)->receive($this->request($body, self::signature($body)));
    $this->assertSame(400, $response->getStatusCode());
  }

  /**
   * Evento válido responde 204 sem corpo e sem cache.
   *
   * O log não recebe protocolo nem o corpo bruto.
   */
  public function testValidEventIsNoContentAndLogIsSanitized(): void {
    $logger = $this->createMock(LoggerInterface::class);
    $logger->expects($this->once())->method('notice')->with(
      $this->stringContains('Webhook do Melhor Envio'),
      $this->callback(static fn (array $context): bool => array_keys($context) === ['event', 'id', 'status']
        && $context['event'] === 'order.posted'),
    );
    $response = $this->controller(self::SECRET, $logger)->receive($this->request(self::BODY, self::signature()));
    $this->assertSame(204, $response->getStatusCode());
    $this->assertSame('', (string) $response->getContent());
    // O Symfony normaliza a ordem das diretivas; confere-se cada uma.
    $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
    $this->assertStringContainsString('private', (string) $response->headers->get('Cache-Control'));
  }

  /**
   * Reenvio do mesmo evento é aceito sem efeito colateral.
   *
   * O controlador não guarda estado e pode receber novas tentativas do provedor.
   */
  public function testRepeatedEventIsAcceptedAgainWithoutState(): void {
    $controller = $this->controller(self::SECRET);
    $first = $controller->receive($this->request(self::BODY, self::signature()));
    $second = $controller->receive($this->request(self::BODY, self::signature()));
    $this->assertSame(204, $first->getStatusCode());
    $this->assertSame(204, $second->getStatusCode());
  }

}
