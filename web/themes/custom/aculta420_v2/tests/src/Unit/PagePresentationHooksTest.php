<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta420_v2\Unit;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Extension\ExtensionList;
use Drupal\Core\Path\PathMatcherInterface;
use Drupal\Core\Routing\CurrentRouteMatch;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\aculta420_v2\Hook\PagePresentationHooks;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Verifies page-layout selection for the Studio dashboard and account.
 */
#[CoversClass(PagePresentationHooks::class)]
#[Group('aculta420_v2')]
final class PagePresentationHooksTest extends UnitTestCase {

  /**
   * Dashboard on the purpose root keeps its aside layout before front-page logic.
   */
  public function testDashboardAndAccountUseAsideLayout(): void {
    foreach ([
      'aculta_studio.dashboard' => TRUE,
      'aculta_studio.account' => FALSE,
    ] as $routeName => $isFrontPage) {
      $routeMatch = $this->createMock(CurrentRouteMatch::class);
      $routeMatch->method('getRouteName')->willReturn($routeName);
      $pathMatcher = $this->createMock(PathMatcherInterface::class);
      $pathMatcher->method('isFrontPage')->willReturn($isFrontPage);

      $hooks = new PagePresentationHooks(
        $this->createMock(ConfigFactoryInterface::class),
        $this->createMock(ExtensionList::class),
        $this->createMock(AccountProxyInterface::class),
        $routeMatch,
        $pathMatcher,
      );
      $method = new \ReflectionMethod(PagePresentationHooks::class, 'getPageLayoutVariant');

      self::assertSame('aside', $method->invoke($hooks), $routeName);
    }
  }

}
