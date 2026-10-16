<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_image\Unit;

use Drupal\aculta_image\Compression\ImageCompressor;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Comandos de compressão sem perda: só os binários documentados, com argumentos fixos.
 */
#[Group('aculta_image')]
class ImageCompressorTest extends UnitTestCase {

  public function testComandosPorTipo(): void {
    $this->assertSame(['jpegoptim', '--strip-all', '--all-progressive', '-q', '/a.jpg'], ImageCompressor::comando('image/jpeg', '/a.jpg'));
    $this->assertSame(['optipng', '-o2', '-quiet', '/a.png'], ImageCompressor::comando('image/png', '/a.png'));
    $this->assertSame(['gifsicle', '-O2', '--batch', '/a.gif'], ImageCompressor::comando('image/gif', '/a.gif'));
  }

  /** WebP e outros tipos não têm compressão sem perda neste sistema: não geram comando. */
  public function testTiposSemCompressao(): void {
    $this->assertNull(ImageCompressor::comando('image/webp', '/a.webp'));
    $this->assertNull(ImageCompressor::comando('application/pdf', '/a.pdf'));
  }

}
