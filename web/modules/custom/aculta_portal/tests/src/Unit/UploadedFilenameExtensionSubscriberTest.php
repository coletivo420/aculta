<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_portal\Unit;

use Drupal\aculta_portal\EventSubscriber\UploadedFilenameExtensionSubscriber;
use Drupal\Core\File\Event\FileUploadSanitizeNameEvent;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;

#[Group('aculta_portal')]
final class UploadedFilenameExtensionSubscriberTest extends UnitTestCase {

  public function testLowercasesOnlyFinalExtension(): void {
    $subscriber = new UploadedFilenameExtensionSubscriber();
    $event = new FileUploadSanitizeNameEvent('relatorio-final.JPG', 'jpg');

    $subscriber->lowercaseExtension($event);

    $this->assertSame('relatorio-final.jpg', $event->getFilename());
  }

  public function testLeavesBasenameAndExtensionlessNamesUnchanged(): void {
    $subscriber = new UploadedFilenameExtensionSubscriber();
    $event = new FileUploadSanitizeNameEvent('Capa.JPG', 'jpg');
    $subscriber->lowercaseExtension($event);
    $this->assertSame('Capa.jpg', $event->getFilename());

    $event = new FileUploadSanitizeNameEvent('sem-extensao', '');
    $subscriber->lowercaseExtension($event);
    $this->assertSame('sem-extensao', $event->getFilename());
  }

}
