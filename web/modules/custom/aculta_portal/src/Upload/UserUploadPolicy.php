<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\Upload;

use Drupal\aculta_image\ImageInspector;
use Drupal\aculta_image\UploadPolicy as ImageUploadPolicy;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\file\FileInterface;

/**
 * Shared policy for files uploaded by authenticated Portal users.
 *
 * Consumers retain ownership of the uploaded entity and its publication rules;
 * this service supplies the common extension, size and raster-image checks.
 */
final class UserUploadPolicy {

  public const DEFAULT_EXTENSIONS = 'png jpg jpeg gif webp pdf docx xls xlsx';

  public const SUPPORTED_EXTENSIONS = [
    'png', 'jpg', 'jpeg', 'gif', 'webp', 'pdf', 'docx', 'xls', 'xlsx',
  ];

  public function __construct(
    private readonly ConfigFactoryInterface $configFactory,
    private readonly ImageUploadPolicy $imagePolicy,
    private readonly ImageInspector $imageInspector,
  ) {}

  /**
   * Returns the normalized extensions enabled by the Portal administrator. */
  public function allowedExtensions(?string $consumer = NULL): array {
    $configured = $this->settings($consumer)['allowed_extensions'] ?? self::DEFAULT_EXTENSIONS;
    $extensions = $this->normalizeExtensions((string) $configured);
    if ($extensions !== NULL) {
      return $extensions;
    }

    if ($consumer !== NULL) {
      $global = $this->normalizeExtensions((string) ($this->configFactory->get('aculta_portal.user_upload')->get('allowed_extensions') ?? self::DEFAULT_EXTENSIONS));
      if ($global !== NULL) {
        return $global;
      }
    }
    return explode(' ', self::DEFAULT_EXTENSIONS);
  }

  /**
   * Returns the configured per-file limit in bytes (10 MiB by default). */
  public function maxBytes(?string $consumer = NULL): int {
    $megabytes = (int) ($this->settings($consumer)['max_size_mb'] ?? 10);
    if ($megabytes < 1 || $megabytes > intdiv(PHP_INT_MAX, 1048576)) {
      $megabytes = $consumer !== NULL
        ? (int) ($this->configFactory->get('aculta_portal.user_upload')->get('max_size_mb') ?? 10)
        : 10;
    }
    if ($megabytes < 1 || $megabytes > intdiv(PHP_INT_MAX, 1048576)) {
      $megabytes = 10;
    }
    return $megabytes * 1048576;
  }

  /**
   * Returns enabled raster extensions accepted by aculta_image. */
  public function imageExtensions(?string $consumer = NULL): string {
    $extensions = array_intersect($this->allowedExtensions($consumer), explode(' ', ImageUploadPolicy::EXTENSIONS));
    if ($extensions === []) {
      return '';
    }
    $extensions = implode(' ', $extensions);
    $this->imagePolicy->validators($this->maxBytes($consumer), $extensions);
    return $extensions;
  }

  /**
   * Returns File API validators for an image upload. */
  public function imageValidators(?string $consumer = NULL): array {
    $extensions = $this->imageExtensions($consumer);
    return $extensions === '' ? [] : $this->imagePolicy->validators($this->maxBytes($consumer), $extensions) + ['FileIsImage' => []];
  }

  /**
   * Confirms extension, size and decoded raster dimensions through aculta_image. */
  public function isValidImage(FileInterface $file, ?string $consumer = NULL): bool {
    return in_array(strtolower(pathinfo($file->getFilename(), PATHINFO_EXTENSION)), explode(' ', $this->imageExtensions($consumer)), TRUE)
      && $file->getSize() > 0
      && $file->getSize() <= $this->maxBytes($consumer)
      && $this->imageInspector->dimensions($file->getFileUri()) !== NULL;
  }

  /**
   * Confirms an enabled document extension and the consumer's size limit. */
  public function isValidDocument(FileInterface $file, ?string $consumer = NULL): bool {
    return in_array(strtolower(pathinfo($file->getFilename(), PATHINFO_EXTENSION)), explode(' ', $this->documentExtensions($consumer)), TRUE)
      && $file->getSize() > 0
      && $file->getSize() <= $this->maxBytes($consumer);
  }

  /**
   * Returns enabled non-image document extensions. */
  public function documentExtensions(?string $consumer = NULL): string {
    return implode(' ', array_diff($this->allowedExtensions($consumer), explode(' ', ImageUploadPolicy::EXTENSIONS)));
  }

  /**
   * Returns File API validators for a non-image file upload. */
  public function documentValidators(?string $consumer = NULL): array {
    $extensions = $this->documentExtensions($consumer);
    return $extensions === '' ? [] : [
      'FileExtension' => ['extensions' => $extensions],
      'FileSizeLimit' => ['fileLimit' => $this->maxBytes($consumer)],
    ];
  }

  /**
   * Caps an integration's File API validators to the consumer policy.
   *
   * The consumer may narrow the policy but never widen it: extensions outside
   * its allowlist are dropped and the size limit can only go down. Callers keep
   * their own extra validators, such as aculta_image's raster constraint.
   */
  public function restrictValidators(array $validators, ?string $consumer = NULL): array {
    $allowed = $this->allowedExtensions($consumer);
    $requested = preg_split('/[\s,]+/', strtolower(trim((string) ($validators['FileExtension']['extensions'] ?? implode(' ', $allowed))))) ?: [];
    $validators['FileExtension'] = ['extensions' => implode(' ', array_values(array_intersect($requested, $allowed)))];

    $limit = $this->maxBytes($consumer);
    $validators['FileSizeLimit'] = ['fileLimit' => min((int) ($validators['FileSizeLimit']['fileLimit'] ?? $limit), $limit)];
    return $validators;
  }

  /**
   * Returns the extensions a restricted validator set accepts, for display.
   *
   * Unlike allowedExtensions(), this reflects the intersection between the
   * consumer allowlist and what the calling activity asked for, so help text
   * never advertises an extension the upload would reject.
   */
  public function acceptedExtensions(array $validators): string {
    $extensions = (string) ($validators['FileExtension']['extensions'] ?? '');
    return implode(' ', preg_split('/[\s,]+/', strtolower(trim($extensions)), -1, PREG_SPLIT_NO_EMPTY) ?: []);
  }

  /**
   * Resolves consumer overrides, falling back to the site-wide policy. */
  private function settings(?string $consumer): array {
    $config = $this->configFactory->get('aculta_portal.user_upload');
    if ($consumer !== NULL) {
      $override = $config->get('consumers.' . $consumer);
      if (is_array($override) && empty($override['inherit_global'])) {
        return $override + [
          'allowed_extensions' => $config->get('allowed_extensions'),
          'max_size_mb' => $config->get('max_size_mb'),
        ];
      }
    }
    return [
      'allowed_extensions' => $config->get('allowed_extensions'),
      'max_size_mb' => $config->get('max_size_mb'),
    ];
  }

  /**
   * Returns a configured extension list only when every entry is supported. */
  private function normalizeExtensions(string $configured): ?array {
    $extensions = preg_split('/\s+/', strtolower(trim($configured))) ?: [];
    $extensions = array_values(array_unique(array_filter($extensions)));
    return $extensions !== [] && array_diff($extensions, self::SUPPORTED_EXTENSIONS) === []
      ? $extensions
      : NULL;
  }

}
