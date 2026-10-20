<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_mailer\Kernel;

use Drupal\aculta_mailer\Api\AcultaMailerInterface;
use Drupal\aculta_mailer\Delivery\DeliveryError;
use Drupal\aculta_mailer\Message\MailMessage;
use Drupal\aculta_mailer\Queue\MailQueue;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Falha de transporte de ponta a ponta: código SMTP, classe do erro, registro e failover pela fila.
 */
#[Group('aculta_mailer')]
#[RunTestsInSeparateProcesses]
final class MailDeliveryKernelTest extends KernelTestBase {

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
    // O KernelTestBase fixa o coletor de testes por $GLOBALS['config']; troca-se só para este teste.
    $GLOBALS['config']['system.mail']['interface']['default'] = 'aculta_mailer_failing_test';
    $this->container->get('config.factory')->reset('system.mail');
  }

  /**
   * Erro 550 é permanente: não há retry nem troca de transporte.
   */
  public function testPermanentSmtpCodeIsPermanent(): void {
    $this->failWith(550);
    $result = $this->mailer()->deliver($this->message(), 'smtp');

    $this->assertFalse($result->sent);
    $this->assertSame(DeliveryError::Permanent, $result->error);
    $this->assertSame(550, $result->smtpCode);
    $this->assertSame('smtp_550', $this->logFor(self::CORRELATION)->get('error_code')->value);
    // O relatório guarda o texto completo do transporte; a mensagem ao usuário continua genérica.
    $this->assertSame('Expected response code "250" but got code "550".', $this->logFor(self::CORRELATION)->get('error_message')->value);
    $this->assertSame('failed', $this->logFor(self::CORRELATION)->get('status')->value);
  }

  /**
   * Erro 421 é temporário e o transporte escolhido vai no cabeçalho X-Transport.
   */
  public function testTemporarySmtpCodeIsTemporaryAndCarriesTransport(): void {
    $this->failWith(421);
    $result = $this->mailer()->deliver($this->message(), 'smtp');

    $this->assertFalse($result->sent);
    $this->assertSame(DeliveryError::Temporary, $result->error);
    $this->assertSame(421, $result->smtpCode);
    $this->assertSame('smtp', $this->lastHeaders()['X-Transport'] ?? NULL);
  }

  /**
   * Tipo não registrado é permanente e nem chega ao sistema de e-mail.
   */
  public function testUnregisteredTypeIsPermanentAndNotSent(): void {
    $message = new MailMessage('tipo_nao_registrado', 'destino@example.invalid', 'pt-br', [], self::CORRELATION);
    $result = $this->mailer()->deliver($message);

    $this->assertFalse($result->sent);
    $this->assertSame(DeliveryError::Permanent, $result->error);
    $this->assertNull($this->container->get('state')->get('aculta_mailer_test.last_headers'));
  }

  /**
   * Na fila, falha temporária no transporte padrão reagenda pelo smtp2go com a segunda tentativa.
   */
  public function testQueueFailsOverToFallbackAfterTemporaryFailure(): void {
    $this->failWith(421);
    $this->container->get('aculta_mailer.queue')->enqueue($this->message());
    $queue = $this->container->get('queue')->get(MailQueue::QUEUE_NAME);
    $item = $queue->claimItem();
    $this->container->get('plugin.manager.queue_worker')->createInstance(MailQueue::QUEUE_NAME)->processItem($item->data);
    $queue->deleteItem($item);

    $next = $queue->claimItem();
    $this->assertNotFalse($next, 'A falha temporária deve reagendar.');
    $this->assertSame('smtp2go', $next->data['transport']);
    $this->assertSame(2, $next->data['attempt']);
    $this->assertGreaterThan(time(), $next->data['not_before']);
  }

  /**
   * Define o código que o transporte de teste devolve.
   */
  private function failWith(int $code): void {
    $this->container->get('state')->set('aculta_mailer_test.failure_code', $code);
  }

  /**
   * Cabeçalhos da última mensagem enviada ao transporte de teste.
   *
   * @return array
   *   Cabeçalhos enviados ao transporte de teste.
   */
  private function lastHeaders(): array {
    return $this->container->get('state')->get('aculta_mailer_test.last_headers', []);
  }

  /**
   * Serviço público de envio do módulo.
   */
  private function mailer(): AcultaMailerInterface {
    return $this->container->get('aculta_mailer.mailer');
  }

  /**
   * Mensagem de teste com contexto escalar.
   */
  private function message(): MailMessage {
    $context = ['name' => 'Maria', 'link' => 'https://example.invalid/x'];
    return new MailMessage('aculta_mailer_test_message', 'destino@example.invalid', 'pt-br', $context, self::CORRELATION);
  }

  /**
   * Registro do envio pelo correlation ID.
   */
  private function logFor(string $correlation): ContentEntityInterface {
    $logs = $this->container->get('entity_type.manager')->getStorage('aculta_mail_log')->loadByProperties(['correlation_id' => $correlation]);
    $this->assertCount(1, $logs);
    return reset($logs);
  }

}
