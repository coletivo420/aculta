<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_forum\Unit;

use Drupal\aculta_forum\Service\ReactionReturnUrl;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\HttpFoundation\Request;

/**
 * Tests non-JS POST return URLs cannot redirect outside the current site.
 */
#[Group('aculta_forum')]
final class ReactionReturnUrlTest extends UnitTestCase {

  /**
   * The explicit in-site destination wins over Referer.
   */
  public function testExplicitLocalDestination(): void {
    $request = Request::create('https://forum.example.org/reacoes');
    self::assertSame('/discussoes/topic?sort=latest#post-2', ReactionReturnUrl::resolve(
      '/discussoes/topic?sort=latest#post-2',
      'https://forum.example.org/outro',
      $request,
    ));
  }

  /**
   * Same-origin absolute referer is converted into a local path.
   */
  public function testSameOriginReferer(): void {
    $request = Request::create('https://forum.example.org/reacoes');
    self::assertSame('/discussoes/tema?l=3#reply', ReactionReturnUrl::resolve(
      NULL,
      'https://forum.example.org/discussoes/tema?l=3#reply',
      $request,
    ));
  }

  /**
   * Unsafe values are rejected, falling back to same-origin or front.
   */
  public function testMaliciousAndCrossDomainDestinationsAreRejected(): void {
    $request = Request::create('https://forum.example.org/reacoes');
    foreach ([
      '//evil.example/hijack',
      '/\\evil.example/hijack',
      'https://evil.example/other',
      'http://forum.example.org/downgrade',
      'javascript:alert(1)',
      "https://forum.example.org/other\r\nLocation: https://evil.example",
      'https://forum.example.org:444/other',
      'https://user:pass@forum.example.org/other',
      'relative/path',
    ] as $candidate) {
      self::assertSame('/', ReactionReturnUrl::resolve($candidate, NULL, $request));
      self::assertSame('/discussoes/seguro', ReactionReturnUrl::resolve(
        $candidate,
        'https://forum.example.org/discussoes/seguro',
        $request,
      ));
    }
  }

}
