<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_file_uploader\Unit;

use Drupal\aculta_file_uploader\MimeTypeVerifier;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Verifica MIME pelo conteúdo, não pelo nome do arquivo.
 */
#[Group('aculta_file_uploader')]
final class MimeTypeVerifierTest extends UnitTestCase {

  /**
   * PNG 1x1 pixel, em base64.
   */
  private const PNG_1X1 = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=';

  /**
   * GIF 1x1 pixel, em base64.
   */
  private const GIF_1X1 = 'R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7';

  /**
   * Conteúdo PNG aceito como PNG.
   */
  public function testPngContentMatchesPngExtension(): void {
    $verifier = new MimeTypeVerifier();
    $this->assertTrue($verifier->matches((string) base64_decode(self::PNG_1X1), 'png'));
  }

  /**
   * Extensão mentirosa é recusada: PNG declarado como JPG.
   */
  public function testPngContentIsRejectedAsJpg(): void {
    $verifier = new MimeTypeVerifier();
    $this->assertFalse($verifier->matches((string) base64_decode(self::PNG_1X1), 'jpg'));
  }

  /**
   * GIF aceito como GIF e recusado como PNG.
   */
  public function testGifContentMatchesOnlyGif(): void {
    $verifier = new MimeTypeVerifier();
    $gif = (string) base64_decode(self::GIF_1X1);
    $this->assertTrue($verifier->matches($gif, 'gif'));
    $this->assertFalse($verifier->matches($gif, 'png'));
  }

  /**
   * Extensão fora do catálogo central é sempre recusada.
   */
  public function testPdfContentMatchesPdfExtension(): void {
    $verifier = new MimeTypeVerifier();
    $pdf = "%PDF-1.4\n1 0 obj\n<< >>\nendobj\n%%EOF\n";
    $this->assertTrue($verifier->matches($pdf, 'pdf'));
    $this->assertFalse($verifier->matches($pdf, 'png'));
  }

  /**
   * Confirma que uma extensão fora do catálogo é rejeitada.
   */
  public function testExtensionOutsideCatalogIsRejected(): void {
    $verifier = new MimeTypeVerifier();
    $this->assertFalse($verifier->matches((string) base64_decode(self::PNG_1X1), 'svg'));
  }

  /**
   * Conteúdo vazio não tem MIME detectável.
   */
  public function testEmptyContentHasNoMime(): void {
    $verifier = new MimeTypeVerifier();
    $this->assertNull($verifier->detect(''));
    $this->assertFalse($verifier->matches('', 'png'));
  }

  /**
   * Docx exige as entradas do pacote OOXML, não só o cabeçalho ZIP.
   */
  public function testDocxNeedsWordPackageEntries(): void {
    $verifier = new MimeTypeVerifier();
    $header = "PK\x03\x04" . str_repeat("\0", 26);
    $this->assertTrue($verifier->matches($header . '[Content_Types].xml' . 'word/document.xml', 'docx'));
    $this->assertFalse($verifier->matches($header . 'xl/workbook.xml', 'docx'));
  }

  /**
   * Xlsx exige a pasta de planilhas do pacote OOXML.
   */
  public function testXlsxNeedsWorkbookEntry(): void {
    $verifier = new MimeTypeVerifier();
    $header = "PK\x03\x04" . str_repeat("\0", 26);
    $this->assertTrue($verifier->matches($header . '[Content_Types].xml' . 'xl/workbook.xml', 'xlsx'));
    $this->assertFalse($verifier->matches($header . 'word/document.xml', 'xlsx'));
  }

}
