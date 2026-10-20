<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_mailer\Unit;

use Drupal\aculta_mailer\Alert\MailAlerts;
use Drupal\aculta_mailer\Monitor\BounceParser;
use Drupal\aculta_mailer\Monitor\DmarcReportParser;
use Drupal\aculta_mailer\Monitor\ImapClientInterface;
use Drupal\aculta_mailer\Monitor\MailMonitor;
use Drupal\aculta_mailer\Queue\MailQueue;
use Drupal\aculta_mailer\Webhook\DeliveryEventApplier;
use Drupal\aculta_mailer\Webhook\DeliveryEventType;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Component\Uuid\UuidInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\State\StateInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Psr\Log\LoggerInterface;

/**
 * Monitor IMAP: mapeamento de bounce para evento, cursor e resumo DMARC, com caixa falsa.
 */
#[CoversClass(MailMonitor::class)]
#[Group('aculta_mailer')]
final class MailMonitorTest extends UnitTestCase {

  private const NOW = 1800000000;

  /**
   * Notificação de falha (DSN) com o correlation ID do ACULTA.
   */
  private function bounce(string $status, string $correlation = 'abc-123'): string {
    $headers = $correlation === '' ? '' : "X-Aculta-Mail-ID: {$correlation}\nSubject: teste\n";
    return <<<MAIL
From: mailer-daemon@example.invalid
To: envio@example.invalid
Subject: Undelivered
MIME-Version: 1.0
Content-Type: multipart/report; report-type=delivery-status; boundary="b"

--b
Content-Type: text/plain

Falha na entrega.

--b
Content-Type: message/delivery-status

Final-Recipient: rfc822; destino@example.invalid
Action: failed
Status: {$status}
Diagnostic-Code: smtp; 550 user unknown

--b
Content-Type: message/rfc822

{$headers}
corpo

--b--

MAIL;
  }

  /**
   * Monitor com configuração e estado em memória, e tempo fixo.
   */
  private function monitor(?ImapClientInterface $unused = NULL): MailMonitor {
    $store = [];
    $state = $this->createMock(StateInterface::class);
    $state->method('get')->willReturnCallback(static function (string $key, mixed $default = NULL) use (&$store): mixed {
      return $store[$key] ?? $default;
    });
    $state->method('set')->willReturnCallback(static function (string $key, mixed $value) use (&$store): void {
      $store[$key] = $value;
    });
    $config = $this->createMock(ImmutableConfig::class);
    $config->method('get')->willReturn(NULL);
    $configFactory = $this->createMock(ConfigFactoryInterface::class);
    $configFactory->method('get')->willReturn($config);
    $time = $this->createMock(TimeInterface::class);
    $time->method('getRequestTime')->willReturn(self::NOW);
    $applier = new DeliveryEventApplier($this->createMock(EntityTypeManagerInterface::class));
    return new MailMonitor(
      $configFactory,
      $state,
      $time,
      $this->createMock(LoggerInterface::class),
      $applier,
      new BounceParser(),
      new DmarcReportParser(),
      NULL,
      new MailAlerts(
        $configFactory,
        $state,
        $time,
        new MailQueue($this->createMock(QueueFactory::class)),
        $this->createMock(UuidInterface::class),
      ),
    );
  }

  /**
   * Cliente falso: mensagens por UID, sem rede.
   */
  private function fakeClient(array $messages): ImapClientInterface {
    return new class($messages) implements ImapClientInterface {

      public function __construct(private readonly array $messages) {}

      /**
       * {@inheritdoc}
       */
      public function uidsAbove(int $uid): array {
        return array_values(array_filter(array_keys($this->messages), static fn(int $n): bool => $n > $uid));
      }

      /**
       * {@inheritdoc}
       */
      public function fetch(int $uid): string {
        return $this->messages[$uid];
      }

    };
  }

  /**
   * Código 5.x vira bounced e 4.x vira deferred; o código do provedor vai sem ponto.
   */
  public function testBounceCodeMapsToEventType(): void {
    $monitor = $this->monitor();
    $hard = $monitor->eventFromBounce($this->bounce('5.1.1'));
    $this->assertSame(DeliveryEventType::Bounced, $hard->type);
    $this->assertSame('abc-123', $hard->correlationId);
    $this->assertSame('5_1_1', $hard->code);

    $soft = $monitor->eventFromBounce($this->bounce('4.2.2'));
    $this->assertSame(DeliveryEventType::Deferred, $soft->type);
  }

  /**
   * Sem o correlation ID do ACULTA, nenhum evento é gerado.
   */
  public function testBounceWithoutCorrelationIsIgnored(): void {
    $this->assertNull($this->monitor()->eventFromBounce($this->bounce('5.1.1', '')));
  }

  /**
   * Mensagem comum não é notificação de falha.
   */
  public function testOrdinaryMessageIsIgnored(): void {
    $this->assertNull($this->monitor()->eventFromBounce("Subject: oi\n\ncorpo\n"));
  }

  /**
   * O cursor avança por mensagem: a segunda leitura só vê o que chegou depois.
   */
  public function testBouncePollAdvancesCursor(): void {
    $monitor = $this->monitor();
    $client = $this->fakeClient([1 => "Subject: oi\n\nx\n", 2 => "Subject: oi\n\ny\n"]);
    $this->assertSame(['checked' => 2, 'applied' => 0], $monitor->pollBounces($client));
    $this->assertSame(['checked' => 0, 'applied' => 0], $monitor->pollBounces($client));
  }

  /**
   * Relatório que não é DMARC não gera resumo, mas avança o cursor.
   */
  public function testDmarcPollSkipsNonReportsAndAdvancesCursor(): void {
    $monitor = $this->monitor();
    $client = $this->fakeClient([5 => "Subject: oi\n\nx\n"]);
    $this->assertSame(['checked' => 1, 'reports' => 0], $monitor->pollDmarc($client));
    $this->assertSame(['checked' => 0, 'reports' => 0], $monitor->pollDmarc($client));
    $this->assertSame([], $monitor->reports());
  }

}
