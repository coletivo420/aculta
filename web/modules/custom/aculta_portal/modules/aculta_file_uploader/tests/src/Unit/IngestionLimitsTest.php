<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_file_uploader\Unit;

use Drupal\aculta_file_uploader\IngestionLimits;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Covers the ingestion limits: defaults, ceilings and fallback for invalid values.
 */
#[CoversClass(IngestionLimits::class)]
final class IngestionLimitsTest extends UnitTestCase {

  /**
   * Os padrões em bytes batem com os valores documentados.
   */
  public function testDefaultsMatchDocumentedValues(): void {
    $limits = IngestionLimits::defaults();
    $this->assertSame(8 * IngestionLimits::MIB, $limits->bytes(IngestionLimits::DMARC_MAX_REPORT_MB));
    $this->assertSame(25 * IngestionLimits::MIB, $limits->bytes(IngestionLimits::IMAP_MAX_MESSAGE_MB));
    $this->assertSame(32 * IngestionLimits::MIB, $limits->bytes(IngestionLimits::H5P_MAX_ENTRY_MB));
    $this->assertSame(256 * IngestionLimits::MIB, $limits->bytes(IngestionLimits::H5P_MAX_TOTAL_MB));
  }

  /**
   * Configuração ausente ou apagada volta aos padrões.
   */
  public function testMissingValuesUseDefaults(): void {
    $this->assertSame(25, IngestionLimits::fromArray([])->megabytes(IngestionLimits::IMAP_MAX_MESSAGE_MB));
  }

  /**
   * Valor acima do teto, zero ou texto não numérico cai no padrão.
   */
  public function testInvalidOrAboveCeilingUsesDefault(): void {
    $limits = IngestionLimits::fromArray([
      IngestionLimits::IMAP_MAX_MESSAGE_MB => 51,
      IngestionLimits::DMARC_MAX_REPORT_MB => '0',
      IngestionLimits::H5P_MAX_ENTRY_MB => 'abc',
    ]);
    $this->assertSame(25, $limits->megabytes(IngestionLimits::IMAP_MAX_MESSAGE_MB));
    $this->assertSame(8, $limits->megabytes(IngestionLimits::DMARC_MAX_REPORT_MB));
    $this->assertSame(32, $limits->megabytes(IngestionLimits::H5P_MAX_ENTRY_MB));
  }

  /**
   * O teto em si é aceito, e texto numérico salvo como string também.
   */
  public function testCeilingAndNumericStringAreAccepted(): void {
    $limits = IngestionLimits::fromArray([
      IngestionLimits::IMAP_MAX_MESSAGE_MB => 50,
      IngestionLimits::DMARC_MAX_REPORT_MB => '12',
    ]);
    $this->assertSame(50, $limits->megabytes(IngestionLimits::IMAP_MAX_MESSAGE_MB));
    $this->assertSame(12, $limits->megabytes(IngestionLimits::DMARC_MAX_REPORT_MB));
  }

  /**
   * Uma entrada maior que o total do pacote faz valer os dois padrões H5P.
   */
  public function testEntryLargerThanTotalFallsBackToH5pDefaults(): void {
    $limits = IngestionLimits::fromArray([
      IngestionLimits::H5P_MAX_ENTRY_MB => 100,
      IngestionLimits::H5P_MAX_TOTAL_MB => 50,
    ]);
    $this->assertSame(32, $limits->megabytes(IngestionLimits::H5P_MAX_ENTRY_MB));
    $this->assertSame(256, $limits->megabytes(IngestionLimits::H5P_MAX_TOTAL_MB));
  }

}
