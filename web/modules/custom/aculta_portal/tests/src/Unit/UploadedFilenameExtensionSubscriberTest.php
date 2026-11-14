<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_portal\Unit;

use Drupal\aculta_portal\EventSubscriber\UploadedFilenameExtensionSubscriber;
use Drupal\Core\File\Event\FileUploadSanitizeNameEvent;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Testa a normalização da extensão de nomes de arquivo enviados.
 */
#[Group('aculta_portal')]
final class UploadedFilenameExtensionSubscriberTest extends UnitTestCase {

  /**
   * Confirma que apenas a extensão final é convertida para minúsculas.
   */
  public function testLowercasesOnlyFinalExtension(): void {
    $subscriber = new UploadedFilenameExtensionSubscriber();
    $event = new FileUploadSanitizeNameEvent('relatorio-final.JPG', 'jpg');

    $subscriber->lowercaseExtension($event);

    $this->assertSame('relatorio-final.jpg', $event->getFilename());
  }

  /**
   * Confirma que o nome base e os nomes sem extensão ficam inalterados.
   */
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
