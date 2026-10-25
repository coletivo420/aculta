<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_document_viewer\Unit\Docx;

use Drupal\aculta_document_viewer\Docx\DocxPackageValidator;
use Drupal\aculta_document_viewer\Docx\DocxValidationException;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Cobre a validação estrutural do DOCX com pacotes gerados no teste.
 */
#[CoversClass(DocxPackageValidator::class)]
#[Group('aculta_document_viewer')]
final class DocxPackageValidatorTest extends UnitTestCase {

  /**
   * Diretório temporário do teste.
   */
  private string $dir;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->dir = sys_get_temp_dir() . '/aculta-docx-' . bin2hex(random_bytes(4));
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
   * Gera um pacote ZIP com as entradas indicadas.
   *
   * @param array<string, string> $entries
   *   Nome da entrada => conteúdo.
   */
  private function package(array $entries, string $name = 'pacote.docx'): string {
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
   * Conteúdo mínimo de um DOCX válido.
   *
   * @return array<string, string>
   */
  private function validEntries(): array {
    return [
      '[Content_Types].xml' => '<Types><Override PartName="/word/document.xml" ContentType="' . DocxPackageValidator::MAIN_CONTENT_TYPE . '"/></Types>',
      'word/document.xml' => '<w:document/>',
    ];
  }

  /**
   * Asserts that validation rejects the path with the given reason.
   */
  private function assertRejected(string $reason, string $path, ?DocxPackageValidator $validator = NULL): void {
    try {
      ($validator ?? new DocxPackageValidator())->validate($path);
      $this->fail("Esperava rejeição por {$reason}.");
    }
    catch (DocxValidationException $e) {
      $this->assertSame($reason, $e->getReason());
    }
  }

  /**
   * Um DOCX com as partes e o tipo principal é aceito.
   */
  public function testValidPackageIsAccepted(): void {
    (new DocxPackageValidator())->validate($this->package($this->validEntries()));
    $this->assertTrue(TRUE);
  }

  /**
   * Arquivo que não começa com a assinatura ZIP é rejeitado.
   */
  public function testNonZipIsRejected(): void {
    $path = $this->dir . '/texto.docx';
    file_put_contents($path, str_repeat('texto simples ', 20));
    $this->assertRejected('not_zip', $path);
  }

  /**
   * Assinatura ZIP com conteúdo corrompido é rejeitada.
   */
  public function testCorruptZipIsRejected(): void {
    $path = $this->dir . '/corrompido.docx';
    file_put_contents($path, "PK\x03\x04" . str_repeat("\x00\xff", 64));
    $this->assertRejected('corrupt_zip', $path);
  }

  /**
   * Caminho ausente gera erro de leitura.
   */
  public function testMissingFileIsRejected(): void {
    $this->assertRejected('unreadable', $this->dir . '/nao-existe.docx');
  }

  /**
   * Sem word/document.xml o pacote não é um DOCX.
   */
  public function testMissingDocumentPartIsRejected(): void {
    $entries = $this->validEntries();
    unset($entries['word/document.xml']);
    $this->assertRejected('missing_part', $this->package($entries));
  }

  /**
   * Tipo principal não declarado é rejeitado (por exemplo, planilha ou slides).
   */
  public function testWrongMainContentTypeIsRejected(): void {
    $entries = $this->validEntries();
    $entries['[Content_Types].xml'] = '<Types><Override ContentType="application/vnd.ms-excel"/></Types>';
    $this->assertRejected('wrong_content_type', $this->package($entries));
  }

  /**
   * Macros VBA não são aceitas.
   */
  public function testVbaProjectIsRejected(): void {
    $entries = $this->validEntries();
    $entries['word/vbaProject.bin'] = 'macro';
    $this->assertRejected('forbidden_part', $this->package($entries));
  }

  /**
   * Partes HTML (altChunk) não são aceitas no pacote.
   */
  public function testHtmlPartIsRejected(): void {
    $entries = $this->validEntries();
    $entries['word/embedded.html'] = '<script>alert(1)</script>';
    $this->assertRejected('forbidden_part', $this->package($entries));
  }

  /**
   * Número de entradas acima do limite é rejeitado.
   */
  public function testTooManyEntriesIsRejected(): void {
    $validator = new DocxPackageValidator(maxEntries: 2);
    $entries = $this->validEntries() + ['word/styles.xml' => '<styles/>'];
    $this->assertRejected('too_many_entries', $this->package($entries), $validator);
  }

  /**
   * Conteúdo altamente compressível (ZIP bomb) é rejeitado pela razão.
   */
  public function testZipBombRatioIsRejected(): void {
    $entries = $this->validEntries();
    $entries['word/media/vazio.bin'] = str_repeat("\0", 4 * 1024 * 1024);
    $this->assertRejected('zip_bomb', $this->package($entries));
  }

  /**
   * Tamanho do arquivo acima do limite é rejeitado antes de abrir o ZIP.
   */
  public function testFileOverByteLimitIsRejected(): void {
    $path = $this->package($this->validEntries());
    $this->assertRejected('too_large', $path, new DocxPackageValidator(maxBytes: 10));
  }

  /**
   * Arquivo vazio é rejeitado.
   */
  public function testEmptyFileIsRejected(): void {
    $path = $this->dir . '/vazio.docx';
    touch($path);
    $this->assertRejected('empty', $path);
  }

}
