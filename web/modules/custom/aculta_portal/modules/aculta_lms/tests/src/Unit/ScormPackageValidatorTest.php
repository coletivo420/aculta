<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_lms\Unit;

use Drupal\aculta_lms\Scorm\ScormPackageValidator;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Covers the SCORM package validator: limits, names, links and the manifest rule.
 */
#[CoversClass(ScormPackageValidator::class)]
final class ScormPackageValidatorTest extends UnitTestCase {

  /**
   * Arquivos temporários criados pelo teste.
   *
   * @var string[]
   */
  private array $archives = [];

  /**
   * {@inheritdoc}
   */
  protected function tearDown(): void {
    foreach ($this->archives as $path) {
      @unlink($path);
    }
    parent::tearDown();
  }

  /**
   * Aceita um pacote comum com manifesto na raiz.
   */
  public function testAcceptsValidPackage(): void {
    $zip = $this->zip(['imsmanifest.xml' => '<manifest/>', 'index.html' => '<html></html>', 'js/app.js' => 'var a;']);
    (new ScormPackageValidator())->assertValid($zip);
    $this->addToAssertionCount(1);
  }

  /**
   * Sem imsmanifest.xml na raiz, o pacote é recusado.
   */
  public function testRejectsPackageWithoutManifest(): void {
    $zip = $this->zip(['index.html' => '<html></html>']);
    $this->expectException(\UnexpectedValueException::class);
    $this->expectExceptionMessage('imsmanifest.xml');
    (new ScormPackageValidator())->assertValid($zip);
  }

  /**
   * Manifesto dentro de subpasta não conta como raiz.
   */
  public function testRejectsManifestOutsideRoot(): void {
    $zip = $this->zip(['pasta/imsmanifest.xml' => '<manifest/>']);
    $this->expectException(\UnexpectedValueException::class);
    (new ScormPackageValidator())->assertValid($zip);
  }

  /**
   * Caminho com ".." é recusado.
   */
  public function testRejectsTraversalName(): void {
    $zip = $this->zip(['imsmanifest.xml' => '<manifest/>', '../fora.txt' => 'x']);
    $this->expectException(\UnexpectedValueException::class);
    $this->expectExceptionMessage('caminho inválido');
    (new ScormPackageValidator())->assertValid($zip);
  }

  /**
   * Número de entradas acima do teto é recusado.
   */
  public function testRejectsTooManyEntries(): void {
    $zip = $this->zip(['imsmanifest.xml' => '<manifest/>', 'a.js' => '1', 'b.js' => '2']);
    $this->expectException(\UnexpectedValueException::class);
    $this->expectExceptionMessage('entradas demais');
    (new ScormPackageValidator(maxEntries: 2))->assertValid($zip);
  }

  /**
   * Entrada que descompacta além do teto por arquivo é recusada.
   */
  public function testRejectsEntryLargerThanLimit(): void {
    $zip = $this->zip(['imsmanifest.xml' => '<manifest/>', 'big.bin' => str_repeat('x', 2 * 1024 * 1024)]);
    $this->expectException(\UnexpectedValueException::class);
    $this->expectExceptionMessage('arquivo grande demais');
    (new ScormPackageValidator(maxEntryBytes: 1024 * 1024))->assertValid($zip);
  }

  /**
   * Total descompactado acima do teto do pacote é recusado.
   */
  public function testRejectsPackageTotalAboveLimit(): void {
    $zip = $this->zip([
      'imsmanifest.xml' => '<manifest/>',
      'a.bin' => str_repeat("\x01", 512 * 1024),
      'b.bin' => str_repeat("\x02", 512 * 1024),
    ]);
    $this->expectException(\UnexpectedValueException::class);
    $this->expectExceptionMessage('grande demais descompactado');
    (new ScormPackageValidator(maxTotalBytes: 1024 * 1024))->assertValid($zip);
  }

  /**
   * Entrada muito compressível (padrão de ZIP bomb) é recusada acima do tamanho mínimo.
   */
  public function testRejectsSuspiciousCompressionRatio(): void {
    $zip = $this->zip(['imsmanifest.xml' => '<manifest/>', 'zeros.js' => str_repeat("\0", 2 * 1024 * 1024)]);
    $this->expectException(\UnexpectedValueException::class);
    $this->expectExceptionMessage('compressão suspeita');
    (new ScormPackageValidator())->assertValid($zip);
  }

  /**
   * Link simbólico dentro do pacote é recusado.
   */
  public function testRejectsSymlink(): void {
    $path = $this->newPath();
    $zip = new \ZipArchive();
    self::assertTrue($zip->open($path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) === TRUE);
    $zip->addFromString('imsmanifest.xml', '<manifest/>');
    $zip->addFromString('atalho', '/etc/passwd');
    $zip->setExternalAttributesName('atalho', \ZipArchive::OPSYS_UNIX, (0120777) << 16);
    $zip->close();

    $this->expectException(\UnexpectedValueException::class);
    $this->expectExceptionMessage('link simbólico');
    (new ScormPackageValidator())->assertValid($path);
  }

  /**
   * Cria um ZIP temporário com as entradas informadas e devolve o caminho.
   *
   * @param array<string, string> $entries
   */
  private function zip(array $entries): string {
    $path = $this->newPath();
    $zip = new \ZipArchive();
    self::assertTrue($zip->open($path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) === TRUE);
    foreach ($entries as $name => $contents) {
      $zip->addFromString($name, $contents);
    }
    self::assertTrue($zip->close());
    return $path;
  }

  /**
   * Caminho único para um ZIP temporário, registrado para limpeza.
   */
  private function newPath(): string {
    $path = sys_get_temp_dir() . '/aculta-scorm-validator-' . bin2hex(random_bytes(6)) . '.zip';
    $this->archives[] = $path;
    return $path;
  }

}
