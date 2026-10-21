<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_document_viewer\Unit\Spreadsheet;

use Drupal\aculta_document_viewer\Docx\DocxPackageValidator;
use Drupal\aculta_document_viewer\Docx\DocxValidationException;
use Drupal\aculta_document_viewer\Spreadsheet\SpreadsheetFileValidator;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Validação por formato das planilhas aceitas pelo leitor.
 */
#[CoversClass(SpreadsheetFileValidator::class)]
#[Group('aculta_document_viewer')]
final class SpreadsheetFileValidatorTest extends UnitTestCase {

  /**
   * Diretório temporário do teste.
   */
  private string $dir;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->dir = sys_get_temp_dir() . '/aculta-sheet-' . bin2hex(random_bytes(4));
    mkdir($this->dir);
  }

  /**
   * {@inheritdoc}
   */
  protected function tearDown(): void {
    array_map('unlink', glob($this->dir . '/*') ?: []);
    rmdir($this->dir);
    parent::tearDown();
  }

  /**
   * Gera um ZIP com as entradas indicadas.
   *
   * @param array<string, string> $entries
   */
  private function zip(string $name, array $entries): string {
    $path = $this->dir . '/' . $name;
    $zip = new \ZipArchive();
    $zip->open($path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
    foreach ($entries as $entry => $content) {
      $zip->addFromString($entry, $content);
    }
    $zip->close();
    return $path;
  }

  /**
   * Asserts a validation rejection with the given reason.
   */
  private function assertRejected(string $reason, string $path, string $extension): void {
    try {
      (new SpreadsheetFileValidator())->validate($path, $extension);
      $this->fail("Esperava rejeição por {$reason}.");
    }
    catch (DocxValidationException $e) {
      $this->assertSame($reason, $e->getReason());
    }
  }

  /**
   * ODS com mimetype correto e content.xml é aceito.
   */
  public function testValidOdsIsAccepted(): void {
    $path = $this->zip('planilha.ods', [
      'mimetype' => SpreadsheetFileValidator::ODS_MIME,
      'content.xml' => '<office:document-content/>',
    ]);
    (new SpreadsheetFileValidator())->validate($path, 'ods');
    $this->assertTrue(TRUE);
  }

  /**
   * ODS com macro Basic é recusado.
   */
  public function testOdsWithMacroIsRejected(): void {
    $path = $this->zip('macro.ods', [
      'mimetype' => SpreadsheetFileValidator::ODS_MIME,
      'content.xml' => '<office:document-content/>',
      'Basic/Standard/Module1.xml' => 'Sub Main',
    ]);
    $this->assertRejected('forbidden_part', $path, 'ods');
  }

  /**
   * ODS sem o tipo declarado em mimetype é recusado.
   */
  public function testOdsWithoutMimetypeIsRejected(): void {
    $path = $this->zip('sem-tipo.ods', ['content.xml' => '<x/>']);
    $this->assertRejected('wrong_content_type', $path, 'ods');
  }

  /**
   * Nomes de entrada inseguros são recusados por uma única regra para DOCX, XLSX e ODS.
   */
  public function testUnsafeEntryNamesAreRefused(): void {
    foreach (['/etc/passwd', '..\\x', 'a/..', '..', 'C:/x', 'pasta/../../x'] as $name) {
      $this->assertTrue(DocxPackageValidator::isUnsafeEntryName($name), "Esperava nome inseguro: {$name}");
    }
    foreach (['word/document.xml', 'xl/workbook.xml', 'Pictures/1.png'] as $name) {
      $this->assertFalse(DocxPackageValidator::isUnsafeEntryName($name), "Nome normal recusado: {$name}");
    }
  }

  /**
   * ODS com conteúdo altamente compressível (ZIP bomb) é recusado pela razão.
   */
  public function testOdsZipBombIsRejected(): void {
    $path = $this->zip('bomba.ods', [
      'mimetype' => SpreadsheetFileValidator::ODS_MIME,
      'content.xml' => '<x/>',
      'Pictures/vazio.bin' => str_repeat("\0", 4 * 1024 * 1024),
    ]);
    $this->assertRejected('zip_bomb', $path, 'ods');
  }

  /**
   * XLS precisa da assinatura OLE2.
   */
  public function testXlsWithoutOle2SignatureIsRejected(): void {
    $path = $this->dir . '/falso.xls';
    file_put_contents($path, str_repeat('nao e xls ', 20));
    $this->assertRejected('not_ole2', $path, 'xls');
  }

  /**
   * XLS com a assinatura OLE2 é aceito pela validação estrutural.
   */
  public function testXlsWithOle2SignatureIsAccepted(): void {
    $path = $this->dir . '/ole2.xls';
    file_put_contents($path, "\xD0\xCF\x11\xE0\xA1\xB1\x1A\xE1" . str_repeat("\0", 512));
    (new SpreadsheetFileValidator())->validate($path, 'xls');
    $this->assertTrue(TRUE);
  }

  /**
   * CSV binário é recusado; CSV de texto é aceito.
   */
  public function testCsvBinaryIsRejectedAndTextIsAccepted(): void {
    $binary = $this->dir . '/binario.csv';
    file_put_contents($binary, "a;b\0c");
    $this->assertRejected('binary_content', $binary, 'csv');

    $text = $this->dir . '/texto.csv';
    file_put_contents($text, "Nome;Valor\nÁgua;1,5\n");
    (new SpreadsheetFileValidator())->validate($text, 'csv');
    $this->assertTrue(TRUE);
  }

  /**
   * Extensão fora da lista (por exemplo, DOC) é recusada.
   */
  public function testUnsupportedExtensionIsRejected(): void {
    $path = $this->dir . '/documento.doc';
    file_put_contents($path, 'x');
    $this->assertRejected('unsupported_format', $path, 'doc');
  }

  /**
   * Arquivo vazio é recusado antes do formato.
   */
  public function testEmptyFileIsRejected(): void {
    $path = $this->dir . '/vazio.csv';
    touch($path);
    $this->assertRejected('empty', $path, 'csv');
  }

}
