<?php

declare(strict_types=1);

namespace Drupal\aculta_pdf_viewer\Pdf;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\file\FileInterface;
use Drupal\media\MediaInterface;

/**
 * Builds an access-checked viewer using only local public Media PDF files.
 */
final class PdfViewerBuilder implements PdfViewerBuilderInterface {

  public function __construct(
    private readonly FileUrlGeneratorInterface $fileUrlGenerator,
    private readonly AccountInterface $currentUser,
    private readonly PdfPreviewThumbnailBuilderInterface $thumbnailBuilder,
  ) {}

  /**
   * {@inheritdoc}
   */
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
    $file_download_access = $file->access('download', $this->currentUser, TRUE);
    $cacheability->addCacheableDependency($file_download_access);
    $uri = $file->getFileUri();
    $url = $file->isPermanent() && $file_access->isAllowed() && $file_download_access->isAllowed() && str_starts_with($uri, 'public://')
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
        'previous' => [
          '#type' => 'html_tag',
          '#tag' => 'button',
          '#value' => new TranslatableMarkup('Página anterior'),
          '#attributes' => [
            'type' => 'button',
            'data-aculta-pdf-control' => 'previous',
            'disabled' => 'disabled',
          ],
        ],
        'status' => [
          '#type' => 'html_tag',
          '#tag' => 'span',
          '#value' => new TranslatableMarkup('Carregando PDF…'),
          '#attributes' => [
            'data-aculta-pdf-control' => 'status',
            'aria-live' => 'polite',
          ],
        ],
        'next' => [
          '#type' => 'html_tag',
          '#tag' => 'button',
          '#value' => new TranslatableMarkup('Próxima página'),
          '#attributes' => [
            'type' => 'button',
            'data-aculta-pdf-control' => 'next',
            'disabled' => 'disabled',
          ],
        ],
      ],
      'reader_controls' => [
        '#type' => 'container',
        '#attributes' => ['data-aculta-pdf-control' => 'reader-controls'],
        'zoom_out' => [
          '#type' => 'html_tag',
          '#tag' => 'button',
          '#value' => new TranslatableMarkup('Reduzir zoom'),
          '#attributes' => [
            'type' => 'button',
            'data-aculta-pdf-control' => 'zoom-out',
            'aria-label' => new TranslatableMarkup('Reduzir zoom'),
          ],
        ],
        'zoom_status' => [
          '#type' => 'html_tag',
          '#tag' => 'span',
          '#value' => '100%',
          '#attributes' => ['data-aculta-pdf-control' => 'zoom-status', 'aria-live' => 'polite'],
        ],
        'zoom_in' => [
          '#type' => 'html_tag',
          '#tag' => 'button',
          '#value' => new TranslatableMarkup('Aumentar zoom'),
          '#attributes' => [
            'type' => 'button',
            'data-aculta-pdf-control' => 'zoom-in',
            'aria-label' => new TranslatableMarkup('Aumentar zoom'),
          ],
        ],
        'zoom_reset' => [
          '#type' => 'html_tag',
          '#tag' => 'button',
          '#value' => new TranslatableMarkup('Restaurar zoom'),
          '#attributes' => [
            'type' => 'button',
            'data-aculta-pdf-control' => 'zoom-reset',
          ],
        ],
        'fullscreen' => [
          '#type' => 'html_tag',
          '#tag' => 'button',
          '#value' => new TranslatableMarkup('Tela cheia'),
          '#attributes' => [
            'type' => 'button',
            'data-aculta-pdf-control' => 'fullscreen',
            'aria-label' => new TranslatableMarkup('Abrir leitor em tela cheia'),
            'aria-pressed' => 'false',
          ],
        ],
      ],
      'search_controls' => [
        '#type' => 'container',
        '#attributes' => ['data-aculta-pdf-control' => 'search-controls'],
        'input' => [
          '#type' => 'html_tag',
          '#tag' => 'input',
          '#attributes' => [
            'type' => 'search',
            'data-aculta-pdf-control' => 'search-input',
            'aria-label' => new TranslatableMarkup('Buscar texto no PDF'),
          ],
        ],
        'submit' => [
          '#type' => 'html_tag',
          '#tag' => 'button',
          '#value' => new TranslatableMarkup('Buscar'),
          '#attributes' => [
            'type' => 'button',
            'data-aculta-pdf-control' => 'search-submit',
          ],
        ],
        'previous' => [
          '#type' => 'html_tag',
          '#tag' => 'button',
          '#value' => new TranslatableMarkup('Resultado anterior'),
          '#attributes' => [
            'type' => 'button',
            'data-aculta-pdf-control' => 'search-previous',
            'disabled' => 'disabled',
          ],
        ],
        'next' => [
          '#type' => 'html_tag',
          '#tag' => 'button',
          '#value' => new TranslatableMarkup('Próximo resultado'),
          '#attributes' => [
            'type' => 'button',
            'data-aculta-pdf-control' => 'search-next',
            'disabled' => 'disabled',
          ],
        ],
        'status' => [
          '#type' => 'html_tag',
          '#tag' => 'span',
          '#attributes' => [
            'data-aculta-pdf-control' => 'search-status',
            'aria-live' => 'polite',
          ],
        ],
      ],
      'page' => [
        '#type' => 'html_tag',
        '#tag' => 'canvas',
        '#attributes' => [
          'data-aculta-pdf-control' => 'page',
          'aria-label' => new TranslatableMarkup('Página do documento'),
        ],
      ],
      'download' => [
        '#type' => 'html_tag',
        '#tag' => 'a',
        '#value' => new TranslatableMarkup('Abrir ou baixar PDF'),
        '#attributes' => ['href' => $url, 'data-aculta-pdf-control' => 'download'],
      ],
      '#attached' => ['library' => ['aculta_pdf_viewer/viewer']],
    ];
    $thumbnail = $this->thumbnailBuilder->build($file, $title . ' — miniatura da primeira página');
    if ($thumbnail !== NULL) {
      $build['thumbnail'] = [
        '#type' => 'container',
        '#attributes' => ['data-aculta-pdf-control' => 'thumbnail-container'],
        '#weight' => -10,
        'image' => $thumbnail,
      ];
    }
    $cacheability->applyTo($build);
    return $build;
  }

  /**
   * Returns an empty render array with the dependencies that decided access.
   */
  private function denied(CacheableMetadata $cacheability): array {
    $build = ['#access' => FALSE];
    $cacheability->applyTo($build);
    return $build;
  }

}
