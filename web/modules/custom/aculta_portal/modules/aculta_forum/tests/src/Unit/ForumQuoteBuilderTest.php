<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_forum\Unit;

use Drupal\aculta_forum\Service\ForumQuoteBuilder;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests safe text extraction for replies quoting public comments.
 */
#[Group('aculta_forum')]
final class ForumQuoteBuilderTest extends UnitTestCase {

  /**
   * Ensures source markup and author data are emitted as escaped text.
   */
  public function testBuildsEscapedAttributedBlockquote(): void {
    $quote = (new ForumQuoteBuilder())->build(
      '<p>Texto <strong>permitido</strong> &amp; leitura.</p><p>Segunda linha.</p>',
      'Nome <script>alert(1)</script>',
    );

    self::assertSame(
      '<blockquote><p>Texto permitido &amp; leitura.</p><p>Segunda linha.</p><cite>Nome &lt;script&gt;alert(1)&lt;/script&gt;</cite></blockquote><p></p>',
      $quote,
    );
    self::assertStringNotContainsString('<script>', $quote);
  }

  /**
   * Empty filtered comments do not create an empty quote container.
   */
  public function testReturnsNullForEmptyContent(): void {
    self::assertNull((new ForumQuoteBuilder())->build('<p><br></p>', 'Participante'));
  }

  /**
   * Long comments are clipped before being inserted into the reply form.
   */
  public function testQuoteLengthIsBounded(): void {
    $quote = (new ForumQuoteBuilder())->build('<p>' . str_repeat('a', 1400) . '</p>', 'Participante');

    self::assertNotNull($quote);
    self::assertSame(1200, mb_strlen(strip_tags($quote)) - mb_strlen('Participante'));
  }

}
