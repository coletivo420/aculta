<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_portal\Unit;

use Drupal\aculta_portal\EventSubscriber\DomainPurposeRequestSubscriber;
use Drupal\aculta_portal\PathProcessor\PurposeFrontPathProcessor;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Ensures cached route matching cannot lose purpose root request markers.
 */
#[Group('aculta_portal')]
final class PurposeFrontPathProcessorTest extends UnitTestCase {

  /**
   * Tests that configured purpose roots receive both request markers.
   */
  #[DataProvider('mappedPurposes')]
  public function testMappedRootGetsNormalizerAndFrontMarkers(string $purpose): void {
    $request = Request::create('/');

    PurposeFrontPathProcessor::markRootRequest($request, $purpose);

    $this->assertTrue($request->attributes->get('_disable_route_normalizer'));
    $this->assertTrue($request->attributes->get(PurposeFrontPathProcessor::FRONT_ATTRIBUTE));
  }

  /**
   * Provides every purpose that serves an internal route at its root.
   */
  public static function mappedPurposes(): array {
    return array_map(
      static fn(string $purpose): array => [$purpose],
      array_keys(PurposeFrontPathProcessor::FRONT_PATHS),
    );
  }

  /**
   * Tests that unrelated paths and the unrewritten main purpose are untouched.
   */
  public function testNonRootAndUnmappedPurposeAreNotMarked(): void {
    $nonRoot = Request::create('/discussoes');
    PurposeFrontPathProcessor::markRootRequest($nonRoot, 'forum');
    $this->assertFalse($nonRoot->attributes->has('_disable_route_normalizer'));
    $this->assertFalse($nonRoot->attributes->has(PurposeFrontPathProcessor::FRONT_ATTRIBUTE));

    $unmapped = Request::create('/');
    PurposeFrontPathProcessor::markRootRequest($unmapped, 'main');
    $this->assertFalse($unmapped->attributes->has('_disable_route_normalizer'));
    $this->assertFalse($unmapped->attributes->has(PurposeFrontPathProcessor::FRONT_ATTRIBUTE));

    $unknown = Request::create('/');
    PurposeFrontPathProcessor::markRootRequest($unknown, NULL);
    $this->assertFalse($unknown->attributes->has('_disable_route_normalizer'));
    $this->assertFalse($unknown->attributes->has(PurposeFrontPathProcessor::FRONT_ATTRIBUTE));
  }

  /**
   * Tests the request subscriber's priority relative to Redirect.
   */
  public function testSubscriberRunsBeforeRedirectNormalizer(): void {
    $events = DomainPurposeRequestSubscriber::getSubscribedEvents();

    $this->assertSame('onRequestDisableFrontNormalizer', $events[KernelEvents::REQUEST][0][0]);
    $this->assertGreaterThan(30, $events[KernelEvents::REQUEST][0][1]);
  }

}
