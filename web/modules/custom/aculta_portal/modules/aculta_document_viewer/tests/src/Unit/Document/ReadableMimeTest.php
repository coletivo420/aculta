<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_document_viewer\Unit\Document;

use Drupal\aculta_document_viewer\Document\ReadableMime;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Valida a normalização de MIME e labels acessíveis (OC315-09).
 */
#[CoversClass(ReadableMime::class)]
#[Group('aculta_document_viewer')]
final class ReadableMimeTest extends UnitTestCase {

  /**
   * Instância sob teste.
   */
  private ReadableMime $readableMime;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->readableMime = new ReadableMime($this->getStringTranslationStub());
  }

  /**
   * Mimes comuns são convertidos para badges amigáveis.
   */
  public function testCommonMimesAreNormalized(): void {
    $this->assertSame('PDF', $this->readableMime->getHumanReadableFormat('application/pdf'));
    $this->assertSame('DOCX', $this->readableMime->getHumanReadableFormat('application/vnd.openxmlformats-officedocument.wordprocessingml.document'));
    $this->assertSame('XLSX', $this->readableMime->getHumanReadableFormat('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'));
    $this->assertSame('ODT', $this->readableMime->getHumanReadableFormat('application/vnd.oasis.opendocument.text'));
    $this->assertSame('CSV', $this->readableMime->getHumanReadableFormat('text/csv'));
    $this->assertSame('ZIP', $this->readableMime->getHumanReadableFormat('application/zip'));
    $this->assertSame('MP4', $this->readableMime->getHumanReadableFormat('video/mp4'));
  }

  /**
   * Tamanho de arquivo é formatado em unidades legíveis com padrão brasileiro.
   */
  public function testFileSizeFormatting(): void {
    $this->assertSame('0 B', $this->readableMime->formatFileSize(0));
    $this->assertSame('512 B', $this->readableMime->formatFileSize(512));
    $this->assertSame('2,4 KB', $this->readableMime->formatFileSize(2450));
    $this->assertSame('15 KB', $this->readableMime->formatFileSize(15360));
    $this->assertSame('3,5 MB', $this->readableMime->formatFileSize(3670016));
    $this->assertSame('12 MB', $this->readableMime->formatFileSize(12582912));
    $this->assertSame('1,2 GB', $this->readableMime->formatFileSize(1288490188));
  }

  /**
   * Label acessível para leitor de tela inclui título, formato e tamanho.
   */
  public function testAccessibleDownloadLabel(): void {
    $label = $this->readableMime->getAccessibleDownloadLabel(
      'Relatório de Transparência 2026',
      'application/pdf',
      3670016
    );

    $this->assertSame('Baixar Relatório de Transparência 2026 (formato PDF, tamanho 3,5 MB)', $label);
  }

}
