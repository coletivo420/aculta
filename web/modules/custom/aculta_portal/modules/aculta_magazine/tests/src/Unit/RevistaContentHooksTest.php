<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_magazine\Unit;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\node\NodeInterface;
use Drupal\aculta_magazine\Hook\RevistaContentHooks;
use Drupal\Tests\UnitTestCase;

/**
 * Tests field and metatag lifecycle behavior for the canonical Revista bundle.
 *
 * @group aculta_magazine
 */
final class RevistaContentHooksTest extends UnitTestCase {

  /**
   * Tests the first published timestamp is recorded for Revista only.
   */
  public function testRecordsFirstPublicationTimestamp(): void {
    $field = $this->createMock(FieldItemListInterface::class);
    $field->method('isEmpty')->willReturn(TRUE);
    $node = $this->createMock(NodeInterface::class);
    $node->method('bundle')->willReturn('revista');
    $node->method('isPublished')->willReturn(TRUE);
    $node->method('hasField')->with('field_published_at')->willReturn(TRUE);
    $node->method('get')->with('field_published_at')->willReturn($field);
    $node->expects(self::once())
      ->method('set')
      ->with('field_published_at', self::matchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}$/'))
      ->willReturnSelf();

    (new RevistaContentHooks())->nodePresave($node);
  }

  /**
   * Tests an optional empty image is omitted from Revista JSON-LD.
   */
  public function testRemovesEmptyImageMetadata(): void {
    $field = $this->createMock(FieldItemListInterface::class);
    $field->method('isEmpty')->willReturn(TRUE);
    $node = $this->createMock(NodeInterface::class);
    $node->method('bundle')->willReturn('revista');
    $node->method('hasField')->with('field_media_image')->willReturn(TRUE);
    $node->method('get')->with('field_media_image')->willReturn($field);
    $tags = [
      'schema_article_image' => '[node:image]',
      'schema_article_headline' => '[node:title]',
    ];
    $context = ['entity' => $node];

    (new RevistaContentHooks())->metatagsAlter($tags, $context);

    self::assertArrayNotHasKey('schema_article_image', $tags);
    self::assertSame('[node:title]', $tags['schema_article_headline']);
  }

}
