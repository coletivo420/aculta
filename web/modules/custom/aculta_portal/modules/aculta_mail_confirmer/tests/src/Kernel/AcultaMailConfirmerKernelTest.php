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
    $this->config('system.mail')->set('interface.default', 'test_mail_collector')->save();
  }

  private function manager(): AcultaMailConfirmerManagerInterface {
    return $this->container->get('aculta_mail_confirmer');
  }

  public function testConfirmCreatesPendingRequestAndSendsTheApprovedText(): void {
    $confirmation = $this->manager()->confirm(self::EMAIL);
    $this->assertNotNull($confirmation->id());
    $this->assertSame('pending', $this->manager()->getStatus($confirmation));

    $mails = $this->container->get('state')->get('system.test_mail_collector', []);
    $this->assertCount(1, $mails);
    $this->assertSame(self::EMAIL, $mails[0]['to']);
    $this->assertStringContainsString('Recebemos um pedido para usar ' . self::EMAIL, $mails[0]['body']);
    $this->assertStringContainsString('vale por 24 horas', $mails[0]['body']);
    $this->assertStringNotContainsString('[aculta-mail-confirmer:', $mails[0]['body'], 'Os tokens precisam ser substituídos.');
  }

  public function testCorrectHashConfirmsAndWrongHashDoesNot(): void {
    $confirmation = $this->manager()->confirm(self::EMAIL);
    $this->assertFalse($this->manager()->confirmWithHash($confirmation, 'hash-errado'));
    $this->assertSame('pending', $this->manager()->getStatus($confirmation));
    $this->assertTrue($this->manager()->confirmWithHash($confirmation, $this->manager()->getHash($confirmation)));
    $this->assertSame('confirmed', $this->manager()->getStatus($confirmation));
  }

  public function testCancelMovesToCancelled(): void {
    $confirmation = $this->manager()->confirm(self::EMAIL);
    $this->manager()->cancelConfirmation($confirmation);
    $this->assertSame('cancelled', $this->manager()->getStatus($confirmation));
  }

  public function testExpiredRequestCannotBeSentOrConfirmed(): void {
    $confirmation = $this->manager()->confirm(self::EMAIL);
    // Pedido com mais que o prazo de hash_expiration (24 h).
    $confirmation->setCreatedTime(time() - 86400 - 60);
    $this->assertSame('expired', $this->manager()->getStatus($confirmation));
    $this->expectException(InvalidConfirmationStateException::class);
    $this->manager()->sendRequest($confirmation);
  }

  public function testResendWithinTheDelayIsQueuedInsteadOfSentAgain(): void {
    $confirmation = $this->manager()->confirm(self::EMAIL);
    // confirm() já enviou o primeiro pedido.
    $mails = $this->container->get('state')->get('system.test_mail_collector', []);
    $this->assertCount(1, $mails);
    $this->assertTrue($this->manager()->sendRequest($confirmation));
    $this->assertCount(1, $this->container->get('state')->get('system.test_mail_collector', []), 'Dentro do atraso, nada novo sai.');
    // Reenvio dentro do prazo vai para a fila e não sai como e-mail.
    $this->assertSame(1, $this->container->get('queue')->get('aculta_mail_confirmer_requests')->numberOfItems());
  }

}
