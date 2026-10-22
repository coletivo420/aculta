<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_gallery\Unit\ContentIndex;

use Drupal\aculta_portal\ContentIndex\ContentIndexProjection;
use Drupal\aculta_gallery\ContentIndex\GalleryContentIndexProvider;
use Drupal\Core\Field\EntityReferenceFieldItemListInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\media\MediaInterface;
use Drupal\media_gallery\MediaGalleryInterface;
use Drupal\Tests\UnitTestCase;

/**
 * @coversDefaultClass \Drupal\aculta_gallery\ContentIndex\GalleryContentIndexProvider
 * @group aculta_gallery
 */
final class GalleryContentIndexProviderTest extends UnitTestCase {

  /**
   * Tests minimal gallery metadata and image-only outgoing relations.
   *
   * @covers ::project
   */
  public function testProjectsGalleryAndExplicitImageRelations(): void {
    $language = $this->createMock(LanguageInterface::class);
    $language->method('getId')->willReturn('pt-br');
    $image = $this->createMock(MediaInterface::class);
    $image->method('bundle')->willReturn('image');
    $image->method('id')->willReturn('31');

    $images = $this->createMock(EntityReferenceFieldItemListInterface::class);
    $images->method('isEmpty')->willReturn(FALSE);
    $images->method('referencedEntities')->willReturn([$image]);

    $gallery = $this->createMock(MediaGalleryInterface::class);
    $gallery->method('getEntityTypeId')->willReturn('media_gallery');
    $gallery->method('bundle')->willReturn('media_gallery');
    $gallery->method('id')->willReturn('12');
    $gallery->method('language')->willReturn($language);
    $gallery->method('uuid')->willReturn('gallery-uuid');
    $gallery->method('getTitle')->willReturn('Festival');
    $gallery->method('isEnabled')->willReturn(TRUE);
    $gallery->method('getCreatedTime')->willReturn(100);
    $gallery->method('getChangedTime')->willReturn(200);
    $gallery->method('hasField')->willReturnCallback(static fn (string $name): bool => $name === 'images');
    $gallery->method('get')->with('images')->willReturn($images);

    $projection = (new GalleryContentIndexProvider())->project($gallery);

    self::assertInstanceOf(ContentIndexProjection::class, $projection);
    self::assertSame('media_gallery', $projection->entityTypeId);
    self::assertSame('12', $projection->entityId);
    self::assertSame('pt-br', $projection->langcode);
    self::assertSame('Festival', $projection->label);
    self::assertTrue($projection->published);
    self::assertSame('gallery_image', $projection->relations[0]->type);
    self::assertSame('media', $projection->relations[0]->targetEntityTypeId);
    self::assertSame('31', $projection->relations[0]->targetEntityId);
    self::assertSame('und', $projection->relations[0]->targetLangcode);
  }

  /**
   * Tests Entity Access is revalidated under the canonical Magazine purpose.
   *
   * @covers ::getAccessPurpose
   */
  public function testGalleryAccessPurposeIsMagazine(): void {
    $gallery = $this->createMock(MediaGalleryInterface::class);
    $gallery->method('bundle')->willReturn('media_gallery');

    self::assertSame('magazine', (new GalleryContentIndexProvider())->getAccessPurpose($gallery));
  }

}
