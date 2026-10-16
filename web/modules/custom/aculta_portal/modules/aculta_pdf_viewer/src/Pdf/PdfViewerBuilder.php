<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\Pdf;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\file\FileInterface;
use Drupal\media\MediaInterface;

/** Builds an access-checked viewer using only local public Media PDF files. */
final class PdfViewerBuilder implements PdfViewerBuilderInterface {

  public function __construct(
    private readonly FileUrlGeneratorInterface $fileUrlGenerator,
    private readonly AccountInterface $currentUser,
  ) {}

  /** {@inheritdoc} */
  public function build(MediaInterface $media, string $field_name = 'field_media_document'): array {
    $cacheability = CacheableMetadata::createFromObject($media);
    $media_access = $media->access('view', $this->currentUser, TRUE);
    $cacheability->addCacheableDependency($media_access);

    if (!$media_access->isAllowed() || $media->bundle() !== 'document' || !$media->hasField($field_name)) {
      return $this->denied($cacheability);
    }

    $field = $media->get($field_name);
    $field_access = $field->access('view', $this->currentUser, TRUE);
    $cacheability->addCacheableDependency($field_access);
    if (!$field_access->isAllowed() || $field->isEmpty()) {
      return $this->denied($cacheability);
    }

    $file = $field->entity;
    if (!$file instanceof FileInterface) {
      return $this->denied($cacheability);
    }

    $cacheability->addCacheableDependency($file);
    $file_access = $file->access('view', $this->currentUser, TRUE);
    $cacheability->addCacheableDependency($file_access);
    $uri = $file->getFileUri();
    $url = $file->isPermanent() && $file_access->isAllowed() && str_starts_with($uri, 'public://')
      ? $this->fileUrlGenerator->generateString($uri)
      : '';

    // A generated local URL is required. Reject remote stream wrappers/CDNs so
    // the browser never receives an arbitrary or third-party PDF URL.
    $parts = parse_url($url);
    if ($url === '' || !is_array($parts) || isset($parts['scheme']) || isset($parts['host']) || !str_starts_with($url, '/')) {
      return $this->denied($cacheability);
    }

    $mime = strtolower((string) $file->getMimeType());
    $extension = strtolower(pathinfo($file->getFilename(), PATHINFO_EXTENSION));
    if ($mime !== 'application/pdf' || $extension !== 'pdf') {
      return $this->denied($cacheability);
    }

    $title = $media->label() ?: $file->getFilename();
    $build = [
      '#type' => 'container',
      '#attributes' => [
        'data-aculta-pdf-viewer' => 'true',
        'role' => 'region',
        'aria-label' => $title,
        'data-aculta-pdf-url' => $url,
        'data-aculta-pdf-title' => $title,
      ],
      'toolbar' => [
        '#type' => 'container',
        '#attributes' => ['data-aculta-pdf-control' => 'toolbar'],
        'previous' => ['#type' => 'html_tag', '#tag' => 'button', '#value' => new TranslatableMarkup('Página anterior'), '#attributes' => ['type' => 'button', 'data-aculta-pdf-control' => 'previous', 'disabled' => 'disabled']],
        'status' => ['#type' => 'html_tag', '#tag' => 'span', '#value' => new TranslatableMarkup('Carregando PDF…'), '#attributes' => ['data-aculta-pdf-control' => 'status', 'aria-live' => 'polite']],
        'next' => ['#type' => 'html_tag', '#tag' => 'button', '#value' => new TranslatableMarkup('Próxima página'), '#attributes' => ['type' => 'button', 'data-aculta-pdf-control' => 'next', 'disabled' => 'disabled']],
      ],
      'page' => ['#type' => 'html_tag', '#tag' => 'canvas', '#attributes' => ['data-aculta-pdf-control' => 'page', 'aria-label' => new TranslatableMarkup('Página do documento')]],
      'download' => [
        '#type' => 'html_tag',
        '#tag' => 'a',
        '#value' => new TranslatableMarkup('Abrir ou baixar PDF'),
        '#attributes' => ['href' => $url, 'data-aculta-pdf-control' => 'download'],
      ],
      '#attached' => ['library' => ['aculta_portal/pdf_viewer']],
    ];
    $cacheability->applyTo($build);
    return $build;
  }

  /** Returns an empty render array with the dependencies that decided access. */
  private function denied(CacheableMetadata $cacheability): array {
    $build = ['#access' => FALSE];
    $cacheability->applyTo($build);
    return $build;
  }

}
