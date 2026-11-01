<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_mailer\Kernel;

use Drupal\aculta_mailer\Message\MailMessage;
use Drupal\aculta_mailer\Message\MailStatus;
use Drupal\aculta_mailer\Log\MailLogSettings;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Registro de envios: estado sem endereço em claro, sem corpo e com retenção por prazo.
 */
#[Group('aculta_mailer')]
#[RunTestsInSeparateProcesses]
final class MailLogKernelTest extends KernelTestBase {

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
   * Endereço de teste, reservado pelo RFC 2606.
   */
  private const ADDRESS = 'destino@example.invalid';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('aculta_mail_log');
  }

  /**
   * Envio aceito grava estado sent, data de aceite e HMAC do destinatário, sem o endereço em claro.
   */
  public function testSentMessageIsLoggedWithoutPlainAddress(): void {
    $context = ['name' => 'Maria', 'link' => 'https://example.invalid/x'];
    $message = new MailMessage('aculta_mailer_test_message', self::ADDRESS, 'pt-br', $context, self::CORRELATION);
    $this->assertTrue($this->container->get('aculta_mailer.mailer')->send($message));

    $log = $this->logFor(self::CORRELATION);
    $this->assertSame('sent', $log->get('status')->value);
    $this->assertNotEmpty($log->get('sent')->value);
    $this->assertSame(1, (int) $log->get('attempts')->value);
    $this->assertNotSame(self::ADDRESS, $log->get('recipient_hash')->value);
    $this->assertSame(64, strlen((string) $log->get('recipient_hash')->value));
  }

  /**
   * O HMAC é estável para o mesmo endereço, sem diferenciar maiúsculas.
   */
  public function testRecipientHashIsStableAndCaseInsensitive(): void {
    $recorder = $this->container->get('aculta_mailer.log_recorder');
    $this->assertSame($recorder->hashRecipient(self::ADDRESS), $recorder->hashRecipient('DESTINO@example.invalid'));
  }

  /**
   * Registros mais antigos que o prazo são apagados pelo cron; os recentes ficam.
   */
  public function testRetentionDeletesOnlyOldLogs(): void {
    $storage = $this->container->get('entity_type.manager')->getStorage('aculta_mail_log');
    $antigo = time() - (MailLogSettings::DEFAULT_RETENTION_DAYS + 5) * 86400;
    $old = $storage->create([
      'correlation_id' => 'a0000000-0000-4000-8000-000000000001',
      'type' => 'aculta_mailer_test_message',
      'recipient_hash' => 'x',
      'status' => 'sent',
      'created' => $antigo,
    ]);
    $old->save();
    $recent = $storage->create([
      'correlation_id' => 'a0000000-0000-4000-8000-000000000002',
      'type' => 'aculta_mailer_test_message',
      'recipient_hash' => 'x',
      'status' => 'sent',
      'created' => time(),
    ]);
    $recent->save();

    $this->container->get('module_handler')->invoke('aculta_mailer', 'cron');

    $this->assertNull($storage->load($old->id()));
    $this->assertNotNull($storage->load($recent->id()));
  }

  /**
   * Busca o registro de um correlation ID.
   */
  private function logFor(string $correlation): ContentEntityInterface {
    $logs = $this->container->get('entity_type.manager')->getStorage('aculta_mail_log')->loadByProperties(['correlation_id' => $correlation]);
    $this->assertCount(1, $logs);
    return reset($logs);
  }

}
