<?php

declare(strict_types=1);

namespace Drupal\aculta_image;

use Composer\InstalledVersions;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\File\FileExists;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\file\FileUsage\FileUsageInterface;
use Drupal\file\FileRepositoryInterface;

/** Publishes immutable, reviewed Composer image assets through aculta_image. */
final class PackagedAssetPublisher {

  public function __construct(
    private readonly FileRepositoryInterface $fileRepository,
    private readonly FileSystemInterface $fileSystem,
    private readonly Connection $database,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly FileUsageInterface $fileUsage,
    private readonly ImageInspector $inspector,
    private readonly TimeInterface $time,
    private readonly LoggerChannelFactoryInterface $loggerFactory,
  ) {}

  /**
   * Publishes one static dependency asset at its stable public URI.
   *
   * The caller must supply a source from a pinned Composer package. Existing
   * destinations are immutable: identical bytes are reused and changed bytes
   * are rejected until the destination is explicitly reviewed and replaced.
   */
  public function publish(string $source, string $destination, string $origin): bool {
    $sourcePath = realpath($source);
    $destinationPath = $this->safeDestination($destination);
    if ($sourcePath === FALSE || !is_file($sourcePath) || !is_readable($sourcePath)
      || (int) filesize($sourcePath) > UploadPolicy::MAX_BYTES
      || $destinationPath === NULL || !$this->trustedH5pSource($sourcePath, $origin)) {
      return FALSE;
    }
    $bytes = file_get_contents($sourcePath);
    if (!is_string($bytes) || ($bytes = $this->validatedBytes($bytes, $destination)) === NULL) {
      $this->loggerFactory->get('aculta_image')->warning('Asset de imagem recusado: {destination}', ['destination' => $destination]);
      return FALSE;
    }

    $sha = hash('sha256', $bytes);
    $existing = $this->fileSystem->realpath($destination);
    if ($existing !== FALSE) {
      if (!hash_equals($sha, hash_file('sha256', $existing) ?: '')) {
        $this->loggerFactory->get('aculta_image')->error('Asset imutável divergiu da origem Composer: {destination}', ['destination' => $destination]);
        return FALSE;
      }
      $file = $this->entityTypeManager->getStorage('file')->loadByProperties(['uri' => $destination]);
      $file = reset($file);
      if (!$file) {
        return FALSE;
      }
      $this->record($file->id(), $sha, $origin);
      $this->fileUsage->add($file, 'aculta_image', 'h5p_composer_asset', 'pinned_assets');
      return TRUE;
    }

    $directory = dirname($destination);
    if (!$this->fileSystem->prepareDirectory($directory, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS)) {
      return FALSE;
    }
    $file = $this->fileRepository->writeData($bytes, $destination, FileExists::Error);
    if ($file === FALSE) {
      return FALSE;
    }
    $file->setPermanent();
    $file->save();
    $this->record((int) $file->id(), $sha, $origin);
    $this->fileUsage->add($file, 'aculta_image', 'h5p_composer_asset', 'pinned_assets');
    return TRUE;
  }

  /** Publishes an image from an H5P package into private managed storage. */
  public function publishH5PLibraryImage(string $source, string $destination, string $packageRoot, string $origin): bool {
    $sourcePath = realpath($source);
    $rootPath = realpath($packageRoot);
    if ($sourcePath === FALSE || $rootPath === FALSE || !str_starts_with($sourcePath, $rootPath . DIRECTORY_SEPARATOR)
      || !is_file($sourcePath) || !is_readable($sourcePath) || !preg_match('@^private://(?:[A-Za-z0-9_-]+/)*libraries/.+$@D', $destination)) {
      return FALSE;
    }
    $relative = preg_replace('@^private://(?:[A-Za-z0-9_-]+/)*libraries/@', '', $destination);
    if (!is_string($relative)) {
      return FALSE;
    }
    if ($relative === '' || str_contains($relative, '\\') || preg_match('@(?:^|/)\.\.?(/|$)|[^\pL\pN._/-]@u', $relative)) {
      return FALSE;
    }
    $bytes = file_get_contents($sourcePath);
    if (!is_string($bytes) || strlen($bytes) > UploadPolicy::MAX_BYTES
      || ($bytes = $this->validatedBytes($bytes, $destination)) === NULL) {
      return FALSE;
    }
    $sha = hash('sha256', $bytes);
    $existingPath = $this->fileSystem->realpath($destination);
    if ($existingPath !== FALSE) {
      if (!hash_equals($sha, hash_file('sha256', $existingPath) ?: '')) {
        return FALSE;
      }
      $files = $this->entityTypeManager->getStorage('file')->loadByProperties(['uri' => $destination]);
      $file = reset($files);
      if (!$file) {
        return FALSE;
      }
    }
    else {
      if (!$this->fileSystem->prepareDirectory(dirname($destination), FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS)) {
        return FALSE;
      }
      $file = $this->fileRepository->writeData($bytes, $destination, FileExists::Error);
      if ($file === FALSE) {
        return FALSE;
      }
      $file->setPermanent();
      $file->save();
    }
    $this->record((int) $file->id(), $sha, $origin);
    $usageId = hash('sha256', dirname($relative));
    $usage = $this->fileUsage->listUsage($file);
    if (empty($usage['aculta_image']['h5p_library_asset'][$usageId])) {
      $this->fileUsage->add($file, 'aculta_image', 'h5p_library_asset', $usageId);
    }
    return TRUE;
  }

