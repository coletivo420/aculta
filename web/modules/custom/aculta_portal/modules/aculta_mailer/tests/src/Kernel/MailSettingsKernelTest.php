<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_mailer\Kernel;

use Drupal\aculta_mailer\Controller\MailSettingsController;
use Drupal\aculta_mailer\Form\MailImapSettingsForm;
use Drupal\Core\Form\FormState;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Página de configurações do envio: rota protegida e aviso claro quando o gerenciador de credenciais não existe.
 */
#[Group('aculta_mailer')]
#[RunTestsInSeparateProcesses]
final class MailSettingsKernelTest extends KernelTestBase {

  /**
   * Módulos do teste (o aculta_portal não é habilitado aqui).
   *
   * @var string[]
   */
  protected static $modules = ['system', 'user', 'aculta_mailer'];

  /**
   * A rota exige a permissão do módulo e a página informa a indisponibilidade do gerenciador de credenciais.
   */
  public function testRouteRequiresPermissionAndReportsMissingSecretsManager(): void {
    $route = $this->container->get('router.route_provider')->getRouteByName('aculta_mailer.settings');
    $this->assertSame('administer aculta_mailer', $route->getRequirement('_permission'));

    $page = MailSettingsController::create($this->container)->settings();
    $this->assertArrayHasKey('#markup', $page);
    $this->assertStringContainsString('indisponível', (string) $page['#markup']);
  }

  /**
   * IMAP status renders only the exception class, never transport exception text.
   */
  public function testImapFailureStatusDoesNotRenderExceptionMessage(): void {
    $this->container->get('state')->set('aculta_mailer.imap.last_error', [
      'kind' => 'bounce',
      'when' => 1_800_000_000,
      'message' => 'Legacy error for private-mailbox@example.invalid',
    ]);
    $form = MailImapSettingsForm::create($this->container)->buildForm([], new FormState());

    $error = (string) $form['status']['error']['#markup'];
    $this->assertStringContainsString('LegacyTransportFailure', $error);
    $this->assertStringNotContainsString('private-mailbox@example.invalid', $error);
  }

}
