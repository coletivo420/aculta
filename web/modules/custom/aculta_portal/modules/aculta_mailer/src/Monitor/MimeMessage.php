<?php

declare(strict_types=1);

namespace Drupal\aculta_mailer\Monitor;

/**
 * Leitura mínima de mensagens MIME (RFC 5322 e 2045): cabeçalhos, partes e codificações.
 *
 * Suficiente para os relatórios DMARC (anexos) e para as notificações de falha (RFC 3464). Não é um cliente de correio.
 */
final class MimeMessage {

  /**
   * Cabeçalhos em minúsculas (dobrados quando continuam na linha seguinte).
   *
   * @return array<string, string>
   */
  public static function headers(string $block): array {
    $headers = [];
    $current = NULL;
    foreach (preg_split('/\r?\n/', $block) ?: [] as $line) {
      if ($line !== '' && ($line[0] === ' ' || $line[0] === "\t") && $current !== NULL) {
        $headers[$current] .= ' ' . trim($line);
        continue;
      }
      $pos = strpos($line, ':');
      if ($pos === FALSE) {
        continue;
      }
      $current = strtolower(trim(substr($line, 0, $pos)));
      $headers[$current] = trim(substr($line, $pos + 1));
    }
    return $headers;
  }

  /**
   * Partes folha da mensagem, em profundidade: cada uma com tipo, parâmetros, nome de arquivo e corpo decodificado.
   *
   * @return array<int, array{type: string, params: array<string, string>, filename: string, body: string}>
   */
  public static function leaves(string $raw): array {
    [$headerBlock, $body] = self::split($raw);
    $headers = self::headers($headerBlock);
    [$type, $params] = self::contentType($headers);
    if (str_starts_with($type, 'multipart/') && isset($params['boundary'])) {
      $leaves = [];
      foreach (self::multipartParts($body, $params['boundary']) as $part) {
        array_push($leaves, ...self::leaves($part));
      }
      return $leaves;
    }
    return [[
      'type' => $type,
      'params' => $params,
      'filename' => self::filename($headers, $params),
      'body' => self::decode($body, $headers['content-transfer-encoding'] ?? ''),
    ]];
  }

  /**
   * Cabeçalhos da mensagem de nível superior, como texto.
   *
   * @return array<string, string>
   */
  public static function topHeaders(string $raw): array {
    [$headerBlock] = self::split($raw);
    return self::headers($headerBlock);
  }

  /**
   * Separa cabeçalhos e corpo na primeira linha em branco.
   *
   * @return array{0: string, 1: string}
   */
  private static function split(string $raw): array {
    $parts = preg_split('/\r?\n\r?\n/', $raw, 2) ?: [$raw, ''];
    return [$parts[0], $parts[1] ?? ''];
  }

  /**
   * @return array{0: string, 1: array<string, string>}
   */
  private static function contentType(array $headers): array {
    $value = $headers['content-type'] ?? 'text/plain';
    $segments = array_map('trim', explode(';', $value));
    $type = strtolower(array_shift($segments) ?? 'text/plain');
    $params = [];
    foreach ($segments as $segment) {
      if (preg_match('/^([a-zA-Z0-9_-]+)\s*=\s*"?([^"]*)"?$/', $segment, $m) === 1) {
        $params[strtolower($m[1])] = $m[2];
      }
    }
    return [$type, $params];
  }

  /**
   * Nome do anexo, por Content-Disposition ou Content-Type.
   */
  private static function filename(array $headers, array $typeParams): string {
    foreach ([$headers['content-disposition'] ?? '', $headers['content-type'] ?? ''] as $value) {
      if (preg_match('/(?:filename|name)\s*=\s*"?([^";]+)"?/i', $value, $m) === 1) {
        return trim($m[1]);
      }
    }
    return $typeParams['name'] ?? '';
  }

  /**
   * Partes de um multipart, sem os delimitadores.
   *
   * @return string[]
   */
  private static function multipartParts(string $body, string $boundary): array {
    $parts = [];
    foreach (preg_split('/\r?\n--' . preg_quote($boundary, '/') . '(?:--)?[ \t]*\r?\n?/', $body) ?: [] as $chunk) {
      $chunk = ltrim($chunk, "\r\n");
      if (trim($chunk) !== '') {
        $parts[] = $chunk;
      }
    }
    return $parts;
  }

  /**
   * Corpo decodificado conforme Content-Transfer-Encoding.
   */
  private static function decode(string $body, string $encoding): string {
    return match (strtolower(trim($encoding))) {
      'base64' => (string) base64_decode(preg_replace('/\s+/', '', $body) ?? '', TRUE),
      'quoted-printable' => quoted_printable_decode($body),
      default => $body,
    };
  }

}
