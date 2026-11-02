<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_lms\Unit;

use Drupal\aculta_lms\Xapi\XapiAttemptToken;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Covers the per-attempt xAPI token model (ADR-041, F3).
 */
#[CoversClass(XapiAttemptToken::class)]
final class XapiAttemptTokenTest extends UnitTestCase {

  private const SECRET = '0123456789abcdef0123456789abcdef';
  private const REGISTRATION = '9e13c2a4-5b1f-4c8e-9a7d-3f2b1e0d4c5a';
  private const ACTOR_SHA1 = '0123456789abcdef0123456789abcdef01234567';
  private const LESSON_STATUS_ID = 2468;
  private const NOW = 1794000000;

  /**
   * Token emitido verifica com as mesmas reivindicações.
   */
  public function testRoundTripKeepsClaims(): void {
    $token = $this->issue();
    $claims = (new XapiAttemptToken(self::SECRET))->verify($token, self::NOW + 60);

    $this->assertSame(42, $claims['uid']);
    $this->assertSame('https://exemplo.invalid/atividade/1', $claims['content']);
    $this->assertSame(self::REGISTRATION, $claims['registration']);
    $this->assertSame(self::LESSON_STATUS_ID, $claims['lesson_status_id']);
    $this->assertSame(self::ACTOR_SHA1, $claims['actor_mbox_sha1sum']);
    $this->assertSame(self::NOW + 1800, $claims['exp']);
  }

  /**
   * Alterar as reivindicações invalida a assinatura.
   */
  public function testTamperedBodyIsRefused(): void {
    $token = $this->issue();
    [$body, $signature] = explode('.', $token);
    $forged = strtr(base64_encode((string) json_encode(['uid' => 43, 'content' => 'x', 'registration' => self::REGISTRATION, 'lesson_status_id' => self::LESSON_STATUS_ID, 'actor_mbox_sha1sum' => self::ACTOR_SHA1, 'iat' => self::NOW, 'exp' => self::NOW + 1800])), '+/', '-_');
    $this->expectException(\UnexpectedValueException::class);
    (new XapiAttemptToken(self::SECRET))->verify(rtrim($forged, '=') . '.' . $signature, self::NOW);
  }

  /**
   * Token assinado com outro segredo é recusado.
   */
  public function testWrongSecretIsRefused(): void {
    $token = $this->issue();
    $this->expectException(\UnexpectedValueException::class);
    (new XapiAttemptToken('fedcba9876543210fedcba9876543210'))->verify($token, self::NOW);
  }

  /**
   * Token expirado é recusado no instante de expiração.
   */
  public function testExpiredTokenIsRefused(): void {
    $token = $this->issue(60);
    $this->expectException(\UnexpectedValueException::class);
    (new XapiAttemptToken(self::SECRET))->verify($token, self::NOW + 60);
  }

  /**
   * Formato sem ponto separador é recusado.
   */
  public function testMalformedTokenIsRefused(): void {
    $this->expectException(\UnexpectedValueException::class);
    (new XapiAttemptToken(self::SECRET))->verify('semponto', self::NOW);
  }

  /**
   * Segredo abaixo de 32 bytes não é aceito.
   */
  public function testShortSecretIsRefused(): void {
    $this->expectException(\InvalidArgumentException::class);
    new XapiAttemptToken('curto');
  }

  /**
   * Validade acima do máximo é recusada na emissão.
   */
  public function testTtlAboveMaximumIsRefused(): void {
    $this->expectException(\InvalidArgumentException::class);
    $this->issue(XapiAttemptToken::MAX_TTL + 1);
  }

  /**
   * Registration que não é UUID é recusado na emissão.
   */
  public function testNonUuidRegistrationIsRefused(): void {
    $this->expectException(\InvalidArgumentException::class);
    (new XapiAttemptToken(self::SECRET))->issue(42, 'https://exemplo.invalid/atividade/1', 'reg-1', self::LESSON_STATUS_ID, self::ACTOR_SHA1, self::NOW);
  }

  private function issue(int $ttl = 1800): string {
    return (new XapiAttemptToken(self::SECRET))->issue(
      42,
      'https://exemplo.invalid/atividade/1',
      self::REGISTRATION,
      self::LESSON_STATUS_ID,
      self::ACTOR_SHA1,
      self::NOW,
      $ttl,
    );
  }

}
