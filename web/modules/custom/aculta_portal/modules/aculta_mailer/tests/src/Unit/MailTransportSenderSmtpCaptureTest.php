<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_mailer\Unit;

/**
 * @file
 * Testa o envio SMTP do Mailer com servidor de captura local controlado.
 */

use Drupal\aculta_mailer\Delivery\DeliverySettings;
use Drupal\aculta_mailer\Entity\MailTransport;
use Drupal\aculta_mailer\Transport\BrevoSecretTransportFactory;
use Drupal\aculta_mailer\Transport\MailTransportFactory;
use Drupal\aculta_mailer\Transport\MailTransportSender;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\Mime\Email;

/**
 * Testa a entrega SMTP em um coletor local limitado ao loopback.
 */
#[CoversClass(MailTransportSender::class)]
#[Group('aculta_mailer')]
final class MailTransportSenderSmtpCaptureTest extends UnitTestCase {

  /**
   * Envia uma mensagem sintética ao coletor SMTP local.
   */
  public function testSmtpSendUsesBounceEnvelopeAndDeliversMessage(): void {
    $directory = sys_get_temp_dir() . '/aculta-mailer-smtp-' . bin2hex(random_bytes(8));
    self::assertTrue(mkdir($directory, 0700));
    $capture = $directory . '/capture.txt';
    $pipes = [];
    $process = proc_open(
      [PHP_BINARY, __DIR__ . '/../../fixtures/controlled-smtp-capture-server.php', $capture],
      [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
      $pipes,
    );
    self::assertIsResource($process);

    try {
      fclose($pipes[0]);
      stream_set_timeout($pipes[1], 5);
      $port = (int) trim((string) fgets($pipes[1]));
      self::assertGreaterThan(0, $port, 'The loopback SMTP sink must announce its ephemeral port.');

      $settings = $this->createMock(ImmutableConfig::class);
      $settings->method('get')->with('bounce_address')->willReturn('retorno@example.invalid');
      $config = $this->createMock(ConfigFactoryInterface::class);
      $config->method('get')->with(DeliverySettings::CONFIG)->willReturn($settings);
      $factory = new MailTransportFactory(NULL, new BrevoSecretTransportFactory(NULL));
      $sender = new MailTransportSender($factory, $config);
      $transport = new MailTransport([
        'id' => 'smtp_local_test',
        'label' => 'SMTP local de teste',
        'plugin' => 'smtp',
        'host' => '127.0.0.1',
        'port' => $port,
        'verify_peer' => TRUE,
      ], MailTransport::ENTITY_TYPE);
      $email = (new Email())
        ->from('aculta@example.invalid')
        ->to('destino@example.invalid')
        ->subject('Mensagem SMTP de teste')
        ->text('Corpo sintético sem dados pessoais.');

      $result = $sender->send($transport, $email);

      self::assertTrue($result['sent'], $result['message']);
      self::assertSame(NULL, $result['error_class']);
      fclose($pipes[1]);
      fclose($pipes[2]);
      self::assertSame(0, proc_close($process));
      $transcript = (string) file_get_contents($capture);
      self::assertStringContainsString('MAIL FROM:<retorno@example.invalid>', $transcript);
      self::assertStringContainsString('RCPT TO:<destino@example.invalid>', $transcript);
      self::assertStringContainsString('Subject: Mensagem SMTP de teste', $transcript);
      self::assertStringContainsString('Corpo sintético sem dados pessoais.', quoted_printable_decode($transcript));
    }
    finally {
      if (is_resource($process)) {
        proc_terminate($process);
        foreach ([1, 2] as $pipe) {
          if (isset($pipes[$pipe]) && is_resource($pipes[$pipe])) {
            fclose($pipes[$pipe]);
          }
        }
        proc_close($process);
      }
      @unlink($capture);
      @rmdir($directory);
    }
  }

}
