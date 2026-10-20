<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_portal\Kernel;

use Drupal\aculta_mailer\Type\MailTypeRegistry;
use Drupal\aculta_portal\Account\RegistrationExistingAccountMailType;
use Drupal\aculta_portal\Account\RegistrationMailPolicy;
use Drupal\KernelTests\KernelTestBase;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Cadastro com e-mail já usado (DT-P06): a política encontra a conta e avisa o dono, sem criar nada.
 */
#[Group('aculta_portal')]
#[RunTestsInSeparateProcesses]
final class RegistrationMailPolicyKernelTest extends KernelTestBase {

  protected static $modules = ['system', 'user', 'aculta_mailer'];

  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installSchema('user', ['users_data']);
    $this->installEntitySchema('aculta_mail_log');
    // O tipo vive no aculta_portal, que não é habilitado aqui; registra-se o mesmo objeto manualmente.
    $this->container->set(MailTypeRegistry::class, new MailTypeRegistry([
      new RegistrationExistingAccountMailType($this->container->get('string_translation')),
    ]));
  }

  public function testExistingAccountIsFoundOnlyForRegisteredMail(): void {
    $owner = User::create(['name' => 'titular', 'mail' => 'titular@example.invalid', 'status' => 1]);
    $owner->save();
    $policy = $this->policy();
    $this->assertSame((int) $owner->id(), (int) $policy->existingAccount('titular@example.invalid')?->id());
    $this->assertNull($policy->existingAccount('ninguem@example.invalid'));
    $this->assertNull($policy->existingAccount('   '));
  }

  public function testOwnerIsNotifiedThroughMailerAndNoAccountIsCreated(): void {
    $owner = User::create(['name' => 'titular2', 'mail' => 'titular2@example.invalid', 'status' => 1]);
    $owner->save();
    $before = count(User::loadMultiple());

    $sent = $this->policy()->notifyExistingAccount($owner);

    $this->assertTrue($sent);
    $mails = $this->container->get('state')->get('system.test_mail_collector', []);
    $this->assertNotEmpty($mails);
    $last = end($mails);
    $this->assertSame('titular2@example.invalid', $last['to']);
    $this->assertSame(RegistrationMailPolicy::MAIL_KEY, $last['key']);
    $this->assertSame('aculta_mailer', $last['module']);
    $this->assertMatchesRegularExpression('/^[0-9a-f-]{36}$/', (string) ($last['headers']['X-Aculta-Mail-ID'] ?? ''));
    $this->assertStringContainsString('Tentativa de cadastro', (string) $last['subject']);
    $this->assertStringContainsString('/user/login', (string) $last['body']);
    $this->assertSame($before, count(User::loadMultiple()), 'Nenhuma conta nova foi criada.');
  }

  private function policy(): RegistrationMailPolicy {
    return new RegistrationMailPolicy(
      $this->container->get('entity_type.manager'),
      $this->container->get('aculta_mailer.mailer'),
      $this->container->get('url_generator'),
      $this->container->get('uuid'),
    );
  }

}
