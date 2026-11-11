<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_lms_h5p\Unit;

use Drupal\aculta_lms_h5p\aculta_H5PDrupal\AcultaH5PArchiveResourceLimits;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Covers ZIP resource limits for H5P library packages.
 */
#[CoversClass(AcultaH5PArchiveResourceLimits::class)]
final class AcultaH5PArchiveResourceLimitsTest extends UnitTestCase {

  /**
   * Temporary archives created by the test.
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
   * Accepts an ordinary package within the default limits.
   */
  public function testAcceptsOrdinaryPackage(): void {
    $zip = $this->openArchive([
      'library.json' => '{"machineName":"H5P.Example"}',
      'js/example.js' => str_repeat('var a = 1;', 5000),
    ]);
    (new AcultaH5PArchiveResourceLimits())->assertWithinLimits($zip);
    $zip->close();
    $this->addToAssertionCount(1);
  }

  /**
   * Rejects a package with more entries than allowed.
   */
  public function testRejectsTooManyEntries(): void {
    $zip = $this->openArchive(['a.json' => '{}', 'b.json' => '{}', 'c.json' => '{}']);
    $this->expectException(\UnexpectedValueException::class);
    $this->expectExceptionMessage('entradas demais');
    try {
      (new AcultaH5PArchiveResourceLimits(maxEntries: 2))->assertWithinLimits($zip);
    }
    finally {
      $zip->close();
    }
  }

  /**
   * Rejects one entry that expands beyond the per-entry limit.
   */
  public function testRejectsEntryLargerThanLimit(): void {
    $zip = $this->openArchive(['big.js' => str_repeat('x', 2 * 1024 * 1024)]);
    $this->expectException(\UnexpectedValueException::class);
    $this->expectExceptionMessage('arquivo grande demais');
    try {
      (new AcultaH5PArchiveResourceLimits(maxEntryBytes: 1024 * 1024))->assertWithinLimits($zip);
    }
    finally {
      $zip->close();
    }
  }

  /**
   * Rejects a package whose total expansion exceeds the package limit.
   */
  public function testRejectsPackageTotalAboveLimit(): void {
    $entries = [];
    for ($i = 0; $i < 4; $i++) {
      $entries["f$i.bin"] = str_repeat("\x01", 512 * 1024);
    }
    $zip = $this->openArchive($entries);
    $this->expectException(\UnexpectedValueException::class);
    $this->expectExceptionMessage('descompactado');
    try {
      (new AcultaH5PArchiveResourceLimits(maxTotalBytes: 1024 * 1024))->assertWithinLimits($zip);
    }
    finally {
      $zip->close();
    }
  }

  /**
   * Rejects a highly compressible entry (ZIP bomb pattern).
   */
  public function testRejectsSuspiciousCompressionRatio(): void {
    $zip = $this->openArchive(['zeros.js' => str_repeat("\0", 2 * 1024 * 1024)]);
    $this->expectException(\UnexpectedValueException::class);
    $this->expectExceptionMessage('compressão suspeita');
    try {
      (new AcultaH5PArchiveResourceLimits())->assertWithinLimits($zip);
    }
    finally {
      $zip->close();
    }
  }

  /**
   * Ignores the ratio for small entries that compress well.
   */
  public function testIgnoresRatioForSmallEntries(): void {
    $zip = $this->openArchive(['small.js' => str_repeat("\0", 200 * 1024)]);
    (new AcultaH5PArchiveResourceLimits())->assertWithinLimits($zip);
    $zip->close();
    $this->addToAssertionCount(1);
  }

  /**
   * Opens a temporary archive built from the given entries.
   *
   * @param array<string, string> $entries
   *   Entry name to contents.
   */
  private function openArchive(array $entries): \ZipArchive {
    $path = sys_get_temp_dir() . '/aculta-h5p-limits-' . bin2hex(random_bytes(6)) . '.h5p';
    $this->archives[] = $path;
    $zip = new \ZipArchive();
    self::assertTrue($zip->open($path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE));
    foreach ($entries as $name => $contents) {
      self::assertTrue($zip->addFromString($name, $contents));
    }
    self::assertTrue($zip->close());
    self::assertTrue($zip->open($path));
    return $zip;
  }

}
