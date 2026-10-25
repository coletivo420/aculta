<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_mailer\Kernel;

use Drupal\aculta_mailer\Controller\MailWebhookController;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Webhook de entrega: assinatura antes de tudo, transições permitidas e identificação por correlação ou provedor.
 */
#[Group('aculta_mailer')]
#[RunTestsInSeparateProcesses]
final class MailWebhookKernelTest extends KernelTestBase {

  /**
   * Módulos do teste.
   *
   * @var string[]
   */
  protected static $modules = ['system', 'user', 'aculta_mailer', 'aculta_mailer_test'];

  /**
   * Correlation ID da mensagem de teste.
   */
  private const CORRELATION = '0190a3f2-7c1d-4e8a-9b2f-3d4e5f6a7b8c';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('aculta_mail_log');
    $this->container->get('entity_type.manager')->getStorage('aculta_mail_log')->create([
      'correlation_id' => self::CORRELATION,
      'type' => 'aculta_mailer_test_message',
      'recipient_hash' => str_repeat('d', 64),
      'status' => 'sent',
      'transport' => 'smtp',
      'created' => time(),
    ])->save();
  }

  /**
   * Evento válido e assinado atualiza o registro pelo correlation ID.
   */
  public function testSignedEventUpdatesLog(): void {
    $response = $this->receive([['type' => 'delivered', 'correlation_id' => self::CORRELATION, 'at' => time()]]);

    $this->assertSame('{"applied":1}', $response->getContent());
    $this->assertSame('delivered', $this->log()->get('status')->value);
  }

  /**
   * Assinatura inválida: nada é lido nem gravado.
   */
  public function testInvalidSignatureIsRejectedWithoutWriting(): void {
    $this->expectException(AccessDeniedHttpException::class);
    try {
      $this->receive([['type' => 'delivered', 'correlation_id' => self::CORRELATION, 'at' => time()]], 'assinatura-errada');
    }
    finally {
      $this->assertSame('sent', $this->log()->get('status')->value);
    }
  }

  /**
   * Provedor desconhecido não existe na rota.
   */
  public function testUnknownProviderIsNotFound(): void {
    $this->expectException(NotFoundHttpException::class);
    MailWebhookController::create($this->container)->receive('inexistente', Request::create('/aculta-mailer/webhook/inexistente', 'POST'));
  }

  /**
   * Transição não permitida (entrega após já ter sido entregue, ou atraso após entrega) é ignorada.
   */
  public function testDisallowedTransitionIsIgnored(): void {
    $this->receive([['type' => 'delivered', 'correlation_id' => self::CORRELATION, 'at' => time()]]);
    $response = $this->receive([['type' => 'deferred', 'correlation_id' => self::CORRELATION, 'at' => time()]]);

    $this->assertSame('{"applied":0}', $response->getContent());
    $this->assertSame('delivered', $this->log()->get('status')->value);
  }

  /**
   * Evento sem correlação é identificado pelo ID do provedor; evento de mensagem desconhecida não altera nada.
   */
  public function testProviderIdentifiesEventAndUnknownIsIgnored(): void {
    $log = $this->log();
    $log->set('provider_message_id', 'prov-123')->save();

    $bounce = ['type' => 'bounced', 'provider_message_id' => 'prov-123', 'code' => '5.1.1', 'at' => time()];
    $byProvider = $this->receive([$bounce]);
    $this->assertSame('{"applied":1}', $byProvider->getContent());
    $this->assertSame('bounced', $this->log()->get('status')->value);
    $this->assertSame('provedor_5.1.1', $this->log()->get('error_code')->value);

    $desconhecida = ['type' => 'delivered', 'correlation_id' => 'ffffffff-ffff-4fff-8fff-ffffffffffff', 'at' => time()];
    $unknown = $this->receive([$desconhecida]);
    $this->assertSame('{"applied":0}', $unknown->getContent());
  }

  /**
   * Envia uma requisição assinada (ou não) para o provedor de teste.
   *
   * @param array $events
   *   Eventos do corpo.
   * @param string $signature
   *   Assinatura enviada no cabeçalho.
   */
  private function receive(array $events, string $signature = 'segredo-de-teste'): Response {
    $request = Request::create('/aculta-mailer/webhook/teste', 'POST', [], [], [], [], json_encode(['events' => $events]));
    $request->headers->set('X-Teste-Assinatura', $signature);
    return MailWebhookController::create($this->container)->receive('teste', $request);
  }

  /**
   * Registro da mensagem de teste.
   */
  private function log(): ContentEntityInterface {
    $logs = $this->container->get('entity_type.manager')->getStorage('aculta_mail_log')->loadByProperties(['correlation_id' => self::CORRELATION]);
    return reset($logs);
  }

}
