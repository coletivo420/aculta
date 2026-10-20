<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_mailer\Kernel;

use Drupal\aculta_mailer\Api\AcultaMailerInterface;
use Drupal\aculta_mailer\Message\MailMessage;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Envio pela API: tipo registrado por tag, hook_mail do módulo e cabeçalho de correlação.
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
