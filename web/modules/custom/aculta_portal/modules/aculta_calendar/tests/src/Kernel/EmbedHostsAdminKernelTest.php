<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_calendar\Kernel;

use Drupal\Core\Routing\RouteMatch;
use Drupal\Core\Session\UserSession;
use Drupal\KernelTests\KernelTestBase;
use Drupal\user\Entity\Role;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpFoundation\Request;

/**
 * Checks access and host data for the read-only embed administration page.
 */
#[Group('aculta_calendar')]
#[RunTestsInSeparateProcesses]
final class EmbedHostsAdminKernelTest extends KernelTestBase {

  /**
   * Modules enabled by this Kernel test.
   *
   * @var string[]
   */

  protected static $modules = ['system', 'user', 'aculta_calendar'];

  /**
   * Verifies regular user cannot view embed hosts.
   */
  public function testRegularUserCannotViewEmbedHosts(): void {
    $route = $this->container->get('router.route_provider')->getRouteByName('aculta_calendar.embed_hosts');
    $request = Request::create('/painel-administrativo/calendario/incorporacao');
    $request->attributes->set('_route', 'aculta_calendar.embed_hosts');
    $request->attributes->set('_route_object', $route);
    $result = $this->container->get('access_manager')->check(
      RouteMatch::createFromRequest($request),
      new UserSession(['uid' => 2, 'roles' => ['authenticated']]),
      $request,
      TRUE,
    );

    self::assertFalse($result->isAllowed());
  }

  /**
   * Verifies authorized user can view the deployer mapping read only.
   */
  public function testAuthorizedUserCanViewTheDeployerMappingReadOnly(): void {
    Role::create([
      'id' => 'calendar_embed_admin',
      'label' => 'Calendar embed administrator',
      'permissions' => ['administer aculta calendar embedding'],
    ])->save();
    $route = $this->container->get('router.route_provider')->getRouteByName('aculta_calendar.embed_hosts');
    $request = Request::create('/painel-administrativo/calendario/incorporacao');
    $request->attributes->set('_route', 'aculta_calendar.embed_hosts');
    $request->attributes->set('_route_object', $route);
    $result = $this->container->get('access_manager')->check(
      RouteMatch::createFromRequest($request),
      new UserSession(['uid' => 3, 'roles' => ['authenticated', 'calendar_embed_admin']]),
      $request,
      TRUE,
    );
    self::assertTrue($result->isAllowed());

    $page = $this->container->get('class_resolver')->getInstanceFromDefinition('Drupal\\aculta_calendar\\Controller\\EmbedHostsController');
    $build = $page->page();
    $mappings = $this->container->get('aculta_calendar.embed_hosts')->mappings();
    self::assertCount(count($mappings), $build['hosts']['#rows']);
    self::assertSame($mappings[0]['test_host'], $build['hosts']['#rows'][0][1]);
    self::assertSame($mappings[0]['production_host'], $build['hosts']['#rows'][0][2]);
    self::assertSame(0, $build['#cache']['max-age']);
  }

}
