<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_image\Unit;

use Drupal\aculta_image\ImageIngester;
use Drupal\aculta_image\UploadPolicy;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Regras de entrada da ingestão: extensão e tamanho pela política única.
 */
#[Group('aculta_image')]
class ImageIngesterTest extends UnitTestCase {

  /** Extensões da política são aceitas, sem diferença de caixa; outras são recusadas. */
  public function testExtensoesDaPolitica(): void {
    $this->assertTrue(ImageIngester::extensaoAceita('foto.JPG'));
    $this->assertTrue(ImageIngester::extensaoAceita('capa.webp'));
    $this->assertFalse(ImageIngester::extensaoAceita('documento.pdf'));
    $this->assertFalse(ImageIngester::extensaoAceita('semextensao'));
  }

  /** Tamanho: zero e acima do limite são recusados; o limite exato é aceito. */
  public function testTamanhoDaPolitica(): void {
    $this->assertFalse(ImageIngester::tamanhoAceito(0));
    $this->assertTrue(ImageIngester::tamanhoAceito(1));
    $this->assertTrue(ImageIngester::tamanhoAceito(UploadPolicy::MAX_BYTES));
    $this->assertFalse(ImageIngester::tamanhoAceito(UploadPolicy::MAX_BYTES + 1));
  }

}
