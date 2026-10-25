<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_messenger\Unit;

use Drupal\aculta_messenger\Service\MemberInitiatedRequest;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\HttpFoundation\Request;

/**
 * Tests that only member navigation may update notice read state.
 */
#[CoversClass(MemberInitiatedRequest::class)]
#[Group('aculta_messenger')]
final class MemberInitiatedRequestTest extends UnitTestCase {

  /**
   * An ordinary navigation without prefetch metadata may mark a notice read.
   */
  public function testSameOriginNavigationIsMemberInitiated(): void {
    self::assertTrue(MemberInitiatedRequest::isMemberInitiated(Request::create('/avisos/wiki/1/abrir')));
  }

  /**
   * Cross-site requests, prefetches, and prerenders cannot update read state.
   */
  public function testAutomatedOrCrossSiteRequestsAreNotMemberInitiated(): void {
    $crossSite = Request::create('/avisos/wiki/1/abrir');
    $crossSite->headers->set('Sec-Fetch-Site', 'cross-site');
    $prefetch = Request::create('/avisos/wiki/1/abrir');
    $prefetch->headers->set('Purpose', 'prefetch');
    $prerender = Request::create('/avisos/wiki/1/abrir');
    $prerender->headers->set('Sec-Purpose', 'prerender');

    self::assertFalse(MemberInitiatedRequest::isMemberInitiated(NULL));
    self::assertFalse(MemberInitiatedRequest::isMemberInitiated($crossSite));
    self::assertFalse(MemberInitiatedRequest::isMemberInitiated($prefetch));
    self::assertFalse(MemberInitiatedRequest::isMemberInitiated($prerender));
    self::assertFalse(MemberInitiatedRequest::isPrefetchOrPrerender($crossSite));
    self::assertTrue(MemberInitiatedRequest::isPrefetchOrPrerender(NULL));
    self::assertTrue(MemberInitiatedRequest::isPrefetchOrPrerender($prefetch));
    self::assertTrue(MemberInitiatedRequest::isPrefetchOrPrerender($prerender));
  }

  /**
   * Either purpose header blocks prefetch when the other is empty.
   */
  public function testEmptySecPurposeDoesNotHidePurposePrefetch(): void {
    $request = Request::create('/mensagens/conversa/1');
    $request->headers->set('Sec-Purpose', '');
    $request->headers->set('Purpose', 'PrEfEtCh');
    $request->headers->set('Sec-Fetch-Site', 'CROSS-SITE');

    self::assertFalse(MemberInitiatedRequest::isMemberInitiated($request));
    self::assertTrue(MemberInitiatedRequest::isPrefetchOrPrerender($request));
  }

  /**
   * Embedded resource requests cannot mark a conversation read, but Fetch can.
   */
  public function testEmbeddedRequestsCannotTriggerReadState(): void {
    foreach (['iframe', 'image', 'script', 'object'] as $destination) {
      $request = Request::create('/mensagens/conversa/1');
      $request->headers->set('Sec-Fetch-Site', 'same-site');
      $request->headers->set('Sec-Fetch-Dest', $destination);
      self::assertFalse(MemberInitiatedRequest::isMemberInitiated($request), $destination);
    }

    $fetch = Request::create('/mensagens/conversa/1');
    $fetch->headers->set('Sec-Fetch-Site', 'same-origin');
    $fetch->headers->set('Sec-Fetch-Dest', 'empty');
    self::assertTrue(MemberInitiatedRequest::isMemberInitiated($fetch));
  }

}
