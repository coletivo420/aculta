<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_image\Kernel;

use Drupal\aculta_image\ImageCleanup;
use Drupal\aculta_image\Quarantine\ImageQuarantine;
use Drupal\Core\Routing\RouteMatch;
use Drupal\Core\Session\UserSession;
use Drupal\file\FileInterface;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpFoundation\Request;

/**
 * The file usage inventory is restricted to image administrators.
 */
#[Group('aculta_image')]
#[RunTestsInSeparateProcesses]
final class ImageFileUsageAccessKernelTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system', 'user', 'file', 'image', 'media', 'entity_usage', 'media_duplicates', 'aculta_image',
  ];

  /**
   * A regular user cannot read the file usage report.
   */
  public function testRegularUserCannotReadFileUsage(): void {
    $route = $this->container->get('router.route_provider')->getRouteByName('aculta_image.file_usage');
    $request = Request::create('/painel-administrativo/imagens/1/usos');
    $request->attributes->set('_route', 'aculta_image.file_usage');
    $request->attributes->set('_route_object', $route);
    $result = $this->container->get('access_manager')->check(
      RouteMatch::createFromRequest($request),
      new UserSession(['uid' => 2, 'roles' => ['authenticated']]),
      $request,
      TRUE,
    );

    $this->assertFalse($result->isAllowed());
  }

  /**
   * Cleanup services install without Portal and quarantine fails closed.
   */
  public function testCleanupServicesBootWithoutPortalCoordinator(): void {
    self::assertFalse($this->container->get('module_handler')->moduleExists('aculta_portal'));
    self::assertInstanceOf(ImageCleanup::class, $this->container->get('aculta_image.cleanup'));

    $quarantine = $this->container->get('aculta_image.quarantine');
    self::assertInstanceOf(ImageQuarantine::class, $quarantine);
    self::assertFalse(
      $quarantine->enviar($this->createMock(FileInterface::class), 'coordinator unavailable'),
    );
  }

}
