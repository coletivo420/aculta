<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_index_file\Unit;

use Drupal\aculta_index_file\ContentIndex\FileContentIndexProvider;
use Drupal\Core\Language\LanguageInterface;
use Drupal\file\FileInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Verifies the minimal File provider used by the Statistics bridge.
 *
 * @group aculta_index
 */
#[Group('aculta_index')]
final class ContentIndexFileProviderTest extends UnitTestCase {

  /**
   * A permanent File projects identity, dates and owner only.
   */
  public function testPermanentFileProjectsOnlyIdentityAndOwner(): void {
    $language = $this->createMock(LanguageInterface::class);
    $language->method('getId')->willReturn('en');
    $file = $this->createMock(FileInterface::class);
    $file->method('id')->willReturn('7');
    $file->method('uuid')->willReturn('file-uuid');
    $file->method('bundle')->willReturn('file');
    $file->method('language')->willReturn($language);
    $file->method('isPermanent')->willReturn(TRUE);
    $file->method('getCreatedTime')->willReturn(100);
    $file->method('getChangedTime')->willReturn(200);
    $file->method('getOwnerId')->willReturn(3);
    // No filename, URI, MIME type or size may enter the catalog.
    $file->expects(self::never())->method('getFilename');
    $file->expects(self::never())->method('getFileUri');
    $file->expects(self::never())->method('getMimeType');
    $file->expects(self::never())->method('getSize');

    $provider = new FileContentIndexProvider();
    self::assertSame('file', $provider->getProviderId());
    self::assertSame(1, $provider->getProjectionVersion());
    self::assertSame(['file' => ['file']], $provider->getSupportedEntityTypes());
    self::assertTrue($provider->isIndexable($file));

    $projection = $provider->project($file);
    self::assertNotNull($projection);
    self::assertSame('file', $projection->entityTypeId);
    self::assertSame('7', $projection->entityId);
    self::assertSame('en', $projection->langcode);
    self::assertSame('file', $projection->bundle);
    self::assertSame('file-uuid', $projection->uuid);
    self::assertSame('', $projection->label);
    self::assertNull($projection->domainId);
    self::assertTrue($projection->published);
    self::assertSame(100, $projection->created);
    self::assertSame(200, $projection->changed);
    self::assertNull($projection->publishedAt);
    self::assertSame([], $projection->relations);
    self::assertSame(3, $projection->ownerUid);
  }

  /**
   * Temporary files are outside the metric's scope and are not indexed.
   */
  public function testTemporaryFileIsNotIndexable(): void {
    $file = $this->createMock(FileInterface::class);
    $file->method('isPermanent')->willReturn(FALSE);

    $provider = new FileContentIndexProvider();
    self::assertFalse($provider->isIndexable($file));
    self::assertNull($provider->project($file));
  }

}
