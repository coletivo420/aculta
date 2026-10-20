<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_mailer\Kernel;

use Drupal\aculta_mailer\Controller\MailSettingsController;
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

}
