<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_mail_confirmer_user\Kernel;

use Drupal\aculta_mail_confirmer\AcultaMailConfirmerManagerInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Troca de e-mail da conta: só vale depois da confirmação do novo endereço.
 */
#[Group('aculta_mail_confirmer_user')]
#[RunTestsInSeparateProcesses]
final class UserEmailChangeKernelTest extends KernelTestBase {

  protected static $modules = ['system', 'user', 'token', 'aculta_mail_confirmer', 'aculta_mail_confirmer_user'];

  private const OLD_EMAIL = 'antiga@example.invalid';
  private const NEW_EMAIL = 'nova@example.invalid';

  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('aculta_mail_confirmer');
    $this->installSchema('user', ['users_data']);
    $this->installConfig(['aculta_mail_confirmer', 'aculta_mail_confirmer_user']);
    $this->config('system.mail')->set('interface.default', 'test_mail_collector')->save();
  }

  private function manager(): AcultaMailConfirmerManagerInterface {
    return $this->container->get('aculta_mail_confirmer');
  }

  private function createUserAndRequestChange(): User {
    $user = User::create(['name' => 'pessoa', 'mail' => self::OLD_EMAIL, 'status' => 1]);
    $user->save();
    $user = User::load($user->id());
    $user->setEmail(self::NEW_EMAIL);
    $user->save();
    return $user;
  }

  public function testEmailChangeIsOnlyAppliedAfterConfirmation(): void {
    $user = $this->createUserAndRequestChange();
    $this->assertSame(self::OLD_EMAIL, User::load($user->id())->getEmail(), 'O e-mail atual continua até a confirmação.');

    $pending = $this->manager()->getConfirmation(self::NEW_EMAIL, 'pending', 'aculta_mail_confirmer_user');
    $this->assertNotNull($pending, 'O novo endereço recebe um pedido de confirmação pendente.');
    $this->assertSame((int) $user->id(), (int) $pending->getProperty('user'));

    $this->assertTrue($this->manager()->confirmWithHash($pending, $this->manager()->getHash($pending)));
    $pending->save();
    $this->assertSame(self::NEW_EMAIL, User::load($user->id())->getEmail(), 'A confirmação aplica o novo e-mail.');
  }

  public function testCancelledChangeKeepsTheCurrentEmail(): void {
    $user = $this->createUserAndRequestChange();
    $pending = $this->manager()->getConfirmation(self::NEW_EMAIL, 'pending', 'aculta_mail_confirmer_user');
    $this->manager()->cancelConfirmation($pending);
    $pending->save();
    $this->assertSame(self::OLD_EMAIL, User::load($user->id())->getEmail(), 'Cancelar mantém o e-mail atual.');
    $this->assertSame('cancelled', $this->manager()->getStatus($pending));
  }

}
