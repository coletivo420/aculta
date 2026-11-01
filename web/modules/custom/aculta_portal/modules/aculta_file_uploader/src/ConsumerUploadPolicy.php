<?php

declare(strict_types=1);

namespace Drupal\aculta_file_uploader;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\file\FileInterface;

/**
 * Política de imagem por integração, lida de aculta_file_uploader.settings.
 *
 * Uma integração só pode restringir o catálogo central de imagens e o limite
 * de tamanho, nunca ampliá-los. Imagem é aceita somente com extensão permitida,
 * tamanho dentro do limite e bytes compatíveis com a extensão (MIME pelo
 * conteúdo). Lista vazia fecha a política.
 */
final class ConsumerUploadPolicy {

  /**
   * Limite padrão por arquivo, em megabytes.
   */
  public const DEFAULT_MAX_MB = 10;

  public function __construct(
    private readonly ConfigFactoryInterface $configFactory,
    private readonly MimeTypeVerifier $mimeVerifier,
    private readonly FileSystemInterface $fileSystem,
  ) {}

  /**
   * Extensões de imagem permitidas para a integração, na ordem do catálogo.
   *
   * @return string[]
   *   Subconjunto de UploadPolicy::EXTENSIONS. Entradas fora do catálogo são
   *   ignoradas.
   */
  public function imageExtensions(?string $consumer = NULL): array {
    $configured = (string) ($this->settings($consumer)['allowed_extensions'] ?? UploadPolicy::EXTENSIONS);
    $requested = preg_split('/\s+/', strtolower(trim($configured))) ?: [];
    return array_values(array_intersect(explode(' ', UploadPolicy::EXTENSIONS), $requested));
  }

  /**
   * Limite por arquivo em bytes, nunca acima do catálogo central.
   */
  public function maxBytes(?string $consumer = NULL): int {
    $megabytes = (int) ($this->settings($consumer)['max_size_mb'] ?? self::DEFAULT_MAX_MB);
    $bytes = ($megabytes > 0 ? $megabytes : self::DEFAULT_MAX_MB) * 1024 * 1024;
    return min($bytes, UploadPolicy::MAX_BYTES);
  }

  /**
   * Confirma extensão, tamanho e MIME pelo conteúdo de uma imagem.
   */
  public function isValidImage(FileInterface $file, ?string $consumer = NULL): bool {
    $extension = strtolower(pathinfo($file->getFilename(), PATHINFO_EXTENSION));
    if (!in_array($extension, $this->imageExtensions($consumer), TRUE)) {
      return FALSE;
    }
    $limit = $this->maxBytes($consumer);
    $size = (int) $file->getSize();
    if ($size < 1 || $size > $limit) {
      return FALSE;
    }
    $path = $this->fileSystem->realpath($file->getFileUri());
    if (!is_string($path) || !is_readable($path)) {
      return FALSE;
    }
    $bytes = @file_get_contents($path, FALSE, NULL, 0, $limit);
    return is_string($bytes) && $this->mimeVerifier->matches($bytes, $extension);
  }

  /**
   * Resolve a integração, com fallback para a política global.
   *
   * @return array<string, mixed>
   *   Extensões e limite em megabytes.
   */
  private function settings(?string $consumer): array {
    $config = $this->configFactory->get('aculta_file_uploader.settings');
    $global = [
      'allowed_extensions' => $config->get('allowed_extensions'),
      'max_size_mb' => $config->get('max_size_mb'),
    ];
    if ($consumer === NULL) {
      return $global;
    }
    $override = $config->get('consumers.' . $consumer);
    if (!is_array($override) || !empty($override['inherit_global'])) {
      return $global;
    }
    return [
      'allowed_extensions' => $override['allowed_extensions'] ?? $global['allowed_extensions'],
      'max_size_mb' => $override['max_size_mb'] ?? $global['max_size_mb'],
    ];
  }

}
