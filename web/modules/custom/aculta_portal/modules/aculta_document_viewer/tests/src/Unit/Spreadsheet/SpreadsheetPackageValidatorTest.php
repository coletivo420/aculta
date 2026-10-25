<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_document_viewer\Unit\Spreadsheet;

use Drupal\aculta_document_viewer\Docx\DocxPackageValidator;
use Drupal\aculta_document_viewer\Docx\DocxValidationException;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Valida o pacote XLSX pelo validador OOXML parametrizado.
 */
#[CoversClass(DocxPackageValidator::class)]
#[Group('aculta_document_viewer')]
final class SpreadsheetPackageValidatorTest extends UnitTestCase {

  /**
   * Diretório temporário do teste.
   */
  private string $dir;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->dir = sys_get_temp_dir() . '/aculta-xlsx-' . bin2hex(random_bytes(4));
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
  private function package(array $entries): string {
    $path = $this->dir . '/pacote.xlsx';
    $zip = new \ZipArchive();
    $zip->open($path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
    foreach ($entries as $name => $content) {
      $zip->addFromString($name, $content);
    }
    $zip->close();
    return $path;
  }

  /**
   * Um XLSX com workbook e tipo principal SpreadsheetML é aceito.
   */
  public function testValidWorkbookIsAccepted(): void {
    $path = $this->package([
      '[Content_Types].xml' => '<Types><Override ContentType="' . DocxPackageValidator::XLSX_MAIN_CONTENT_TYPE . '"/></Types>',
      'xl/workbook.xml' => '<workbook/>',
    ]);
    DocxPackageValidator::forXlsx()->validate($path);
    $this->assertTrue(TRUE);
  }

  /**
   * Um DOCX não é aceito como planilha.
   */
  public function testWordPackageIsRejectedAsWorkbook(): void {
    $path = $this->package([
      '[Content_Types].xml' => '<Types><Override ContentType="' . DocxPackageValidator::MAIN_CONTENT_TYPE . '"/></Types>',
      'word/document.xml' => '<w:document/>',
    ]);
    try {
      DocxPackageValidator::forXlsx()->validate($path);
      $this->fail('Esperava rejeição do DOCX como planilha.');
    }
    catch (DocxValidationException $e) {
      $this->assertSame('missing_part', $e->getReason());
    }
  }

  /**
   * Macros VBA em planilha são recusadas.
   */
  public function testVbaInWorkbookIsRejected(): void {
    $path = $this->package([
      '[Content_Types].xml' => '<Types><Override ContentType="' . DocxPackageValidator::XLSX_MAIN_CONTENT_TYPE . '"/></Types>',
      'xl/workbook.xml' => '<workbook/>',
      'xl/vbaProject.bin' => 'macro',
    ]);
    try {
      DocxPackageValidator::forXlsx()->validate($path);
      $this->fail('Esperava rejeição por macro.');
    }
    catch (DocxValidationException $e) {
      $this->assertSame('forbidden_part', $e->getReason());
    }
  }

}
