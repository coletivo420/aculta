<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_qrcode\Unit;

use Drupal\aculta_qrcode\Service\QrCodeDataUri;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * URI de imagem SVG usado pelo formatter de campo.
 */
#[CoversClass(QrCodeDataUri::class)]
#[Group('aculta_qrcode')]
class QrCodeDataUriTest extends UnitTestCase {

  /**
   * O URI traz o tipo SVG e o conteúdo codificado em base64, sem markup solto.
   */
  public function testSvgIsEncodedAsBase64ImageUri(): void {
    $svg = '<svg xmlns="http://www.w3.org/2000/svg"></svg>';
    $uri = QrCodeDataUri::svg($svg);
    $this->assertStringStartsWith('data:image/svg+xml;base64,', $uri);
    $this->assertSame($svg, base64_decode(substr($uri, strlen('data:image/svg+xml;base64,')), TRUE));
    $this->assertStringNotContainsString('<svg', $uri);
  }

}
