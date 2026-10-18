<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_image\Unit;

use Drupal\aculta_image\ImageInspector;
use Drupal\aculta_image\PackagedAssetPublisher;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Image\ImageFactory;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\file\FileRepositoryInterface;
use Drupal\file\FileUsage\FileUsageInterface;

/** @coversDefaultClass \Drupal\aculta_image\PackagedAssetPublisher */
final class PackagedAssetPublisherTest extends UnitTestCase {

  /** @covers ::publish */
  public function testRejectsImageOutsidePinnedComposerPackages(): void {
    $source = tempnam(sys_get_temp_dir(), 'aculta-image-test-');
    self::assertNotFalse($source);
    file_put_contents($source, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/c9sAAAAASUVORK5CYII=', TRUE));
    try {
      self::assertFalse($this->publisher()->publish(
        $source,
        'public://h5p/h5p-core/images/test.png',
        'composer://h5p/h5p-core/images/test.png',
      ));
    }
    finally {
      unlink($source);
    }
  }

  /** @covers ::publish */
  public function testRejectsDestinationTraversal(): void {
    $source = tempnam(sys_get_temp_dir(), 'aculta-image-test-');
    self::assertNotFalse($source);
    file_put_contents($source, 'not inspected because the destination is invalid');
    try {
      self::assertFalse($this->publisher()->publish(
        $source,
        'public://h5p/../outside.png',
        'composer://h5p/h5p-core/images/test.png',
      ));
    }
    finally {
      unlink($source);
    }
  }

  /** @covers ::validatedBytes */
  public function testAcceptsPinnedH5pSvgAssetsAndRejectsActiveMarkup(): void {
    $publisher = (new \ReflectionClass(PackagedAssetPublisher::class))->newInstanceWithoutConstructor();
    $validator = new \ReflectionMethod(PackagedAssetPublisher::class, 'validatedBytes');
    $root = dirname(__DIR__, 9) . '/vendor/h5p';
    $assets = [
      ['h5p-core/images/h5p.svg', 'public://h5p/h5p-core/images/h5p.svg'],
      ['h5p-editor/styles/css/fonts/h5p-metadata-icons.svg', 'public://h5p/h5p-editor/styles/css/fonts/h5p-metadata-icons.svg'],
      ['h5p-editor/styles/css/fonts/h5p-hub.svg', 'public://h5p/h5p-editor/styles/css/fonts/h5p-hub.svg'],
      ['h5p-editor/styles/css/fonts/h5p-fullscreen-bar.svg', 'public://h5p/h5p-editor/styles/css/fonts/h5p-fullscreen-bar.svg'],
    ];
    foreach ($assets as [$path, $destination]) {
      $sanitized = $validator->invoke($publisher, file_get_contents($root . '/' . $path), $destination);
      self::assertIsString($sanitized, $path);
      self::assertStringNotContainsString('<!DOCTYPE', $sanitized);
    }
    self::assertNull($validator->invoke($publisher, '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>', 'public://h5p/h5p-core/images/unsafe.svg'));
  }

  private function publisher(): PackagedAssetPublisher {
    return new PackagedAssetPublisher(
      $this->createMock(FileRepositoryInterface::class),
      $this->createMock(FileSystemInterface::class),
      $this->createMock(Connection::class),
      $this->createMock(EntityTypeManagerInterface::class),
      $this->createMock(FileUsageInterface::class),
      new ImageInspector($this->createMock(ImageFactory::class)),
      $this->createMock(TimeInterface::class),
      $this->createMock(LoggerChannelFactoryInterface::class),
    );
  }

}
