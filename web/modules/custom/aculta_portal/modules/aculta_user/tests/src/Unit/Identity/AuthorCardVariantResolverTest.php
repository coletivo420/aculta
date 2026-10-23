<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_user\Unit\Identity;

use Drupal\aculta_user\Identity\AuthorCardVariantResolver;
use Drupal\node\NodeInterface;
use Drupal\taxonomy\TermInterface;
use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass \Drupal\aculta_user\Identity\AuthorCardVariantResolver
 * @group aculta_user
 */
final class AuthorCardVariantResolverTest extends TestCase {

  /**
   * Maps each supported discussion host to its own author-card variant.
   */
  public function testVariantsAreSeparatedByHostContext(): void {
    $resolver = new AuthorCardVariantResolver();
    foreach ([
      'aculta_forum_topic' => 'author_forum',
      'revista' => 'author_comment',
      'aculta_lms_topic' => 'author_lms',
      'wiki_entry' => 'author_wiki',
    ] as $bundle => $expected) {
      $host = $this->createMock(NodeInterface::class);
      $host->method('bundle')->willReturn($bundle);
      self::assertSame($expected, $resolver->forHost($host), $bundle);
    }
  }

  /**
   * Unknown hosts and non-Node hosts do not inherit another context's card.
   */
  public function testUnsupportedHostHasNoVariant(): void {
    $resolver = new AuthorCardVariantResolver();
    foreach (['activity', 'article', 'blog'] as $bundle) {
      $node = $this->createMock(NodeInterface::class);
      $node->method('bundle')->willReturn($bundle);
      self::assertNull($resolver->forHost($node), $bundle);
    }

    self::assertNull($resolver->forHost($this->createMock(TermInterface::class)));
  }

}
