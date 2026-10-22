<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_activity\Unit\ContentIndex;

use Drupal\aculta_activity\ContentIndex\ActivityContentIndexProvider;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\node\NodeInterface;
use Drupal\Tests\UnitTestCase;

/**
 * Tests minimal Activity projection and access context.
 *
 * @group aculta_activity
 */
final class ActivityContentIndexProviderTest extends UnitTestCase {

  /**
   * Tests that only current Activity metadata is projected.
   */
  public function testProjectsOnlyCurrentActivityMetadata(): void {
    $language = $this->createMock(LanguageInterface::class);
    $language->method('getId')->willReturn('pt-br');
    $node = $this->createMock(NodeInterface::class);
    $node->method('bundle')->willReturn('activity');
    $node->method('isDefaultRevision')->willReturn(TRUE);
    $node->method('id')->willReturn('14');
    $node->method('language')->willReturn($language);
    $node->method('uuid')->willReturn('activity-uuid');
    $node->method('label')->willReturn('Evento');
    $node->method('isPublished')->willReturn(TRUE);
    $node->method('getCreatedTime')->willReturn(10);
    $node->method('getChangedTime')->willReturn(20);
    $node->method('hasField')->willReturn(FALSE);

    $projection = (new ActivityContentIndexProvider())->project($node);

    self::assertNotNull($projection);
    self::assertSame('activity', $projection->bundle);
    self::assertSame('pt-br', $projection->langcode);
    self::assertSame([], $projection->relations);
  }

  /**
   * Tests that unsupported bundles and historical revisions are ignored.
   */
  public function testRejectsOtherBundlesAndNonDefaultRevisions(): void {
    $provider = new ActivityContentIndexProvider();
    $other = $this->createMock(NodeInterface::class);
    $other->method('bundle')->willReturn('page');
    $other->method('isDefaultRevision')->willReturn(TRUE);
    self::assertNull($provider->project($other));

    $revision = $this->createMock(NodeInterface::class);
    $revision->method('bundle')->willReturn('activity');
    $revision->method('isDefaultRevision')->willReturn(FALSE);
    self::assertNull($provider->project($revision));
  }

  /**
   * Tests that access checks use the main purpose.
   */
  public function testUsesMainPurposeForActivityAccess(): void {
    $provider = new ActivityContentIndexProvider();
    $node = $this->createMock(NodeInterface::class);
    $node->method('bundle')->willReturn('activity');

    self::assertSame('main', $provider->getAccessPurpose($node));
    self::assertNull($provider->getAccessPurpose($this->createMock(ContentEntityInterface::class)));
  }

}
