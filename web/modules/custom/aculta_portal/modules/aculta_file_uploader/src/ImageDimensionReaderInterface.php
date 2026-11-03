<?php

declare(strict_types=1);

namespace Drupal\aculta_file_uploader;

/**
 * Contrato de leitura de dimensões de imagem usado na validação de upload.
 *
 * Implementado pelo leitor nativo deste módulo e, quando aculta_image está ativo, pelo aculta_image.inspector.
 */
interface ImageDimensionReaderInterface {

  /**
   * Largura e altura da imagem em URI, ou NULL se não for imagem com dimensões positivas.
   *
   * @return array{width: int, height: int}|null
   */
  public function dimensions(string $uri): ?array;

}
