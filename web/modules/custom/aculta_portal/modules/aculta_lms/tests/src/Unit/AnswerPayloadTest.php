<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_lms\Unit;

use Drupal\aculta_lms\Learning\AnswerPayload;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Tests the immutable ACULTA answer payload.
 */
#[CoversClass(AnswerPayload::class)]
final class AnswerPayloadTest extends TestCase {

  /**
   * Tests construction and round-trip persistence.
   */
  public function testPayloadRoundTripsAsAnObject(): void {
    $payload = new AnswerPayload(['answer' => ['selected' => ['a', 'c']], 'attempt_context' => ['source' => 'web']]);

    self::assertSame(['answer' => ['selected' => ['a', 'c']], 'attempt_context' => ['source' => 'web']], $payload->toArray());
    self::assertSame('{"answer":{"selected":["a","c"]},"attempt_context":{"source":"web"}}', $payload->toJson());
    self::assertSame($payload->toArray(), AnswerPayload::fromJson($payload->toJson())->toArray());
  }

  /**
   * Tests malformed JSON and non-object JSON rejection.
   */
  public function testInvalidOrNonObjectJsonIsRejected(): void {
    foreach (['invalid', '["list"]', 'null'] as $json) {
      try {
        AnswerPayload::fromJson($json);
        self::fail('JSON de resposta inválido foi aceito.');
      }
      catch (\InvalidArgumentException) {
        self::assertTrue(TRUE);
      }
    }
  }

  /**
   * Tests rejection of values JSON cannot represent.
   */
  public function testNonSerializableValuesAreRejected(): void {
    $this->expectException(\InvalidArgumentException::class);
    new AnswerPayload(['resource' => fopen('php://memory', 'rb')]);
  }

}
