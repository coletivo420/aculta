<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_events\Unit;

use Drupal\aculta_events\Controller\EventLocationWidgetController;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Tests strict input validation for the location widget endpoint.
 */
#[CoversClass(EventLocationWidgetController::class)]
#[Group('aculta_events')]
final class EventLocationWidgetControllerTest extends UnitTestCase {

  /**
   * Valid widget identifiers are accepted unchanged.
   */
  public function testValidWidgetParameters(): void {
    $controller = new EventLocationWidgetController();
    $method = new \ReflectionMethod($controller, 'validatedWidgetParameters');

    self::assertSame(
      ['field_event_location', 'field-event-location-wrapper'],
      $method->invoke($controller, new Request([
        'field_name' => 'field_event_location',
        'wrapper_id' => 'field-event-location-wrapper',
      ])),
    );
  }

  /**
   * Confirma que parâmetros inválidos são rejeitados com erro 400.
   *
   * @param array<string, string> $query
   *   Parâmetros de consulta a validar.
   */
  #[DataProvider('invalidParameterProvider')]
  public function testInvalidWidgetParametersAreRejected(array $query): void {
    $controller = new EventLocationWidgetController();
    $method = new \ReflectionMethod($controller, 'validatedWidgetParameters');

    $this->expectException(BadRequestHttpException::class);
    $method->invoke($controller, new Request($query));
  }

  /**
   * Fornece conjuntos inválidos de parâmetros de consulta.
   *
   * @return array<string, array{array<string, string>}>
   *   Conjuntos inválidos indexados por descrição.
   */
  public static function invalidParameterProvider(): array {
    return [
      'unexpected field' => [['field_name' => 'field_other', 'wrapper_id' => 'field-event-location-wrapper']],
      'markup in wrapper' => [['field_name' => 'field_event_location', 'wrapper_id' => 'x"><script>alert(1)</script>']],
      'selector in wrapper' => [['field_name' => 'field_event_location', 'wrapper_id' => '#unexpected']],
      'spaces in field' => [['field_name' => ' field_event_location', 'wrapper_id' => 'field-event-location-wrapper']],
    ];
  }

}
