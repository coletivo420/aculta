<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_index\Kernel;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Routing\RouteMatch;
use Drupal\Core\Session\UserSession;
use Drupal\KernelTests\KernelTestBase;
use Drupal\aculta_portal\Domain\DomainPurposeManager;
use Drupal\domain\DomainNegotiatorInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * The Content Index status panel is limited to authorized operators.
 */
#[Group('aculta_index')]
#[RunTestsInSeparateProcesses]
final class ContentIndexStatusAccessKernelTest extends KernelTestBase {

  /** {@inheritdoc} */
  protected static $modules = [
    'system', 'user', 'field', 'file', 'image', 'media', 'entity_usage', 'media_duplicates',
    'aculta_image', 'aculta_index',
  ];

  /** {@inheritdoc} */
  public function register(ContainerBuilder $container): void {
    parent::register($container);
    $container->set('aculta_portal.domain_purpose', new DomainPurposeManager(
      $this->createMock(EntityTypeManagerInterface::class),
      $this->createMock(DomainNegotiatorInterface::class),
      $this->createMock(ConfigFactoryInterface::class),
      new RequestStack(),
    ));
  }

  public function testRegularUserCannotReadIndexStatus(): void {
    $route = $this->container->get('router.route_provider')->getRouteByName('aculta_index.status');
    $request = Request::create('/painel-administrativo/indice');
    $request->attributes->set('_route', 'aculta_index.status');
    $request->attributes->set('_route_object', $route);
    $result = $this->container->get('access_manager')->check(
      RouteMatch::createFromRequest($request),
      new UserSession(['uid' => 2, 'roles' => ['authenticated']]),
      $request,
      TRUE,
    );

    $this->assertFalse($result->isAllowed());
  }

}
