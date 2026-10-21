<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_links\Unit;

use Drupal\Component\Utility\Unicode;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\aculta_portal\Domain\DomainPurposeManager;
use Drupal\aculta_links\Hook\LinkPageMetatagHooks;
use Drupal\domain\DomainNegotiatorInterface;
use Drupal\Tests\UnitTestCase;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Tests metadata policy for private ACULTA Links routes.
 *
 * @group aculta_links
 * @coversDefaultClass \Drupal\aculta_links\Hook\LinkPageMetatagHooks
 */
final class LinkPageMetatagHooksTest extends UnitTestCase {

  /**
   * Core's namespaced Unicode helper truncates descriptions within the limit.
   */
  public function testCoreUnicodeTruncationApi(): void {
    $description = Unicode::truncate(str_repeat('palavra ', 50), 300, TRUE, TRUE);

    self::assertLessThanOrEqual(300, mb_strlen($description));
    self::assertStringEndsWith('…', $description);
  }

  /**
   * A private editing route removes default canonical tags and blocks indexing.
   *
   * @covers ::metatagsAlter
   */
  public function testPrivateRoutesAreNotIndexed(): void {
    $route_match = $this->createMock(RouteMatchInterface::class);
    $route_match->method('getRouteName')->willReturn('aculta_links.page_preview');
    $entity_type_manager = $this->createMock(EntityTypeManagerInterface::class);
    $config_factory = $this->createMock(ConfigFactoryInterface::class);
    $domain_manager = new DomainPurposeManager(
      $entity_type_manager,
      $this->createMock(DomainNegotiatorInterface::class),
      $config_factory,
      new RequestStack(),
    );
    $hooks = new LinkPageMetatagHooks(
      $entity_type_manager,
      $route_match,
      $domain_manager,
      $config_factory,
    );
    $tags = [
      'canonical_url' => '[current-page:url]',
      'og_url' => '[current-page:url]',
      'schema_web_page_url' => '[current-page:url]',
    ];
    $context = ['entity' => NULL];

    $hooks->metatagsAlter($tags, $context);

    self::assertSame('noindex, nofollow', $tags['robots']);
    self::assertArrayNotHasKey('canonical_url', $tags);
    self::assertArrayNotHasKey('og_url', $tags);
    self::assertArrayNotHasKey('schema_web_page_url', $tags);
  }

}
