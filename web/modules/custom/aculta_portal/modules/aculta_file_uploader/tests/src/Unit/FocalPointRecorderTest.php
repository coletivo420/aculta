<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_file_uploader\Unit;

use Drupal\aculta_file_uploader\FocalPointRecorder;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Image\Image;
use Drupal\Core\Image\ImageFactory;
use Drupal\file\FileInterface;
use Drupal\image_widget_crop\ImageWidgetCropInterface;
use Drupal\Tests\UnitTestCase;

/**
 * Covers the fail-closed paths of FocalPointRecorder.
 *
 * @group aculta_file_uploader
 */
final class FocalPointRecorderTest extends UnitTestCase {

  public function testReturnsFalseForEveryTypeWhenCropTypeIsMissing(): void {
    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('load')->willReturn(NULL);
    $entityTypes = $this->createMock(EntityTypeManagerInterface::class);
    $entityTypes->method('getStorage')->with('crop_type')->willReturn($storage);
    $cropManager = $this->createMock(ImageWidgetCropInterface::class);
    $cropManager->expects($this->never())->method('applyCrop');
    $cropManager->expects($this->never())->method('updateCrop');

    $file = $this->createMock(FileInterface::class);
    $file->method('getFileUri')->willReturn('public://foto.jpg');
    $imageFactory = $this->createMock(ImageFactory::class);
    $imageFactory->method('get')->willReturn($this->createMock(Image::class));

    $recorder = new FocalPointRecorder($cropManager, $entityTypes, $imageFactory);
    $result = $recorder->record($file, 50.0, 50.0);

    self::assertSame(['aculta_ponto_focal_16x9' => FALSE, 'aculta_ponto_focal_1x1' => FALSE], $result);
  }

}
