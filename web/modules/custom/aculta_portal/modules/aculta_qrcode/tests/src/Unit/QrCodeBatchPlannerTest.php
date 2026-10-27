<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_qrcode\Unit;

use Drupal\aculta_qrcode\Exception\InvalidQrCodeException;
use Drupal\aculta_qrcode\Service\QrCodeBatchPlanner;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Validação do pedido de lote: ids, formato e nomes de arquivo dentro do ZIP.
 */
#[CoversClass(QrCodeBatchPlanner::class)]
#[Group('aculta_qrcode')]
class QrCodeBatchPlannerTest extends UnitTestCase {

  /**
   * Ids válidos são devolvidos sem repetição e na ordem informada.
   */
  public function testIdsAreParsedUniqueAndInOrder(): void {
    $this->assertSame([3, 1, 2], (new QrCodeBatchPlanner())->parseIds('3, 1,2,3'));
  }

  /**
   * Entradas que não formam uma lista válida de ids são recusadas.
   *
   * @return array<string, array{string}>
   *   Entradas inválidas por nome.
   */
  public static function invalidIdsProvider(): array {
    return [
      'vazia' => [''],
      'só separadores' => [' , , '],
      'texto' => ['abc'],
      'zero' => ['0'],
      'negativo' => ['-1'],
      'decimal' => ['1.5'],
      'injeção' => ['1);drop'],
    ];
  }

  /**
   * Ids inválidos geram erro de validação, antes de qualquer consulta.
   */
  #[DataProvider('invalidIdsProvider')]
  public function testInvalidIdsAreRejected(string $ids): void {
    $this->expectException(InvalidQrCodeException::class);
    (new QrCodeBatchPlanner())->parseIds($ids);
  }

  /**
   * O limite de códigos por lote é exato: o máximo passa, um a mais é recusado.
   */
  public function testBatchSizeLimitIsEnforced(): void {
    $planner = new QrCodeBatchPlanner();
    $atLimit = implode(',', range(1, QrCodeBatchPlanner::MAX_IDS));
    $this->assertCount(QrCodeBatchPlanner::MAX_IDS, $planner->parseIds($atLimit));
    $this->expectException(InvalidQrCodeException::class);
    $planner->parseIds(implode(',', range(1, QrCodeBatchPlanner::MAX_IDS + 1)));
  }

  /**
   * Formatos fora da lista são recusados.
   */
  public function testUnknownFormatIsRejected(): void {
    $planner = new QrCodeBatchPlanner();
    $this->assertSame('pdf', $planner->assertFormat('pdf'));
    $this->expectException(InvalidQrCodeException::class);
    $planner->assertFormat('exe');
  }

  /**
   * O nome dentro do ZIP segue qr-<codigo>.<formato>.
   */
  public function testEntryNameUsesCodeAndFormat(): void {
    $this->assertSame('qr-ab12cd34ef56.png', (new QrCodeBatchPlanner())->entryName('ab12cd34ef56', 'png'));
  }

  /**
   * Código com caracteres de caminho nunca vira nome de arquivo.
   */
  public function testEntryNameRefusesPathCharacters(): void {
    $this->expectException(InvalidQrCodeException::class);
    (new QrCodeBatchPlanner())->entryName('../../etc', 'svg');
  }

}
