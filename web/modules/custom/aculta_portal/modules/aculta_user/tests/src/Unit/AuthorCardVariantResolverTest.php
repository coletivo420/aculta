<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_user\Unit;

use Drupal\aculta_user\Identity\AuthorCardVariantResolver;
use Drupal\node\NodeInterface;
use Drupal\Tests\UnitTestCase;

/**
 * Tests that discussion host presentation variants preserve host boundaries.
 *
 * @group aculta_user
 * @coversDefaultClass \Drupal\aculta_user\Identity\AuthorCardVariantResolver
 */
final class AuthorCardVariantResolverTest extends UnitTestCase {

  /**
   * @covers ::forHost
   */
  public function testOnlyForumHostsSelectTheForumAuthorVariant(): void {
    $resolver = new AuthorCardVariantResolver();
    $variants = [
      'aculta_forum_topic' => 'author_forum',
      'revista' => 'author_comment',
      'aculta_lms_topic' => 'author_lms',
      'wiki_entry' => 'author_wiki',
      'unknown' => NULL,
    ];

    foreach ($variants as $bundle => $expected) {
      $host = $this->createMock(NodeInterface::class);
      $host->method('bundle')->willReturn($bundle);
      self::assertSame($expected, $resolver->forHost($host), $bundle);
    }
  }

}
