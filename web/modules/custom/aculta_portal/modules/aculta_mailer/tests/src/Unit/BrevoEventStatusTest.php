<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_mailer\Unit;

use Drupal\aculta_mailer\Provider\BrevoEventStatus;
use Drupal\aculta_mailer\Transport\SecretProviderInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * Consulta de estado no Brevo: formato da resposta, chave ausente e erro da API.
 */
#[Group('aculta_mailer')]
final class BrevoEventStatusTest extends UnitTestCase {

  /**
   * Serviço com a chave informada e as respostas simuladas.
   *
   * @param string|null $key
   *   Chave da API (NULL: sem chave).
   * @param \Symfony\Component\HttpClient\Response\MockResponse[] $responses
   *   Respostas em ordem.
   */
  private function service(?string $key, array $responses): BrevoEventStatus {
    return new BrevoEventStatus($this->secrets($key), new MockHttpClient($responses));
  }

  /**
   * Provedor de segredo com um único valor.
   */
  private function secrets(?string $key): SecretProviderInterface {
    return new class($key) implements SecretProviderInterface {

      public function __construct(private readonly ?string $key) {}

      /**
       * {@inheritdoc}
       */
      public function value(string $name): ?string {
        return $name === 'BREVO_API_KEY' ? $this->key : NULL;
      }

    };
  }

  /**
   * A resposta com a chave `events` vira lista de eventos com nome e data.
   */
  public function testParsesEventsObject(): void {
    $body = json_encode([
      'events' => [
        ['event' => 'requests', 'date' => '2026-10-20T16:58:31.000Z'],
        ['event' => 'delivered', 'date' => '2026-10-20T16:58:40.000Z'],
      ],
    ]);
    $events = $this->service('chave', [new MockResponse($body, ['http_code' => 200])])->events('202610201658.1@smtp-relay.mailin.fr', 7);
    $this->assertSame(['requests', 'delivered'], array_column($events, 'event'));
  }

  /**
   * Sem chave configurada, nenhuma chamada é feita e o resultado é NULL.
   */
  public function testNoKeyReturnsNullWithoutCalling(): void {
    $this->assertNull($this->service(NULL, [])->events('id', 7));
    $this->assertNull($this->service('', [])->events('id', 7));
  }

  /**
   * Erro da API (ex.: 400) não vira lista vazia: o chamador sabe que a consulta falhou.
   */
  public function testApiErrorReturnsNull(): void {
    $body = json_encode(['code' => 'invalid_parameter', 'message' => 'x']);
    $this->assertNull($this->service('chave', [new MockResponse($body, ['http_code' => 400])])->events('id', 7));
  }

  /**
   * Os colchetes são acrescentados uma vez, e a janela é limitada a 1-90 dias.
   */
  public function testWrapsMessageIdAndClampsDays(): void {
    $captured = [];
    $http = new MockHttpClient(function (string $method, string $url) use (&$captured): MockResponse {
      $captured[] = $url;
      return new MockResponse(json_encode(['events' => []]), ['http_code' => 200]);
    });
    $service = new BrevoEventStatus($this->secrets('chave'), $http);
    $service->events('<abc@relay.example.invalid>', 500);
    $this->assertStringContainsString('messageId=%3Cabc%40relay.example.invalid%3E', $captured[0]);
    $this->assertStringContainsString('days=90', $captured[0]);
  }

}
