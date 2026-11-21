<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_forum\Unit;

use Drupal\aculta_forum\Service\ForumQuoteBuilder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * The quote keeps the id of the quoted comment for citation notices.
 */
#[CoversClass(ForumQuoteBuilder::class)]
#[Group('aculta_forum')]
final class ForumQuoteBuilderSourceTest extends TestCase {

  /**
   * The blockquote carries data-aculta-quote-cid when a source id is given.
   */
  public function testQuoteCarriesSourceCommentId(): void {
    $quote = (new ForumQuoteBuilder())->build('<p>Texto citado</p>', 'Ana', 42);
    self::assertNotNull($quote);
    self::assertStringContainsString('<blockquote data-aculta-quote-cid="42">', $quote);
  }

  /**
   * Without a source id the quote keeps the plain blockquote.
   */
  public function testQuoteWithoutSourceIsPlain(): void {
    $quote = (new ForumQuoteBuilder())->build('<p>Texto citado</p>', 'Ana');
    self::assertNotNull($quote);
    self::assertStringStartsWith('<blockquote><p>', $quote);
  }

}
