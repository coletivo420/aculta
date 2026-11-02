<?php

declare(strict_types=1);

namespace Drupal\aculta_lms\Xapi;

/**
 * Token curto de tentativa para o envio de declarações xAPI (ADR-041, F3).
 *
 * Função pura, sem Drupal e sem armazenamento. Assina com HMAC-SHA256 as
 * reivindicações da tentativa: estudante (uid), conteúdo, lançamento
 * (registration) e validade. O segredo é injetado por quem chama; na F3 virá
 * da Drupal Key, nunca de configuração exportável.
 *
 * Formato: base64url(JSON das reivindicações) . "." . base64url(HMAC).
 */
final class XapiAttemptToken {

  /**
   * Validade máxima de um token, em segundos.
   */
  public const MAX_TTL = 7200;

  /**
   * Tamanho mínimo do segredo, em bytes.
   */
  public const MIN_SECRET_BYTES = 32;

  public function __construct(private readonly string $secret) {
    if (strlen($secret) < self::MIN_SECRET_BYTES) {
      throw new \InvalidArgumentException('Segredo do token curto abaixo do mínimo.');
    }
  }

  /**
   * Emite um token para uma tentativa.
   *
   * @param int $uid
   *   Estudante dono da tentativa.
   * @param string $content
   *   Identificador do conteúdo xAPI (IRI da atividade de lançamento).
   * @param string $registration
   *   UUID do lançamento (context.registration).
   * @param int $now
   *   Instante de emissão, em Unix time.
   * @param int $ttl
   *   Validade, em segundos, de 1 até MAX_TTL.
   */
  public function issue(int $uid, string $content, string $registration, int $now, int $ttl = 1800): string {
    if ($uid <= 0 || $content === '' || !$this->isUuid($registration)) {
      throw new \InvalidArgumentException('Reivindicações inválidas para o token.');
    }
    if ($ttl < 1 || $ttl > self::MAX_TTL) {
      throw new \InvalidArgumentException('Validade do token fora do limite.');
    }
    $claims = [
      'uid' => $uid,
      'content' => $content,
      'registration' => $registration,
      'iat' => $now,
      'exp' => $now + $ttl,
    ];
    $body = $this->encode((string) json_encode($claims, JSON_UNESCAPED_SLASHES));
    return $body . '.' . $this->encode($this->sign($body));
  }

  /**
   * Verifica assinatura e validade, e devolve as reivindicações.
   *
   * @throws \UnexpectedValueException
   *   Quando o token é malformado, tem assinatura inválida ou está expirado.
   */
  public function verify(string $token, int $now): array {
    $parts = explode('.', $token);
    if (count($parts) !== 2 || $parts[0] === '' || $parts[1] === '') {
      throw new \UnexpectedValueException('Token malformado.');
    }
    [$body, $signature] = $parts;
    $expected = $this->encode($this->sign($body));
    if (!hash_equals($expected, $signature)) {
      throw new \UnexpectedValueException('Assinatura inválida.');
    }
    $claims = json_decode((string) $this->decode($body), TRUE);
    if (!is_array($claims)
      || !isset($claims['uid'], $claims['content'], $claims['registration'], $claims['iat'], $claims['exp'])
      || !is_int($claims['uid']) || $claims['uid'] <= 0
      || !is_string($claims['content']) || $claims['content'] === ''
      || !is_string($claims['registration']) || !$this->isUuid($claims['registration'])
      || !is_int($claims['iat']) || !is_int($claims['exp'])) {
      throw new \UnexpectedValueException('Reivindicações inválidas.');
    }
    if ($claims['exp'] - $claims['iat'] > self::MAX_TTL) {
      throw new \UnexpectedValueException('Validade acima do máximo.');
    }
    if ($now >= $claims['exp']) {
      throw new \UnexpectedValueException('Token expirado.');
    }
    return $claims;
  }

  private function sign(string $body): string {
    return hash_hmac('sha256', $body, $this->secret, TRUE);
  }

  private function encode(string $bytes): string {
    return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
  }

  private function decode(string $text): string|false {
    $padded = strtr($text, '-_', '+/');
    $padded .= str_repeat('=', (4 - strlen($padded) % 4) % 4);
    return base64_decode($padded, TRUE);
  }

  private function isUuid(string $value): bool {
    return preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $value) === 1;
  }

}
