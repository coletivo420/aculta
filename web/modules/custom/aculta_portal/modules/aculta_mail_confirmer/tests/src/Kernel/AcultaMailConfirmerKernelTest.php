<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_mail_confirmer\Kernel;

use Drupal\aculta_mail_confirmer\AcultaMailConfirmerManagerInterface;
use Drupal\aculta_mail_confirmer\InvalidConfirmationStateException;
use Drupal\Core\Entity\EntityAccessCheck;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Core\Routing\RouteMatch;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\Routing\Route;
use Symfony\Component\Yaml\Yaml;

/**
 * Testa o fluxo de confirmação de e-mail incorporado.
 *
 * Substitui o teste funcional do contrib, que exige um servidor web de testes.
 */
#[Group('aculta_mail_confirmer')]
#[RunTestsInSeparateProcesses]
final class AcultaMailConfirmerKernelTest extends KernelTestBase {

  /**
   * Módulos instalados para os testes Kernel.
   *
   * @var string[]
   */
  protected static $modules = ['system', 'user', 'token', 'aculta_mail_confirmer'];

  private const EMAIL = 'pessoa@example.invalid';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('aculta_mail_confirmer');
    $this->installConfig(['user', 'aculta_mail_confirmer']);
    Role::load('authenticated')?->grantPermission('access aculta mail confirmer')->save();
    $this->config('system.mail')->set('interface.default', 'test_mail_collector')->save();
  }

  /**
   * Retorna o serviço de confirmação do container.
   */
  private function manager(): AcultaMailConfirmerManagerInterface {
    return $this->container->get('aculta_mail_confirmer');
  }

  /**
   * Verifica o pedido pendente e o conteúdo da mensagem. */
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

  /**
   * Verifica a confirmação com hash válido e inválido. */
  public function testCorrectHashConfirmsAndWrongHashDoesNot(): void {
    $confirmation = $this->manager()->confirm(self::EMAIL);
    $this->assertFalse($this->manager()->confirmWithHash($confirmation, 'hash-errado'));
    $this->assertSame('pending', $this->manager()->getStatus($confirmation));
    $this->assertTrue($this->manager()->confirmWithHash($confirmation, $this->manager()->getHash($confirmation)));
    $this->assertSame('confirmed', $this->manager()->getStatus($confirmation));
  }

  /**
   * Verifica o cancelamento da confirmação pendente. */
  public function testCancelMovesToCancelled(): void {
    $confirmation = $this->manager()->confirm(self::EMAIL);
    $this->manager()->cancelConfirmation($confirmation);
    $this->assertSame('cancelled', $this->manager()->getStatus($confirmation));
  }

  /**
   * Verifica que pedidos expirados não podem ser enviados. */
  public function testExpiredRequestCannotBeSentOrConfirmed(): void {
    $confirmation = $this->manager()->confirm(self::EMAIL);
    // Pedido com mais que o prazo de hash_expiration (24 h).
    $confirmation->setCreatedTime(time() - 86400 - 60);
    $this->assertSame('expired', $this->manager()->getStatus($confirmation));
    $this->expectException(InvalidConfirmationStateException::class);
    $this->manager()->sendRequest($confirmation);
  }

  /**
   * Verifica que o reenvio durante o cooldown é enfileirado. */
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

  /**
   * Verifica que a rota de reenvio respeita o acesso à entidade privada. */
  public function testPrivateResendAccessIsLimitedToTheConfirmationOwner(): void {
    $routeDefinition = Yaml::parseFile(dirname(__DIR__, 3) . '/aculta_mail_confirmer.routing.yml')['entity.aculta_mail_confirmer.resend'];
    $route = new Route(
      $routeDefinition['path'],
      $routeDefinition['defaults'] ?? [],
      $routeDefinition['requirements'] ?? [],
      $routeDefinition['options'] ?? [],
    );
    $this->assertSame('confirmation.update', $route->getRequirement('_entity_access'));

    $owner = User::create(['name' => 'confirmation-owner', 'mail' => 'owner@example.invalid']);
    $owner->save();
    $other = User::create(['name' => 'confirmation-other', 'mail' => 'other@example.invalid']);
    $other->save();
    $confirmation = $this->container->get('entity_type.manager')
      ->getStorage('aculta_mail_confirmer')
      ->create([
        'email' => self::EMAIL,
        'uid' => $owner->id(),
        'private' => TRUE,
      ]);
    $confirmation->save();

    $routeMatch = new RouteMatch('entity.aculta_mail_confirmer.resend', $route, ['confirmation' => $confirmation]);
    $accessChecker = new EntityAccessCheck();
    $this->assertTrue($accessChecker->access($route, $routeMatch, $owner)->isAllowed());
    $this->assertTrue($accessChecker->access($route, $routeMatch, $other)->isForbidden());
  }

}
