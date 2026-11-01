<?php

declare(strict_types=1);

namespace Drupal\aculta_file_uploader;

use Drupal\Core\Utility\Token;

/**
 * Resolve destinos de upload com tokens, por conceito do filefield_paths.
 *
 * Usa o serviço Token do Core. Cada segmento do caminho é sanitizado e só os
 * esquemas public:// e private:// são aceitos.
 */
final class DestinationResolver {

  /**
   * Esquemas de destino permitidos.
   */
  private const SCHEMES = ['public://', 'private://'];

  public function __construct(private readonly Token $token) {}

  /**
   * Resolve um padrão de destino, por exemplo "public://aculta/[date:custom:Y]".
   *
   * @param string $pattern
   *   Destino com tokens opcionais.
   * @param array<string, mixed> $data
   *   Dados de contexto para os tokens.
   * @param string|null $type
   *   Tipo de token do Core, por exemplo "node" ou "user".
   *
   * @return string
   *   Destino final, sem tokens não resolvidos e com segmentos sanitizados.
   *
   * @throws \InvalidArgumentException
   *   Quando o esquema não é permitido ou o caminho tenta sair do destino.
   */
  public function resolve(string $pattern, array $data = [], ?string $type = NULL): string {
    $scheme = NULL;
    foreach (self::SCHEMES as $candidate) {
      if (str_starts_with($pattern, $candidate)) {
        $scheme = $candidate;
        break;
      }
    }
    if ($scheme === NULL) {
      throw new \InvalidArgumentException('O destino de upload precisa usar public:// ou private://.');
    }

    $replaced = $this->token->replace(substr($pattern, strlen($scheme)), $type, $data, ['clear' => TRUE]);

    $segments = [];
    foreach (explode('/', $replaced) as $segment) {
      if ($segment === '' || $segment === '.') {
        continue;
      }
      if ($segment === '..') {
        throw new \InvalidArgumentException('O destino de upload não pode sair da pasta configurada.');
      }
      // Sanitiza o segmento: somente letras, números, ponto, hífen e sublinhado.
      $clean = preg_replace('/[^A-Za-z0-9._-]+/', '-', $segment) ?? '';
      $clean = trim($clean, '.-');
      if ($clean !== '') {
        $segments[] = $clean;
      }
    }

    if ($segments === []) {
      throw new \InvalidArgumentException('O destino de upload ficou vazio após a resolução dos tokens.');
    }

    return $scheme . implode('/', $segments);
  }

}