  /** Verifies provenance against the two pinned H5P Composer package roots. */
  private function trustedH5pSource(string $sourcePath, string $origin): bool {
    if (!preg_match('@^composer://h5p/(h5p-core|h5p-editor)/([A-Za-z0-9._/-]+)$@D', $origin, $matches)
      || preg_match('@(?:^|/)\.{1,2}(?:/|$)@', $matches[2])) {
      return FALSE;
    }
    $root = InstalledVersions::getInstallPath('h5p/' . $matches[1]);
    $realRoot = $root === NULL ? FALSE : realpath($root);
    if ($realRoot === FALSE || !str_starts_with($sourcePath, $realRoot . DIRECTORY_SEPARATOR)) {
      return FALSE;
    }
    return hash_equals(str_replace(DIRECTORY_SEPARATOR, '/', substr($sourcePath, strlen($realRoot) + 1)), $matches[2]);
  }

  private function safeDestination(string $destination): ?string {
    if (!str_starts_with($destination, 'public://h5p/')) {
      return NULL;
    }
    $path = substr($destination, strlen('public://h5p/'));
    if ($path === '' || str_contains($path, '\\') || preg_match('@(?:^|/)\.{1,2}(?:/|$)|[^A-Za-z0-9._/-]@', $path)) {
      return NULL;
    }
    $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    return in_array($extension, [...explode(' ', UploadPolicy::EXTENSIONS), 'svg'], TRUE) ? $destination : NULL;
  }

  private function validatedBytes(string $bytes, string $destination): ?string {
    if ($bytes === '' || strlen($bytes) > UploadPolicy::MAX_BYTES) {
      return NULL;
    }
    $extension = strtolower(pathinfo($destination, PATHINFO_EXTENSION));
    if ($extension === 'svg') {
      // H5P's pinned SVGs carry the standard external SVG 1.1 doctype. Strip
      // only that declaration before publication; reject custom DTD/entities.
      $bytes = preg_replace(
        '~<!DOCTYPE\s+svg\s+PUBLIC\s+["\']-//W3C//DTD SVG 1\.1//EN["\']\s+["\']http://www\.w3\.org/Graphics/SVG/1\.1/DTD/svg11\.dtd["\']\s*>~i',
        '',
        $bytes,
        1,
      ) ?? $bytes;
      return $this->validSvg($bytes) ? $bytes : NULL;
    }
    $image = $this->inspector->fromBytes($bytes);
    $extension = strtolower(pathinfo($destination, PATHINFO_EXTENSION));
    $extensions = match ($image['mime'] ?? '') {
      'image/png' => ['png'],
      'image/jpeg' => ['jpg', 'jpeg'],
      'image/gif' => ['gif'],
      'image/webp' => ['webp'],
      default => [],
    };
    return ImageIngester::extensaoAceita($destination) && in_array($extension, $extensions, TRUE) ? $bytes : NULL;
  }

  /** Rejects active SVG markup; these files are pinned package assets, not user uploads. */
  private function validSvg(string $bytes): bool {
    if (preg_match('/<!DOCTYPE|<!ENTITY/i', $bytes)) {
      return FALSE;
    }
    $previous = libxml_use_internal_errors(TRUE);
    try {
      $document = new \DOMDocument();
      if (!$document->loadXML($bytes, LIBXML_NONET | LIBXML_NOBLANKS) || $document->doctype !== NULL || $document->documentElement?->localName !== 'svg') {
        return FALSE;
      }
      $allowedElements = ['svg', 'g', 'path', 'metadata', 'json', 'defs', 'font', 'font-face', 'missing-glyph', 'glyph'];
      foreach ($document->getElementsByTagName('*') as $element) {
        if (!in_array($element->localName, $allowedElements, TRUE)) {
          return FALSE;
        }
        foreach ($element->attributes as $attribute) {
          $name = strtolower($attribute->nodeName);
          $value = trim($attribute->nodeValue ?? '');
          if (str_starts_with($name, 'on') || in_array($name, ['href', 'xlink:href', 'style'], TRUE)
            || preg_match('/(?:javascript:|https?:|\/\/|url\s*\()/i', $value)) {
            return FALSE;
          }
        }
      }
      return TRUE;
    }
    finally {
      libxml_clear_errors();
      libxml_use_internal_errors($previous);
    }
  }

  private function record(int $fid, string $sha, string $origin): void {
    $exists = $this->database->select('aculta_image_origin', 'o')
      ->fields('o', ['id'])
      ->condition('o.fid', $fid)
      ->condition('o.sha256', $sha)
      ->condition('o.source_url', mb_substr($origin, 0, 2048))
      ->range(0, 1)
      ->execute()
      ->fetchField();
    if ($exists !== FALSE) {
      return;
    }
    $this->database->insert('aculta_image_origin')->fields([
      'fid' => $fid,
      'sha256' => $sha,
      'source_url' => mb_substr($origin, 0, 2048),
      'captured_at' => (int) $this->time->getRequestTime(),
      'imported_at' => (int) $this->time->getRequestTime(),
    ])->execute();
  }

}
