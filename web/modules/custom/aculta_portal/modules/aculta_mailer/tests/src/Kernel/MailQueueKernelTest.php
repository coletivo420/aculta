<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_mailer\Kernel;

use Drupal\aculta_mailer\Message\MailMessage;
use Drupal\aculta_mailer\Queue\MailQueue;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Fila de envio: enfileirar, processar pelo worker, descartar tipo desconhecido e respeitar o horário da tentativa.
 */
#[Group('aculta_mailer')]
#[RunTestsInSeparateProcesses]
final class MailQueueKernelTest extends KernelTestBase {

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
   * Mensagem enfileirada é enviada pelo worker e sai da fila.
   */
  public function testQueuedMessageIsSentByWorker(): void {
    $this->queue()->enqueue($this->testMessage());

    $this->runWorkerOnce();

    $mails = $this->container->get('state')->get('system.test_mail_collector', []);
    $this->assertCount(1, $mails);
    $this->assertSame('aculta_mailer_test_message', end($mails)['key']);
    $this->assertSame(0, $this->queueItems());
  }

  /**
   * Tipo não registrado é descartado sem envio e sem nova tentativa.
   */
  public function testUnregisteredTypeIsDiscarded(): void {
    $message = new MailMessage('tipo_nao_registrado', 'destino@example.invalid', 'pt-br', [], self::CORRELATION);
    $this->queue()->enqueue($message);

    $this->runWorkerOnce();

    $this->assertEmpty($this->container->get('state')->get('system.test_mail_collector', []));
    $this->assertSame(0, $this->queueItems());
  }

  /**
   * Tentativa com horário futuro volta para a fila sem envio.
   */
  public function testFutureAttemptIsRequeuedWithoutSending(): void {
    $this->container->get('queue')->get(MailQueue::QUEUE_NAME)->createItem([
      'message' => $this->testMessage()->toArray(),
      'attempt' => 2,
      'not_before' => $this->container->get('datetime.time')->getRequestTime() + 3600,
      'transport' => 'hostinger',
    ]);

    $this->runWorkerOnce();

    $this->assertEmpty($this->container->get('state')->get('system.test_mail_collector', []));
    $this->assertSame(1, $this->queueItems());
  }

  /**
   * Processa um item da fila como o cron faria.
   */
  private function runWorkerOnce(): void {
    $queue = $this->container->get('queue')->get(MailQueue::QUEUE_NAME);
    $item = $queue->claimItem();
    $this->assertNotFalse($item, 'Esperava um item na fila.');
    $worker = $this->container->get('plugin.manager.queue_worker')->createInstance(MailQueue::QUEUE_NAME);
    $worker->processItem($item->data);
    $queue->deleteItem($item);
  }

  /**
   * Quantidade de itens pendentes na fila de envio.
   */
  private function queueItems(): int {
    return $this->container->get('queue')->get(MailQueue::QUEUE_NAME)->numberOfItems();
  }

  /**
   * Serviço de enfileiramento do módulo.
   */
  private function queue(): MailQueue {
    return $this->container->get('aculta_mailer.queue');
  }

  /**
   * Mensagem de teste com contexto escalar.
   */
  private function testMessage(): MailMessage {
    $context = ['name' => 'Maria', 'link' => 'https://example.invalid/x'];
    return new MailMessage('aculta_mailer_test_message', 'destino@example.invalid', 'pt-br', $context, self::CORRELATION);
  }

}
