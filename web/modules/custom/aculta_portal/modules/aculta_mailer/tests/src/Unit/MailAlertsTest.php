<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_mailer\Unit;

use Drupal\aculta_mailer\Alert\MailAlerts;
use Drupal\aculta_mailer\Delivery\AdministratorRecipients;
use Drupal\aculta_mailer\Delivery\DeliverySettings;
use Drupal\aculta_mailer\Queue\MailQueue;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Component\Uuid\UuidInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\Queue\QueueInterface;
use Drupal\Core\State\StateInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Alertas: janela de 5 minutos, destinatários e recusa de laço. Fila e estado em memória.
 */
#[CoversClass(MailAlerts::class)]
#[CoversClass(DeliverySettings::class)]
#[Group('aculta_mailer')]
final class MailAlertsTest extends UnitTestCase {

  private const T0 = 1800000000;

  /**
   * Quantidade de itens enfileirados no teste.
   */
  private int $queued = 0;

  /**
   * Tempo atual do teste, em timestamp Unix.
   */
  private int $now = self::T0;

  /**
   * Alertas com a configuração informada.
   */
  private function alerts(array $settings): MailAlerts {
    $store = [];
    $state = $this->createMock(StateInterface::class);
    $state->method('get')->willReturnCallback(static function (string $key, mixed $default = NULL) use (&$store): mixed {
      return $store[$key] ?? $default;
    });
    $state->method('set')->willReturnCallback(static function (string $key, mixed $value) use (&$store): void {
      $store[$key] = $value;
    });
    $config = $this->createMock(ImmutableConfig::class);
    $config->method('get')->willReturnCallback(static fn(string $key): mixed => $settings[$key] ?? NULL);
    $configFactory = $this->createMock(ConfigFactoryInterface::class);
    $configFactory->method('get')->willReturn($config);
    $time = $this->createMock(TimeInterface::class);
    $time->method('getRequestTime')->willReturnCallback(fn(): int => $this->now);
    $queue = $this->createMock(QueueInterface::class);
    $queue->method('createItem')->willReturnCallback(function (): int {
      $this->queued++;
      return $this->queued;
    });
    $factory = $this->createMock(QueueFactory::class);
    $factory->method('get')->willReturn($queue);
    $uuid = $this->createMock(UuidInterface::class);
    $uuid->method('generate')->willReturn('3f2504e0-4f89-41d3-9a0c-0305e82c3301');
    $administrators = $this->createMock(AdministratorRecipients::class);
    $administrators->method('emails')->willReturn([]);
    return new MailAlerts($configFactory, $state, $time, new MailQueue($factory), $uuid, $administrators);
  }

  /**
   * Configuração com alertas ligados e dois destinatários.
   */
  private function enabled(): array {
    return ['alerts_enabled' => TRUE, 'alerts_recipients' => 'a@example.invalid, b@example.invalid'];
  }

  /**
   * Desligado, nenhum alerta entra na fila.
   */
  public function testDisabledQueuesNothing(): void {
    $this->alerts(['alerts_enabled' => FALSE, 'alerts_recipients' => 'a@example.invalid'])
      ->failure('registration_existing', 'c-1', 'smtp', 'smtp_554', '554 recusado');
    $this->assertSame(0, $this->queued);
  }

  /**
   * Um alerta por destinatário; o segundo dentro da janela só é contado.
   */
  public function testThrottleKeepsOneAlertPerWindow(): void {
    $alerts = $this->alerts($this->enabled());
    $alerts->failure('registration_existing', 'c-1', 'smtp', 'smtp_554', '554 recusado');
    $this->assertSame(2, $this->queued);
    $this->now += 60;
    $alerts->failure('registration_existing', 'c-2', 'smtp', 'smtp_550', '550 inexistente');
    $this->assertSame(2, $this->queued);
  }

  /**
   * Depois da janela, o próximo alerta sai.
   */
  public function testAlertReturnsAfterWindow(): void {
    $alerts = $this->alerts($this->enabled());
    $alerts->hardBounce('c-1', '5_1_1');
    $this->now += MailAlerts::THROTTLE;
    $alerts->hardBounce('c-2', '5_1_1');
    $this->assertSame(4, $this->queued);
  }

  /**
   * Mensagens do próprio sistema de alertas e de resumo não geram alerta (evita laço).
   */
  public function testAlertTypesDoNotAlertAgain(): void {
    $alerts = $this->alerts($this->enabled());
    $alerts->failure('aculta_admin_alert', 'c-1', 'smtp', 'smtp_554', NULL);
    $alerts->failure('aculta_weekly_summary', 'c-2', 'smtp', 'smtp_554', NULL);
    $this->assertSame(0, $this->queued);
  }

  /**
   * Lista de destinatários: separadores variados, minúsculas, sem repetição e sem itens inválidos.
   */
  public function testRecipientListIsCleaned(): void {
    $this->assertSame(
      ['a@example.invalid', 'b@example.invalid'],
      DeliverySettings::recipients("A@example.invalid;\nb@example.invalid, a@example.invalid  nao-e-email"),
    );
    $this->assertSame(['nao-e-email'], DeliverySettings::invalid("A@example.invalid nao-e-email"));
  }

}
