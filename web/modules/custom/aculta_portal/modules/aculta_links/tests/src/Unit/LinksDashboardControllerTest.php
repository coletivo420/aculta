<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_links\Unit;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\aculta_links\Controller\LinksDashboardController;
use Drupal\aculta_links\Service\LinkAnalytics;
use Drupal\aculta_portal\Domain\DomainPurposeManager;
use Drupal\Tests\UnitTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * @coversDefaultClass \Drupal\aculta_links\Controller\LinksDashboardController
 * @group aculta_links
 */
final class LinksDashboardControllerTest extends UnitTestCase {

  /**
   * Ensures each status filter resets paging and preserves other filters.
   *
   * @covers ::buildStatusFilterLinks
   */
  public function testStatusFilterLinksPreserveIndependentFilters(): void {
    $request = Request::create('/painel-administrativo/links', 'GET', [
      'paginas_estado' => 'publicados',
      'itens_estado' => 'rascunhos',
      'page' => '2,4,1',
    ]);
    $request_stack = new RequestStack();
    $request_stack->push($request);
    $domain_purpose = (new \ReflectionClass(DomainPurposeManager::class))
      ->newInstanceWithoutConstructor();
    $analytics = (new \ReflectionClass(LinkAnalytics::class))
      ->newInstanceWithoutConstructor();
    $controller = new LinksDashboardController(
      $this->createMock(EntityTypeManagerInterface::class),
      $this->createMock(AccountProxyInterface::class),
      $domain_purpose,
      $analytics,
      $request_stack,
    );

    $method = new \ReflectionMethod($controller, 'buildStatusFilterLinks');
    $links = $method->invoke($controller, 'paginas_estado', 'publicados');

    self::assertSame('page', $links['publicados']['attributes']['aria-current']);
    self::assertNull($links['todos']['attributes']['aria-current']);
    self::assertSame(
      ['itens_estado' => 'rascunhos'],
      $links['todos']['url']->getOption('query'),
      'Removing this filter retains the other list filter and resets pager offsets.',
    );
    self::assertEquals(
      ['itens_estado' => 'rascunhos', 'paginas_estado' => 'rascunhos'],
      $links['rascunhos']['url']->getOption('query'),
    );
  }

}
