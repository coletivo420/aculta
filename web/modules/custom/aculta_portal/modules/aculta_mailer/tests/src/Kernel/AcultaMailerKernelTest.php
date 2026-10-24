<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_mailer\Kernel;

use Drupal\aculta_mailer\Api\AcultaMailerInterface;
use Drupal\aculta_mailer\Delivery\DeliveryError;
use Drupal\Core\Lock\PersistentDatabaseLockBackend;
use Drupal\aculta_mailer\Message\MailMessage;
use Drupal\aculta_mailer\Message\MessageIdFactory;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Envio pela API, renderização pelo hook_mail e cabeçalho de correlação.
 */
#[Group('aculta_mailer')]
#[RunTestsInSeparateProcesses]
final class AcultaMailerKernelTest extends KernelTestBase {

  /**
   * Módulos do teste.
   *
   * @var string[]
   */
  protected static $modules = ['system', 'user', 'aculta_mailer', 'aculta_mailer_test'];

  /**
   * Correlation ID fixo usado nas mensagens de teste.
   */
  private const CORRELATION = '0190a3f2-7c1d-4e8a-9b2f-3d4e5f6a7b8c';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('aculta_mail_log');
  }

  /**
   * Envia um tipo registrado e confere assunto, corpo e cabeçalho.
   */
  public function testSendsRegisteredTypeWithCorrelationHeader(): void {
    $context = ['name' => 'Maria', 'link' => 'https://example.invalid/x'];
    $message = new MailMessage('aculta_mailer_test_message', 'destino@example.invalid', 'pt-br', $context, self::CORRELATION);

    $this->assertTrue($this->mailer()->send($message));

    $mails = $this->container->get('state')->get('system.test_mail_collector', []);
    $last = end($mails);
    $this->assertSame('aculta_mailer', $last['module']);
    $this->assertSame('aculta_mailer_test_message', $last['key']);
    $this->assertSame('destino@example.invalid', $last['to']);
    $this->assertSame('Assunto de teste para Maria', $last['subject']);
    $this->assertStringContainsString('https://example.invalid/x', (string) $last['body']);
    $this->assertSame(self::CORRELATION, $last['headers']['X-Aculta-Mail-ID']);
  }

  /**
   * Repeated successful delivery with the same correlation sends only once.
   */
  public function testAcceptedCorrelationIsNotDeliveredTwice(): void {
    $correlationId = MessageIdFactory::correlationIdFor('kernel-idempotency-test');
    $message = new MailMessage(
      'aculta_mailer_test_message',
      'destino@example.invalid',
      'pt-br',
      ['name' => 'Maria'],
      $correlationId,
    );

    $this->assertTrue($this->mailer()->send($message));
    $this->assertTrue($this->mailer()->send($message));
    $this->assertTrue($this->mailer()->wasAcceptedCorrelation($correlationId, $message->type));
    $this->config('aculta_mailer.log')->set('enabled', FALSE)->save();
    $this->assertTrue(
      $this->mailer()->wasAcceptedCorrelation($correlationId, $message->type),
      'Existing acceptance remains recoverable after logging is disabled.',
    );

    $mails = $this->container->get('state')->get('system.test_mail_collector', []);
    $this->assertCount(1, $mails);
  }

  /**
   * An idempotent delivery stays retryable while acceptance logging is off.
   */
  public function testIdempotentDeliveryRequiresAcceptanceLogging(): void {
    $this->config('aculta_mailer.log')->set('enabled', FALSE)->save();
    $message = new MailMessage(
      'aculta_mailer_test_message',
      'destino@example.invalid',
      'pt-br',
      ['name' => 'Maria'],
      MessageIdFactory::correlationIdFor('kernel-idempotency-logging-disabled'),
    );

    $result = $this->mailer()->deliver($message);

    $this->assertFalse($result->sent);
    $this->assertSame(DeliveryError::Temporary, $result->error);
    $this->assertEmpty($this->container->get('state')->get('system.test_mail_collector', []));
  }

  /**
   * A concurrent idempotent delivery fails retryably while its lock is held.
   */
  public function testConcurrentIdempotentDeliveryDoesNotReachTransport(): void {
    $correlationId = MessageIdFactory::correlationIdFor('kernel-lock-contention-test');
    $message = new MailMessage(
      'aculta_mailer_test_message',
      'destino@example.invalid',
      'pt-br',
      ['name' => 'Maria'],
      $correlationId,
    );
    $lockName = 'aculta_mailer.delivery.' . hash('sha256', $correlationId);
    $otherRequestLock = new PersistentDatabaseLockBackend($this->container->get('database'));
    $this->assertTrue($otherRequestLock->acquire($lockName, 180.0));
    try {
      $result = $this->container->get('aculta_mailer.mailer')->deliver($message);

      $this->assertFalse($result->sent);
      $this->assertSame(DeliveryError::Temporary, $result->error);
      $this->assertEmpty($this->container->get('state')->get('system.test_mail_collector', []));
    }
    finally {
      $otherRequestLock->release($lockName);
    }
  }

  /**
   * Tipo não registrado não envia nada e retorna FALSE.
   */
  public function testUnregisteredTypeIsNotSent(): void {
    $message = new MailMessage('tipo_inexistente', 'destino@example.invalid', 'pt-br', [], self::CORRELATION);

    $this->assertFalse($this->mailer()->send($message));
    $this->assertEmpty($this->container->get('state')->get('system.test_mail_collector', []));
  }

  /**
   * Serviço público de envio do módulo.
   */
  private function mailer(): AcultaMailerInterface {
    return $this->container->get('aculta_mailer.mailer');
  }

}
