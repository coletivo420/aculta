<?php

declare(strict_types=1);

namespace Drupal\aculta_pdf_viewer\Pdf;

use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\Core\Routing\UrlGeneratorInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\file\FileInterface;
use Drupal\media\MediaInterface;

/**
 * Builds an access-checked viewer using local public or private Media PDFs.
 */
final class PdfViewerBuilder implements PdfViewerBuilderInterface {

  public function __construct(
    private readonly FileUrlGeneratorInterface $fileUrlGenerator,
    private readonly UrlGeneratorInterface $urlGenerator,
    private readonly AccountInterface $currentUser,
    private readonly PdfPreviewThumbnailBuilderInterface $thumbnailBuilder,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function build(MediaInterface $media, string $field_name = 'field_media_document', array $settings = []): array {
    $cacheability = CacheableMetadata::createFromObject($media);
    $media_access = $media->access('view', $this->currentUser, TRUE);
    $cacheability->addCacheableDependency($media_access);

    $bundle = $media->bundle();
    $expected_field = DocumentFieldMap::fieldForBundle($bundle);
    if (!$media_access->isAllowed() || $expected_field === NULL || $field_name !== $expected_field || !$media->hasField($field_name)) {
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

    $expected_scheme = $bundle === 'course_document' ? 'private' : 'public';
    return $this->buildFile($file, $media->label() ?: $file->getFilename(), $expected_scheme, $cacheability, TRUE, $settings);
  }

  /**
   * {@inheritdoc}
   */
  public function buildFile(FileInterface $file, string $title, string $expected_scheme, CacheableMetadata $cacheability, bool $require_file_view_access = FALSE, array $settings = []): array {
    $cacheability->addCacheableDependency($file);
    $file_access = $require_file_view_access
      ? $file->access('view', $this->currentUser, TRUE)
      : AccessResult::allowed();
    $cacheability->addCacheableDependency($file_access);
    $file_download_access = $file->access('download', $this->currentUser, TRUE);
    $cacheability->addCacheableDependency($file_download_access);
    $uri = $file->getFileUri();
    $scheme = parse_url($uri, PHP_URL_SCHEME);
    $url = '';
    if (
      $file->isPermanent()
      && $file_access->isAllowed()
      && $file_download_access->isAllowed()
      && $scheme === $expected_scheme
      && in_array($scheme, ['public', 'private'], TRUE)
    ) {
      $url = $scheme === 'private'
        ? $this->privateFileUrl($uri)
        : $this->fileUrlGenerator->generateString($uri);
    }

    // A generated local URL is required. Reject remote stream wrappers/CDNs so
    // the browser never receives an arbitrary or third-party PDF URL.
    $parts = parse_url($url);
    if ($url === '' || !is_array($parts) || isset($parts['scheme']) || isset($parts['host']) || !str_starts_with($url, '/')) {
      return $this->denied($cacheability);
    }
    if ($scheme === 'private') {
      // Core rechecks File references and field/entity access on each private
      // file request. Avoid caching a page that contains this protected URL.
      $cacheability->setCacheMaxAge(0);
    }

    $mime = strtolower((string) $file->getMimeType());
    $extension = strtolower(pathinfo($file->getFilename(), PATHINFO_EXTENSION));
    if ($mime !== 'application/pdf' || $extension !== 'pdf') {
      return $this->denied($cacheability);
    }

    $title = trim((string) ($settings['embed_title'] ?? '')) ?: (trim($title) ?: $file->getFilename());
    $defaults = [
      'show_download' => TRUE, 'show_print' => TRUE, 'show_zoom' => TRUE,
      'show_fullscreen' => TRUE, 'show_search' => TRUE, 'show_page_navigation' => TRUE,
      'show_rotation' => TRUE,
      'show_outline' => TRUE,
      'show_full_viewer' => TRUE, 'default_zoom' => 'fit-width', 'default_scroll' => 'vertical',
      'double_page' => FALSE, 'double_page_mobile' => FALSE, 'language' => 'pt-BR',
      'link_text' => '', 'link_target' => '_blank', 'css_class' => '',
    ];
    $settings = array_replace($defaults, $settings);
    $class = preg_replace('/[^a-zA-Z0-9_ -]/', '', (string) $settings['css_class']);
    $build = [
      '#type' => 'container',
      '#attributes' => [
        'data-aculta-pdf-viewer' => 'true',
        'role' => 'region',
        'aria-label' => $title,
        'data-aculta-pdf-url' => $url,
        'data-aculta-pdf-title' => $title,
        'data-aculta-pdf-default-zoom' => $settings['default_zoom'],
        'data-aculta-pdf-default-scroll' => $settings['default_scroll'],
        'data-aculta-pdf-double-page' => $settings['double_page'] ? 'true' : 'false',
        'data-aculta-pdf-double-page-mobile' => $settings['double_page_mobile'] ? 'true' : 'false',
        'data-aculta-pdf-show-zoom' => $settings['show_zoom'] ? 'true' : 'false',
        'data-aculta-pdf-show-fullscreen' => $settings['show_fullscreen'] ? 'true' : 'false',
        'data-aculta-pdf-show-search' => $settings['show_search'] ? 'true' : 'false',
        'data-aculta-pdf-show-page-navigation' => $settings['show_page_navigation'] ? 'true' : 'false',
        'class' => array_filter(['aculta-pdf-viewer', $class]),
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
        'zoom' => [
          '#type' => 'container',
          '#attributes' => ['data-aculta-pdf-control' => 'zoom-controls'],
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
          ],
        'rotate' => [
          '#type' => 'html_tag',
          '#tag' => 'button',
          '#value' => new TranslatableMarkup('Girar página 90 graus'),
          '#attributes' => [
            'type' => 'button',
            'data-aculta-pdf-control' => 'rotate',
            'aria-label' => new TranslatableMarkup('Girar página 90 graus'),
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
      'outline' => [
        '#type' => 'details',
        '#title' => new TranslatableMarkup('Contorno do documento'),
        '#attributes' => ['data-aculta-pdf-control' => 'outline'],
        'items' => [
          '#type' => 'html_tag',
          '#tag' => 'ul',
          '#attributes' => ['data-aculta-pdf-control' => 'outline-items'],
        ],
        'status' => [
          '#type' => 'html_tag',
          '#tag' => 'p',
          '#value' => new TranslatableMarkup('Este PDF não contém contorno.'),
          '#attributes' => ['data-aculta-pdf-control' => 'outline-status', 'aria-live' => 'polite'],
        ],
      ],
      'page_stage' => [
        '#type' => 'container',
        '#attributes' => ['data-aculta-pdf-control' => 'page-stage'],
        'page' => [
          '#type' => 'html_tag',
          '#tag' => 'canvas',
          '#attributes' => [
            'data-aculta-pdf-control' => 'page',
            'aria-label' => new TranslatableMarkup('Página do documento'),
            'hidden' => 'hidden',
          ],
        ],
      ],
      'download' => [
        '#type' => 'html_tag',
        '#tag' => 'a',
        '#value' => trim((string) $settings['link_text']) ?: new TranslatableMarkup('Abrir ou baixar PDF'),
        '#attributes' => ['href' => $url, 'data-aculta-pdf-control' => 'download'],
      ],
      'print' => [
        '#type' => 'html_tag',
        '#tag' => 'a',
        '#value' => new TranslatableMarkup('Abrir leitor completo para imprimir'),
        '#attributes' => [
          'href' => '/libraries/pdf.js/web/viewer.html?' . UrlHelper::buildQuery(['file' => $url, 'locale' => $settings['language']]),
          'target' => '_blank',
          'rel' => 'noopener noreferrer',
          'data-aculta-pdf-control' => 'print',
        ],
      ],
      'full_viewer' => [
        '#type' => 'html_tag',
        '#tag' => 'a',
        '#value' => new TranslatableMarkup('Abrir leitor completo do PDF (nova aba)'),
        '#attributes' => [
          'href' => '/libraries/pdf.js/web/viewer.html?' . UrlHelper::buildQuery([
            'file' => $url,
            'locale' => $settings['language'],
          ]),
          'target' => in_array($settings['link_target'], ['_blank', '_self'], TRUE) ? $settings['link_target'] : '_blank',
          'rel' => $settings['link_target'] === '_blank' ? 'noopener noreferrer' : NULL,
          'data-aculta-pdf-control' => 'full-viewer',
        ],
      ],
      '#attached' => ['library' => ['aculta_pdf_viewer/viewer']],
    ];
    if (empty($settings['show_download'])) {
      $build['download']['#access'] = FALSE;
    }
    if (empty($settings['show_print'])) {
      $build['print']['#access'] = FALSE;
    }
    if (empty($settings['show_full_viewer'])) {
      $build['full_viewer']['#access'] = FALSE;
    }
    if (empty($settings['show_zoom'])) {
      $build['reader_controls']['zoom']['#access'] = FALSE;
    }
    if (empty($settings['show_fullscreen'])) {
      $build['reader_controls']['fullscreen']['#access'] = FALSE;
    }
    if (empty($settings['show_rotation'])) {
      $build['reader_controls']['rotate']['#access'] = FALSE;
    }
    if (empty($settings['show_outline'])) {
      $build['outline']['#access'] = FALSE;
    }
    if (empty($settings['show_search'])) {
      $build['search_controls']['#access'] = FALSE;
    }
    if (empty($settings['show_page_navigation'])) {
      $build['toolbar']['previous']['#access'] = FALSE;
      $build['toolbar']['next']['#access'] = FALSE;
    }
    $thumbnail = $scheme === 'public'
      ? $this->thumbnailBuilder->build($file, $title . ' — miniatura da primeira página')
      : NULL;
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

  /**
   * Generates a Core private-file URL with an explicit file query parameter.
   *
   * The Runtime's front controller does not resolve Core's path-processor
   * form `/system/files/{filepath}` reliably. The same Core route accepts
   * `?file={filepath}` and still rechecks the Media/File access on each request.
   */
  private function privateFileUrl(string $uri): string {
    $path = substr($uri, strlen('private://'));
    $url = Url::fromRoute('system.files', ['scheme' => 'private'], [
      'query' => ['file' => $path],
    ]);
    $url->setUrlGenerator($this->urlGenerator);
    return $url->toString();
  }

}
