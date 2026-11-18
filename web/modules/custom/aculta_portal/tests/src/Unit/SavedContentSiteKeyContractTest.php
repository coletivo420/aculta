<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_portal\Unit;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\aculta_portal\Saved\SavedContentFilter;
use Drupal\aculta_portal\Saved\SavedContentItem;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Group;

/**
 * Protects the canonical site-key contract for saved content.
 */
#[Group('aculta_portal')]
#[CoversMethod(SavedContentFilter::class, 'getSiteKey')]
#[CoversMethod(SavedContentItem::class, 'getSiteKey')]
final class SavedContentSiteKeyContractTest extends UnitTestCase {

  /**
   * Confirms the filter exposes the canonical key.
   */
  public function testFilterExposesSiteKey(): void {
    $filter = new SavedContentFilter(
      account: $this->createMock(AccountInterface::class),
      siteKey: 'wiki',
    );

    self::assertSame('wiki', $filter->getSiteKey());
  }

  /**
   * Confirms the item exposes the canonical key.
   */
  public function testItemExposesSiteKey(): void {
    $item = new SavedContentItem(
      entityTypeId: 'node',
      entityId: 42,
      entity: $this->createMock(EntityInterface::class),
      flagId: 'bookmark',
      createdTime: 1,
      category: 'saved',
      siteKey: 'wiki',
    );

    self::assertSame('wiki', $item->getSiteKey());
  }

}
