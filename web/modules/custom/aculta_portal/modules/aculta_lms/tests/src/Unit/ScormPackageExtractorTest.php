<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_lms\Unit;

use Drupal\aculta_lms\Scorm\ScormPackageExtractor;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Covers extraction into a private subdirectory and cleanup on refusal.
 */
#[CoversClass(ScormPackageExtractor::class)]
final class ScormPackageExtractorTest extends UnitTestCase {

  /**
   * Diretórios pai criados pelo teste.
   *
   * @var string[]
   */
  private array $directories = [];

  /**
   * {@inheritdoc}
   */
  protected function tearDown(): void {
    foreach ($this->directories as $directory) {
      $this->removeTree($directory);
    }
    parent::tearDown();
  }

  /**
   * Extrai um pacote válido para um subdiretório próprio e lê o manifesto.
   */
  public function testExtractsValidPackageIntoPrivateSubdirectory(): void {
    $parent = $this->newParent();
    $zip = $this->zip([
      'imsmanifest.xml' => $this->manifest(),
      'index.html' => '<html></html>',
      'js/app.js' => 'var a = 1;',
    ]);

    $result = (new ScormPackageExtractor())->extract($zip, $parent);

    $this->assertSame(1, count($result['manifest']['launch_items']));
    $this->assertSame('index.html', $result['manifest']['launch_items'][0]['href']);
    $this->assertTrue(is_file($result['root'] . '/index.html'));
    $this->assertTrue(is_file($result['root'] . '/js/app.js'));
    $this->assertSame($parent, dirname($result['root']));
    @unlink($zip);
  }

  /**
   * Pacote recusado não deixa nenhum diretório extraído para trás.
   */
  public function testRemovesExtractionWhenPackageIsRefused(): void {
    $parent = $this->newParent();
    $zip = $this->zip(['index.html' => '<html></html>']);

    try {
      (new ScormPackageExtractor())->extract($zip, $parent);
      self::assertTrue(FALSE, 'Pacote sem manifesto deveria ser recusado.');
    }
    catch (\UnexpectedValueException) {
      $this->assertSame([], array_values(array_diff(scandir($parent), ['.', '..'])));
    }
    @unlink($zip);
  }

  /**
   * Diretório pai inexistente é erro de configuração, não de pacote.
   */
  public function testMissingParentIsRuntimeError(): void {
    $zip = $this->zip(['imsmanifest.xml' => $this->manifest()]);
    $this->expectException(\RuntimeException::class);
    try {
      (new ScormPackageExtractor())->extract($zip, sys_get_temp_dir() . '/aculta-scorm-inexistente-' . bin2hex(random_bytes(6)));
    }
    finally {
      @unlink($zip);
    }
  }

  /**
   * Manifesto mínimo SCORM 1.2 com um item lançável.
   */
  private function manifest(): string {
    return '<?xml version="1.0"?><manifest xmlns="http://www.imsproject.org/xsd/imscp_rootv1p1p2" xmlns:adlcp="http://www.adlnet.org/xsd/adlcp_rootv1p2">'
      . '<metadata><schemaversion>1.2</schemaversion></metadata>'
      . '<organizations default="ORG"><organization identifier="ORG"><title>C</title>'
      . '<item identifier="I" identifierref="R"><title>Aula</title></item></organization></organizations>'
      . '<resources><resource identifier="R" adlcp:scormtype="sco" href="index.html"><file href="index.html"/></resource></resources>'
      . '</manifest>';
  }

  /**
   * Cria um ZIP temporário com as entradas informadas.
   *
   * @param array<string, string> $entries
   */
  private function zip(array $entries): string {
    $path = sys_get_temp_dir() . '/aculta-scorm-extract-' . bin2hex(random_bytes(6)) . '.zip';
    $zip = new \ZipArchive();
    self::assertTrue($zip->open($path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) === TRUE);
    foreach ($entries as $name => $contents) {
      $zip->addFromString($name, $contents);
    }
    self::assertTrue($zip->close());
    return $path;
  }

  /**
   * Cria um diretório pai temporário para a extração.
   */
  private function newParent(): string {
    $path = sys_get_temp_dir() . '/aculta-scorm-parent-' . bin2hex(random_bytes(6));
    self::assertTrue(mkdir($path, 0700));
    $this->directories[] = $path;
    return realpath($path);
  }

  /**
   * Remove um diretório temporário de teste.
   */
  private function removeTree(string $path): void {
    if (!is_dir($path)) {
      return;
    }
    foreach (array_diff(scandir($path), ['.', '..']) as $item) {
      $full = $path . '/' . $item;
      is_dir($full) && !is_link($full) ? $this->removeTree($full) : unlink($full);
    }
    rmdir($path);
  }

}
