<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_user\Unit;

use Drupal\aculta_user\Associado\AssociadoMarker;
use Drupal\aculta_user\Controller\AssociadosController;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests CSV safety for the restricted associates export.
 */
#[CoversClass(AssociadosController::class)]
#[Group('aculta_user')]
final class AssociadosControllerTest extends UnitTestCase {

  /**
   * Formula-like text is prefixed without changing ordinary text.
   */
  #[DataProvider('spreadsheetCellProvider')]
  public function testSafeSpreadsheetCell(string $value, string $expected): void {
    $controller = new AssociadosController(
      $this->createMock(EntityTypeManagerInterface::class),
      (new \ReflectionClass(AssociadoMarker::class))->newInstanceWithoutConstructor(),
      $this->createMock(DateFormatterInterface::class),
    );
    $method = new \ReflectionMethod($controller, 'safeSpreadsheetCell');

    self::assertSame($expected, $method->invoke($controller, $value));
  }

  /**
   * @return array<string, array{string, string}>
   *   Text and its expected neutralized value.
   */
  public static function spreadsheetCellProvider(): array {
    return [
      'equals' => ['=1+1', "'=1+1"],
      'plus' => ['+cmd', "'+cmd"],
      'minus' => ['-cmd', "'-cmd"],
      'at' => ['@cmd', "'@cmd"],
      'spaces and control' => [" \t\x01=cmd", "' \t\x01=cmd"],
      'ordinary' => ['Maria da Silva', 'Maria da Silva'],
    ];
  }

}
