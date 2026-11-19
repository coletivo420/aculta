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
      catch (\InvalidArgumentException $exception) {
        self::assertNotSame('', $exception->getMessage());
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

  /**
   * Tests a file answer stores only a positive managed-file reference.
   */
  public function testFileReferenceRoundTripsAndRejectsInvalidIds(): void {
    $payload = (new AnswerPayload(['caption' => 'prova']))->withFileId(42);
    self::assertSame(42, $payload->fileId());
    self::assertSame(42, AnswerPayload::fromJson($payload->toJson())->fileId());
    self::assertNull((new AnswerPayload(['answer' => 'texto']))->fileId());

    foreach ([0, -1] as $fileId) {
      try {
        $payload->withFileId($fileId);
        self::fail('A referência de arquivo inválida foi aceita.');
      }
      catch (\InvalidArgumentException $exception) {
        self::assertNotSame('', $exception->getMessage());
      }
    }
  }

  /**
   * Tests malformed references loaded from persisted JSON are rejected.
   */
  public function testMalformedFileReferenceIsRejected(): void {
    $payload = new AnswerPayload(['file_upload' => ['fids' => ['abc']]]);
    $this->expectException(\UnexpectedValueException::class);
    $payload->fileId();
  }

}
