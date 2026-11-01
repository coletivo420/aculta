<?php

declare(strict_types=1);

namespace Drupal\aculta_file_uploader;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\file\FileInterface;

/**
 * Política de upload por integração, lida de aculta_file_uploader.settings.
 *
 * Uma integração só restringe os catálogos centrais (imagens e documentos) e o
 * limite de tamanho, nunca os amplia. Um arquivo é aceito somente com extensão
 * permitida, tamanho dentro do limite e bytes compatíveis com a extensão (MIME
 * pelo conteúdo). Lista vazia fecha a política.
 */
final class ConsumerUploadPolicy {

  /**
   * Limite padrão por arquivo, em megabytes.
   */
  public const DEFAULT_MAX_MB = 10;

  public function __construct(
    private readonly ConfigFactoryInterface $configFactory,
    private readonly UploadPolicy $policy,
    private readonly MimeTypeVerifier $mimeVerifier,
    private readonly FileSystemInterface $fileSystem,
  ) {}

  /**
   * Extensões de imagem permitidas para a integração, na ordem do catálogo.
   *
   * @return string[]
   */
  public function imageExtensions(?string $consumer = NULL): array {
    return $this->catalogExtensions($consumer, UploadPolicy::EXTENSIONS);
  }

  /**
   * Extensões de documento permitidas para a integração, na ordem do catálogo.
   *
   * @return string[]
   */
  public function documentExtensions(?string $consumer = NULL): array {
    return $this->catalogExtensions($consumer, UploadPolicy::DOCUMENT_EXTENSIONS);
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
    return $this->isValidFile($file, $this->imageExtensions($consumer), $consumer);
  }

  /**
   * Confirma extensão, tamanho e MIME pelo conteúdo de um documento.
   */
  public function isValidDocument(FileInterface $file, ?string $consumer = NULL): bool {
    return $this->isValidFile($file, $this->documentExtensions($consumer), $consumer);
  }

  /**
   * Validadores do Managed File API para imagens, ou [] se a lista estiver vazia.
   *
   * @return array<string, array<string, int|string>>
   */
  public function imageValidators(?string $consumer = NULL): array {
    $extensions = $this->imageExtensions($consumer);
    if ($extensions === []) {
      return [];
    }
    return $this->policy->validators($this->maxBytes($consumer), implode(' ', $extensions)) + ['FileIsImage' => []];
  }

  /**
   * Validadores do Managed File API para documentos, ou [] se a lista estiver vazia.
   *
   * @return array<string, array<string, int|string>>
   */
  public function documentValidators(?string $consumer = NULL): array {
    $extensions = $this->documentExtensions($consumer);
    if ($extensions === []) {
      return [];
    }
    return $this->policy->documentValidators($this->maxBytes($consumer), implode(' ', $extensions));
  }

  /**
   * Interseção entre a configuração da integração e um catálogo central.
   *
   * @return string[]
   */
  private function catalogExtensions(?string $consumer, string $catalog): array {
    $configured = (string) ($this->settings($consumer)['allowed_extensions'] ?? '');
    $requested = preg_split('/\s+/', strtolower(trim($configured))) ?: [];
    return array_values(array_intersect(explode(' ', $catalog), $requested));
  }

  /**
   * Valida um arquivo contra a lista de extensões, tamanho e conteúdo.
   *
   * @param string[] $extensions
   */
  private function isValidFile(FileInterface $file, array $extensions, ?string $consumer): bool {
    $extension = strtolower(pathinfo($file->getFilename(), PATHINFO_EXTENSION));
    if (!in_array($extension, $extensions, TRUE)) {
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
