<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_events\Kernel;

use Drupal\Core\Session\UserSession;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Checks access to the administrative event export route.
 */
#[Group('aculta_events')]
#[RunTestsInSeparateProcesses]
final class AdministrativeEventExportAccessKernelTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system', 'user', 'key', 'options', 'link', 'text', 'filter', 'taxonomy',
    'datetime', 'datetime_range', 'address', 'geofield', 'views',
    'aculta_calendar', 'aculta_events',
  ];

  /**
   * A regular account cannot access the administrative CSV export.
   */
  public function testExportRouteRequiresEventAdministration(): void {
    $this->container->get('router.builder')->rebuild();
    $route = $this->container->get('router.route_provider')->getRouteByName('aculta_events.admin_export');
    $this->assertSame('/painel-administrativo/eventos/exportar', $route->getPath());
    $this->assertSame('administer aculta events', $route->getRequirement('_permission'));
    $this->assertSame('eventos', $route->getOption('_aculta_domain_purpose'));
    $visitor = new UserSession(['uid' => 2, 'roles' => ['authenticated']]);
    $this->assertFalse($this->container->get('access_manager')->checkNamedRoute('aculta_events.admin_export', [], $visitor));
  }

}
