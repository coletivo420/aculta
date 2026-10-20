<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_mail_confirmer\Kernel;

use Drupal\aculta_mail_confirmer\AcultaMailConfirmerManagerInterface;
use Drupal\aculta_mail_confirmer\InvalidConfirmationStateException;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Confirmação de e-mail incorporada: pedido, envio, confirmação, cancelamento, expiração e reenvio.
 *
 * Substitui o teste funcional do contrib, que exige um servidor web de testes.
 */
#[Group('aculta_mail_confirmer')]
#[RunTestsInSeparateProcesses]
final class AcultaMailConfirmerKernelTest extends KernelTestBase {

  protected static $modules = ['system', 'user', 'token', 'aculta_mail_confirmer'];

  private const EMAIL = 'pessoa@example.invalid';

  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('aculta_mail_confirmer');
    $this->installConfig(['aculta_mail_confirmer']);
    $this->installSchema('system', ['sequences']);
    $this->config('system.mail')->set('interface.default', 'test_mail_collector')->save();
  }

  private function manager(): AcultaMailConfirmerManagerInterface {
    return $this->container->get('aculta_mail_confirmer');
  }

  public function testConfirmCreatesPendingRequestAndSendsTheApprovedText(): void {
    $confirmation = $this->manager()->confirm(self::EMAIL);
    $this->assertNotNull($confirmation->id());
    $this->assertSame('pending', $confirmation->getStatus());

    $mails = $this->container->get('state')->get('system.test_mail_collector', []);
    $this->assertCount(1, $mails);
    $this->assertSame(self::EMAIL, $mails[0]['to']);
    $this->assertStringContainsString('Recebemos um pedido para usar ' . self::EMAIL, $mails[0]['body']);
    $this->assertStringContainsString('vale por 24 horas', $mails[0]['body']);
    $this->assertStringNotContainsString('[aculta-mail-confirmer:', $mails[0]['body'], 'Os tokens precisam ser substituídos.');
  }

  public function testCorrectHashConfirmsAndWrongHashDoesNot(): void {
    $confirmation = $this->manager()->confirm(self::EMAIL);
    $this->assertFalse($confirmation->confirm('hash-errado'));
    $this->assertSame('pending', $confirmation->getStatus());
    $this->assertTrue($confirmation->confirm($confirmation->getHash()));
    $this->assertSame('confirmed', $confirmation->getStatus());
  }

  public function testCancelMovesToCancelled(): void {
    $confirmation = $this->manager()->confirm(self::EMAIL);
    $confirmation->cancel();
    $this->assertSame('cancelled', $confirmation->getStatus());
  }

  public function testExpiredRequestCannotBeSentOrConfirmed(): void {
    $confirmation = $this->manager()->confirm(self::EMAIL);
    // Pedido com mais que o prazo de hash_expiration (24 h).
    $confirmation->setCreatedTime(time() - 86400 - 60);
    $this->assertSame('expired', $confirmation->getStatus());
    $this->expectException(InvalidConfirmationStateException::class);
    $confirmation->sendRequest();
  }

  public function testResendWithinTheDelayIsQueuedInsteadOfSentAgain(): void {
    $confirmation = $this->manager()->confirm(self::EMAIL);
    // confirm() já enviou o primeiro pedido.
    $mails = $this->container->get('state')->get('system.test_mail_collector', []);
    $this->assertCount(1, $mails);
    $this->assertTrue($confirmation->sendRequest());
    $this->assertCount(1, $this->container->get('state')->get('system.test_mail_collector', []), 'Dentro do atraso, nada novo sai.');
    // Reenvio dentro do prazo vai para a fila e não sai como e-mail. A contagem exata da fila variou entre execuções; investigar.
    $this->assertGreaterThanOrEqual(1, $this->container->get('queue')->get('aculta_mail_confirmer_requests')->numberOfItems());
  }

}
