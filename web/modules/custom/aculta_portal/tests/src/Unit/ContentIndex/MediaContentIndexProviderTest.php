<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_portal\Unit\ContentIndex;

use Drupal\aculta_portal\ContentIndex\MediaContentIndexProvider;
use Drupal\Core\Language\LanguageInterface;
use Drupal\media\MediaInterface;
use Drupal\Tests\UnitTestCase;

/**
 * @coversDefaultClass \Drupal\aculta_portal\ContentIndex\MediaContentIndexProvider
 * @group aculta_portal
 */
final class MediaContentIndexProviderTest extends UnitTestCase {

  /**
   * Tests supported bundle, minimal projection and purpose declaration.
   *
   * @covers ::__construct
   * @covers ::getProviderId
   * @covers ::getSupportedEntityTypes
   * @covers ::getAccessPurpose
   * @covers ::project
   */
  public function testProjectsOneConfiguredMediaBundle(): void {
    $language = $this->createMock(LanguageInterface::class);
    $language->method('getId')->willReturn('pt-br');
    $media = $this->createMock(MediaInterface::class);
    $media->method('bundle')->willReturn('course_document');
    $media->method('id')->willReturn('52');
    $media->method('language')->willReturn($language);
    $media->method('uuid')->willReturn('course-document-uuid');
    $media->method('getName')->willReturn('Apostila');
    $media->method('isPublished')->willReturn(FALSE);
    $media->method('getCreatedTime')->willReturn(100);
    $media->method('getChangedTime')->willReturn(200);
    $provider = new MediaContentIndexProvider('course_document', 'course_document', 'courses');

    $projection = $provider->project($media);

    self::assertSame('course_document', $provider->getProviderId());
    self::assertSame(['media' => ['course_document']], $provider->getSupportedEntityTypes());
    self::assertSame('courses', $provider->getAccessPurpose($media));
    self::assertSame('52', $projection?->entityId);
    self::assertSame('Apostila', $projection?->label);
    self::assertFalse($projection?->published);
    self::assertSame([], $projection?->relations);
  }

  /**
   * Tests invalid configuration is rejected at container construction.
   *
   * @covers ::__construct
   */
  public function testRejectsInvalidMachineNames(): void {
    $this->expectException(\InvalidArgumentException::class);
    new MediaContentIndexProvider('Course Document', 'course_document');
  }

}
