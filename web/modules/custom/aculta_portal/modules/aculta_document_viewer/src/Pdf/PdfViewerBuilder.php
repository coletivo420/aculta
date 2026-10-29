<?php

declare(strict_types=1);

namespace Drupal\aculta_document_viewer\Pdf;

use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
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

  /**
   * Local wrapper that applies per-document defaults to the pinned PDF.js UI.
   */
  private const VIEWER_WRAPPER_PATH = '/modules/custom/aculta_portal/modules/aculta_pdf_viewer/viewer-wrapper.html';

  /**
   * Render defaults for a viewer, overridden by site and formatter settings.
   */
  private const DEFAULT_SETTINGS = [
    'show_download' => TRUE,
    'show_print' => TRUE,
    'show_zoom' => TRUE,
    'show_fullscreen' => TRUE,
    'show_search' => TRUE,
    'show_page_navigation' => TRUE,
    'show_rotation' => TRUE,
    'show_outline' => TRUE,
    'show_page_thumbnails' => TRUE,
    'show_full_viewer' => TRUE,
    'default_zoom' => 'fit-width',
    'default_scroll' => 'vertical',
    'use_site_settings' => TRUE,
    'double_page' => FALSE,
    'double_page_mobile' => FALSE,
    'language' => 'pt-BR',
    'link_text' => '',
    'link_target' => '_blank',
    'css_class' => '',
  ];

  public function __construct(
    private readonly FileUrlGeneratorInterface $fileUrlGenerator,
    private readonly UrlGeneratorInterface $urlGenerator,
    private readonly AccountInterface $currentUser,
    private readonly PdfPreviewThumbnailBuilderInterface $thumbnailBuilder,
    private readonly ?ConfigFactoryInterface $configFactory = NULL,
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

    $resolution = $this->resolveFileUrl($file, $expected_scheme, $require_file_view_access, $cacheability);
    if ($resolution === NULL) {
      return $this->denied($cacheability);
    }
    $url = $resolution['url'];
    $scheme = $resolution['scheme'];

    // Site settings are merged before MIME validation so a denied document
    // still carries the config dependency that took part in the decision.
    $settings = $this->mergeSettings($settings, $cacheability);
    if (!$this->isPdf($file)) {
      return $this->denied($cacheability);
    }

    $title = $this->resolveTitle($title, $file, $settings);
    $build = $this->buildViewer($url, $title, $settings);

    $thumbnail = $this->buildThumbnail($file, $scheme, $title);
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
   * Resolves the only URL forms the browser may receive for this file.
   *
   * @return array{url: string, scheme: string|false|null}|null
   *   The local URL and its stream scheme, or NULL when access is denied.
   */
  private function resolveFileUrl(FileInterface $file, string $expected_scheme, bool $require_file_view_access, CacheableMetadata $cacheability): ?array {
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
      return NULL;
    }

    if ($scheme === 'private') {
      // Core rechecks File references and field/entity access on each private
      // file request. Avoid caching a page that contains this protected URL.
      $cacheability->setCacheMaxAge(0);
    }

    return ['url' => $url, 'scheme' => $scheme];
  }

  /**
   * Applies site settings and the per-viewer defaults.
   */
  private function mergeSettings(array $settings, CacheableMetadata $cacheability): array {
    if (($settings['use_site_settings'] ?? TRUE) && $this->configFactory !== NULL) {
      $site_settings = $this->configFactory->get('aculta_document_viewer.settings');
      $cacheability->addCacheableDependency($site_settings);
      $settings = array_replace($settings, $site_settings->getRawData());
    }
    return array_replace(self::DEFAULT_SETTINGS, $settings);
  }

  /**
   * Checks that the file really is a PDF by MIME type and extension.
   */
  private function isPdf(FileInterface $file): bool {
    $mime = strtolower((string) $file->getMimeType());
    $extension = strtolower(pathinfo($file->getFilename(), PATHINFO_EXTENSION));
    return $mime === 'application/pdf' && $extension === 'pdf';
  }

  /**
   * Resolves the accessible title shown by the viewer.
   */
  private function resolveTitle(string $title, FileInterface $file, array $settings): string {
    return trim((string) ($settings['embed_title'] ?? '')) ?: (trim($title) ?: $file->getFilename());
  }

  /**
   * Assembles the viewer shell, toolbar, controls and fallback links.
   */
  private function buildViewer(string $url, string $title, array $settings): array {
    $full_viewer_url = $this->fullViewerUrl($url, $settings);
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
      'toolbar' => $this->buildToolbar(),
      'reader_controls' => $this->buildReaderControls(),
      'search_controls' => $this->buildSearchControls(),
      'outline' => $this->buildOutline(),
      'page_thumbnails' => $this->buildPageThumbnails(),
      'page_stage' => $this->buildPageStage(),
      ...$this->buildActionLinks($url, $full_viewer_url, $settings),
      '#attached' => ['library' => ['aculta_pdf_viewer/viewer']],
    ];

    $this->applyVisibility($build, $settings);
    return $build;
  }

  /**
   * Builds the page navigation toolbar.
   */
  private function buildToolbar(): array {
    return [
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
    ];
  }

  /**
   * Builds zoom, rotation and fullscreen controls.
   */
  private function buildReaderControls(): array {
    return [
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
    ];
  }

  /**
   * Builds the in-document search controls.
   */
  private function buildSearchControls(): array {
    return [
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
    ];
  }

  /**
   * Builds the document outline section.
   */
  private function buildOutline(): array {
    return [
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
    ];
  }

  /**
   * Builds the page thumbnails section.
   */
  private function buildPageThumbnails(): array {
    return [
      '#type' => 'details',
      '#title' => new TranslatableMarkup('Miniaturas das páginas'),
      '#attributes' => ['data-aculta-pdf-control' => 'page-thumbnails'],
      'items' => [
        '#type' => 'html_tag',
        '#tag' => 'ol',
        '#attributes' => ['data-aculta-pdf-control' => 'page-thumbnail-items'],
      ],
      'status' => [
        '#type' => 'html_tag',
        '#tag' => 'p',
        '#value' => new TranslatableMarkup('As miniaturas serão preparadas ao abrir esta seção.'),
        '#attributes' => ['data-aculta-pdf-control' => 'page-thumbnail-status', 'aria-live' => 'polite'],
      ],
    ];
  }

  /**
   * Builds the page rendering stage.
   */
  private function buildPageStage(): array {
    return [
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
    ];
  }

  /**
   * Builds the download, print and full-viewer fallback links.
   */
  private function buildActionLinks(string $url, string $full_viewer_url, array $settings): array {
    return [
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
          'href' => $full_viewer_url,
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
          'href' => $full_viewer_url,
          'target' => in_array($settings['link_target'], ['_blank', '_self'], TRUE) ? $settings['link_target'] : '_blank',
          'rel' => $settings['link_target'] === '_blank' ? 'noopener noreferrer' : NULL,
          'data-aculta-pdf-control' => 'full-viewer',
        ],
      ],
    ];
  }

  /**
   * Applies the per-control visibility settings.
   */
  private function applyVisibility(array &$build, array $settings): void {
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
    if (empty($settings['show_page_thumbnails'])) {
      $build['page_thumbnails']['#access'] = FALSE;
    }
    if (empty($settings['show_search'])) {
      $build['search_controls']['#access'] = FALSE;
    }
    if (empty($settings['show_page_navigation'])) {
      $build['toolbar']['previous']['#access'] = FALSE;
      $build['toolbar']['next']['#access'] = FALSE;
    }
  }

  /**
   * Builds a first-page thumbnail for public files only.
   */
  private function buildThumbnail(FileInterface $file, string|false|null $scheme, string $title): ?array {
    if ($scheme !== 'public') {
      return NULL;
    }
    return $this->thumbnailBuilder->build($file, $title . ' — miniatura da primeira página');
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
   * `?file={filepath}` and rechecks Media/File access on every request.
   */
  private function privateFileUrl(string $uri): string {
    $path = substr($uri, strlen('private://'));
    $url = Url::fromRoute('system.files', ['scheme' => 'private'], [
      'query' => ['file' => $path],
    ]);
    $url->setUrlGenerator($this->urlGenerator);
    return $url->toString();
  }

  /**
   * Builds the same-origin wrapper URL with the defaults for this formatter.
   */
  private function fullViewerUrl(string $file_url, array $settings): string {
    return self::VIEWER_WRAPPER_PATH . '?' . UrlHelper::buildQuery([
      'file' => $file_url,
      'locale' => (string) $settings['language'],
      'scroll' => $settings['default_scroll'] === 'horizontal' ? 'horizontal' : 'vertical',
      'spread' => !empty($settings['double_page']) ? '1' : '0',
      'spread_mobile' => !empty($settings['double_page_mobile']) ? '1' : '0',
      'print' => !empty($settings['show_print']) ? '1' : '0',
      'download' => !empty($settings['show_download']) ? '1' : '0',
    ]);
  }

}
