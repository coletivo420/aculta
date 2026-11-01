<?php

declare(strict_types=1);

namespace Drupal\aculta_file_uploader;

/**
 * Política única de upload do ACULTA.
 *
 * Catálogo central de extensões e limite de tamanho. Consumidores só podem
 * restringir o catálogo central, nunca ampliá-lo. Mesmo catálogo e limite do
 * aculta_image, para que a migração futura não altere comportamento.
 */
final class UploadPolicy {

  /**
   * Extensões aceitas pelo catálogo central.
   */
  public const EXTENSIONS = 'png gif jpg jpeg webp';

  /**
   * Tamanho máximo padrão do arquivo enviado, em bytes (10 MB).
   */
  public const MAX_BYTES = 10485760;

  /**
   * Documentos aceitos pelo catálogo de anexos (pdf, Word e planilhas).
   */
  public const DOCUMENT_EXTENSIONS = 'pdf docx xls xlsx';

  /**
   * Monta validadores do Managed File API para a política central ou escopada.
   *
   * @param int|null $maxBytes
   *   Limite em bytes. Padrão: 10 MB.
   * @param string|null $extensions
   *   Subconjunto do catálogo central, separado por espaço.
   *
   * @return array<string, array<string, int|string>>
   *   Validadores do Managed File API.
   *
   * @throws \InvalidArgumentException
   *   Quando o limite é inválido ou as extensões saem do catálogo central.
   */
  public function validators(?int $maxBytes = NULL, ?string $extensions = NULL): array {
    $maxBytes ??= self::MAX_BYTES;
    $extensions ??= self::EXTENSIONS;
    $allowed = array_values(array_unique(preg_split('/\s+/', trim($extensions)) ?: []));
    $central = explode(' ', self::EXTENSIONS);
    if ($maxBytes < 1 || $allowed === [] || array_diff($allowed, $central) !== []) {
      throw new \InvalidArgumentException('A política contextual de upload excede o catálogo ACULTA.');
    }
    return [
      'FileExtension' => ['extensions' => implode(' ', $allowed)],
      'FileSizeLimit' => ['fileLimit' => $maxBytes],
    ];
  }

}
