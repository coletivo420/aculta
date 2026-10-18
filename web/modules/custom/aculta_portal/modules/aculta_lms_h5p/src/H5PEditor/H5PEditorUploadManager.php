<?php

declare(strict_types=1);

namespace Drupal\aculta_lms_h5p\H5PEditor;

use Drupal\aculta_image\ImageUploadIngestor;
use Drupal\aculta_image\ImageInspector;
use Drupal\aculta_lms_h5p\H5PDrupal\H5PDrupal;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\File\FileExists;
use Drupal\Core\File\FileSystemInterface;
use Drupal\file\Upload\FileUploadHandlerInterface;
use Drupal\file\Upload\FormUploadedFile;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/** Validates H5P editor media and writes uploads to managed private files. */
final class H5PEditorUploadManager {

  public function __construct(
    private readonly FileUploadHandlerInterface $uploadHandler,
    private readonly ImageUploadIngestor $imageUploadIngestor,
    private readonly ImageInspector $imageInspector,
    private readonly FileSystemInterface $fileSystem,
    private readonly ConfigFactoryInterface $configFactory,
    private readonly H5PDrupal $h5pFramework,
  ) {}

  /**
   * Stores one media upload and returns the JSON fields expected by H5P.
   *
   * @param array{type: string, mimes?: array, name?: string} $field
   *   The H5P semantic field describing the expected media.
   *
   * @return array<string, mixed>
   *   H5P upload result, or a safe error object.
   */
  public function upload(UploadedFile $upload, array $field): array {
    if (!$upload->isValid() || !isset($field['type']) || !is_string($field['type'])) {
      return $this->error('O arquivo enviado está ausente ou inválido.');
    }
    $type = $field['type'];
    $extension = strtolower(pathinfo($upload->getClientOriginalName(), PATHINFO_EXTENSION));
    $mimes = $field['mimes'] ?? [];
    if (!in_array($type, ['image', 'audio', 'video', 'file'], TRUE)
      || !is_array($mimes)
      || count($mimes) > 50
      || array_filter($mimes, static fn ($mime): bool => !is_string($mime) || !preg_match('@^[a-z0-9.+-]+/[a-z0-9.+*-]+$@iD', $mime))) {
      return $this->error('O campo de arquivo da atividade é inválido.');
    }
    $actualMime = $upload->getMimeType();
    if ($mimes !== [] && !in_array($actualMime, $mimes, TRUE)) {
      return $this->error('O tipo de mídia não corresponde ao campo da atividade.');
    }

    $imageExtensions = ['png', 'gif', 'jpg', 'jpeg', 'webp'];
    $imageLikeExtensions = [...$imageExtensions, 'svg', 'bmp', 'tif', 'tiff', 'ico'];
    if ($type === 'image' || str_starts_with((string) $actualMime, 'image/') || in_array($extension, $imageLikeExtensions, TRUE)) {
      if (!in_array($extension, $imageExtensions, TRUE)) {
        return $this->error('Este campo aceita somente imagens raster permitidas.');
      }
      $plural = 'images';
    }
    else {
      $plural = match ($type) {
        'audio' => 'audios',
        'video' => 'videos',
        default => 'files',
      };
      if (!$this->extensionAllowedForType($extension, $type)) {
        return $this->error('O formato do arquivo não é permitido para este campo.');
      }
    }

    $basePath = (string) ($this->configFactory->get('aculta_lms_h5p.settings')->get('h5p_default_path') ?? 'h5p');
    if (!preg_match('@^[a-z0-9_-]+(?:/[a-z0-9_-]+)*$@iD', $basePath)) {
      return $this->error('O armazenamento de atividades está indisponível.');
    }
    $destination = "private://{$basePath}/editor/{$plural}";
    if (!$this->fileSystem->prepareDirectory($destination, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS)) {
      return $this->error('Não foi possível preparar o armazenamento privado da atividade.');
    }

    try {
      $wrapped = new FormUploadedFile($upload);
      if ($plural === 'images') {
        $result = $this->imageUploadIngestor->upload($wrapped, $destination, $mimes);
      }
      else {
        // Core supplies its usual PHP upload-size bound and filename safety
        // checks alongside this type-specific H5P extension allowlist.
        $validators = (new H5PFileUploadValidators())->forExtensions($this->extensionsForType($type));
        $result = $this->uploadHandler->handleFileUpload($wrapped, $validators, $destination, FileExists::Rename);
      }
    }
    catch (\InvalidArgumentException $exception) {
      return $this->error($exception->getMessage());
    }
    catch (\Throwable) {
      return $this->error('O arquivo não pôde ser recebido com segurança.');
    }

    if ($result->hasViolations()) {
      return $this->error('O arquivo não atende aos requisitos deste campo.');
    }
    $file = $result->getFile();
    $name = $file->getFilename();
    $response = [
      'path' => "{$plural}/{$name}#tmp",
      'mime' => (string) $file->getMimeType(),
    ];
    if ($plural === 'images') {
      $info = $this->imageInspector->dimensions($file->getFileUri());
      if ($info === NULL) {
        return $this->error('O conteúdo enviado não é uma imagem válida.');
      }
      $response['width'] = (int) $info['width'];
      $response['height'] = (int) $info['height'];
    }
    return $response;
  }

  private function extensionAllowedForType(string $extension, string $type): bool {
    return in_array($extension, explode(' ', $this->extensionsForType($type)), TRUE);
  }

  private function extensionsForType(string $type): string {
    $h5pWhitelist = preg_split('/\s+/', trim((string) $this->h5pFramework->getWhitelist(FALSE, \H5PCore::$defaultContentWhitelist, \H5PCore::$defaultLibraryWhitelistExtras))) ?: [];
    $typeWhitelist = match ($type) {
      'audio' => ['mp3', 'm4a', 'wav', 'ogg'],
      'video' => ['mp4', 'webm', 'ogv'],
      default => $h5pWhitelist,
    };
    return implode(' ', array_values(array_intersect($typeWhitelist, $h5pWhitelist)));
  }

  private function error(string $message): array {
    return ['success' => FALSE, 'message' => $message];
  }

}
