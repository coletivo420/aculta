<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_portal\Kernel;

use Drupal\aculta_portal\Account\EmailConfirmationPolicy;
use Drupal\KernelTests\KernelTestBase;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Verifies account activation and role grants for email confirmation.
 */
#[Group('aculta_portal')]
#[RunTestsInSeparateProcesses]
final class EmailConfirmationPolicyKernelTest extends KernelTestBase {

  /**
   * Kernel extensions needed to test the User entity and data store.
   *
   * @var string[]
   */
  protected static $modules = ['system', 'user'];

  /**
   * The policy instance under test.
   */
  private EmailConfirmationPolicy $policy;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installSchema('user', ['users_data']);
    Role::create(['id' => EmailConfirmationPolicy::ROLE, 'label' => 'E-mail confirmado'])->save();
    $this->policy = new EmailConfirmationPolicy(
      $this->container->get('user.data'),
      $this->container->get('entity_type.manager'),
      $this->container->get('module_handler'),
    );
  }

  /**
   * A new account is active even when the email has not been confirmed.
   */
  public function testNewAccountIsNotConfirmedAndHasNoRole(): void {
    $account = User::create(['name' => 'nova', 'mail' => 'nova@example.invalid', 'status' => 1]);
    $account->save();
    $this->assertTrue($account->isActive(), 'A falta de confirmação não bloqueia o login da conta ativa.');
    $this->assertFalse($this->policy->isConfirmed($account));
    $this->assertFalse($account->hasRole(EmailConfirmationPolicy::ROLE));
  }

  /**
   * Confirmation grants the role and repeating it is idempotent.
   */
  public function testConfirmationGrantsRoleAndIsIrreversible(): void {
    $account = User::create(['name' => 'confirmada', 'mail' => 'confirmada@example.invalid', 'status' => 1]);
    $account->save();
    $this->policy->markConfirmed((int) $account->id());
    $reloaded = User::load($account->id());
    $this->assertTrue($this->policy->isConfirmed($reloaded));
    $this->assertTrue($reloaded->hasRole(EmailConfirmationPolicy::ROLE));

    // Confirmar de novo não duplica nem remove o papel.
    $this->policy->markConfirmed((int) $account->id());
    $this->assertTrue(User::load($account->id())->hasRole(EmailConfirmationPolicy::ROLE));
  }

  /**
   * Anonymous UID zero cannot be marked as confirmed.
   */
  public function testAnonymousUidIsIgnored(): void {
    $this->policy->markConfirmed(0);
    $anonymous = User::create(['uid' => 0, 'name' => '', 'status' => 0]);
    $this->assertFalse($this->policy->isConfirmed($anonymous));
  }

}
