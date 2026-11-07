<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_studio\Unit;

use Drupal\aculta_studio\Connections\StudioDashboardConnectionsComposer;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Routing\UrlGeneratorInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests StudioDashboardConnectionsComposer.
 */
#[CoversClass(StudioDashboardConnectionsComposer::class)]
final class StudioConnectionsBlockTest extends UnitTestCase {

  /**
   *
   */
  public function testComposeForAnonymousOrEmpty(): void {
    $container = new ContainerBuilder();
    $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
    $urlGenerator->method('generateFromRoute')->willReturn('/conta/conexoes');
    $container->set('url_generator', $urlGenerator);
    \Drupal::setContainer($container);

    $currentUser = $this->createMock(AccountInterface::class);
    $currentUser->method('isAuthenticated')->willReturn(FALSE);

    $composer = new StudioDashboardConnectionsComposer(NULL, $currentUser);
    $build = $composer->compose();

    self::assertSame('aculta_studio_connections', $build['#theme']);
    self::assertSame('CONEXÕES', $build['#title']);
    self::assertSame([], $build['#connections']);
  }

}
