<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_portal\Kernel;

use Drupal\aculta_mailer\Template\MailTemplateRenderer;
use Drupal\aculta_mailer\Type\MailTypeRegistry;
use Drupal\aculta_portal\Domain\DomainPurposeManager;
use Drupal\aculta_portal\Email\AccountMailRouter;
use Drupal\aculta_portal\Email\AccountPasswordResetMailType;
use Drupal\aculta_portal\Email\AccountRegisterAdminMailType;
use Drupal\aculta_portal\Email\AccountRegisterMailType;
use Drupal\domain\DomainNegotiatorInterface;
use Drupal\domain\Entity\Domain;
use Drupal\KernelTests\KernelTestBase;
use Drupal\user\Entity\User;
use Drupal\user\OneTimeAuthentication;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Textos de conta (aprovados em 2026-10-20) saem pela camada do aculta_mailer, com conta real e link de uso único.
 */
#[Group('aculta_portal')]
#[RunTestsInSeparateProcesses]
final class AccountMailRouterKernelTest extends KernelTestBase {

  protected static $modules = ['system', 'user', 'aculta_mailer', 'domain'];

  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installSchema('user', ['users_data']);
    $this->installEntitySchema('aculta_mail_log');
    $this->installEntitySchema('domain');
    Domain::create(['id' => 'conta_aculta_org', 'hostname' => 'conta.example.invalid', 'name' => 'conta', 'scheme' => 'https', 'weight' => 0])->save();
    $t = $this->container->get('string_translation');
    $this->container->set(MailTypeRegistry::class, new MailTypeRegistry([
      new AccountPasswordResetMailType($t),
      new AccountRegisterMailType($t),
      new AccountRegisterAdminMailType($t),
    ]));
  }

  /**
   * Router com o gerenciador de domínio real (o domínio de Conta criado no setUp).
   */
  private function router(): AccountMailRouter {
    $purposes = new DomainPurposeManager(
      $this->container->get('entity_type.manager'),
      $this->container->get(DomainNegotiatorInterface::class),
      $this->container->get('config.factory'),
      $this->container->get('request_stack'),
    );
    return new AccountMailRouter(
      $this->container->get(MailTypeRegistry::class),
      $this->container->get(MailTemplateRenderer::class),
      $this->container->get(OneTimeAuthentication::class),
      $this->container->get('config.factory'),
      $purposes,
    );
  }

  public function testResetMessageUsesApprovedTextAndOneTimeLink(): void {
    $account = User::create(['name' => 'pessoa', 'mail' => 'pessoa@example.invalid', 'status' => 1]);
    $account->save();
    $message = ['module' => 'user', 'key' => 'password_reset', 'params' => ['account' => $account], 'subject' => 'orig', 'body' => ['orig']];

    $this->router()->apply($message);

    $this->assertStringStartsWith('Redefinição de senha em ', $message['subject']);
    $this->assertStringContainsString('Recebemos um pedido para redefinir a senha', $message['body'][0]);
    $this->assertStringContainsString('vale por 24 horas', $message['body'][0]);
    $this->assertStringContainsString('/user/reset/', $message['body'][0]);
    $this->assertStringNotContainsString('{{', $message['body'][0]);
    $this->assertStringNotContainsString('orig', $message['body'][0]);
  }

  public function testRegisterMessageNamesTheUserAndTheLoginOptions(): void {
    $account = User::create(['name' => 'nova', 'mail' => 'nova@example.invalid', 'status' => 1]);
    $account->save();
    $message = ['module' => 'user', 'key' => 'register_no_approval_required', 'params' => ['account' => $account], 'subject' => '', 'body' => []];

    $this->router()->apply($message);

    $this->assertStringContainsString('nova', $message['body'][0]);
    $this->assertStringContainsString('com seu usuário (nova) ou com seu e-mail', $message['body'][0]);
    // A entrada aponta para o domínio de Conta, não para o domínio principal.
    $this->assertStringContainsString('https://conta.example.invalid/', $message['body'][0]);
  }

  public function testOtherKeysAndModulesAreLeftAlone(): void {
    $account = User::create(['name' => 'outra', 'mail' => 'outra@example.invalid', 'status' => 1]);
    $account->save();
    $message = ['module' => 'user', 'key' => 'status_blocked', 'params' => ['account' => $account], 'subject' => 'Core', 'body' => ['Core']];
    $this->router()->apply($message);
    $this->assertSame('Core', $message['subject']);
    $this->assertSame(['Core'], $message['body']);
  }

}
