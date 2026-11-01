<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_document_viewer\Unit\Pdf;

use Drupal\aculta_document_viewer\Pdf\PdfPreviewThumbnailBuilderInterface;
use Drupal\aculta_document_viewer\Pdf\PdfViewerBuilder;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\Core\Routing\UrlGeneratorInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\file\FileInterface;
use Drupal\Tests\UnitTestCase;

/**
 * Compares buildFile() output with the versioned pre-refactor fixture.
 *
 * The fixture was generated from the original 357-line implementation. Once
 * it is committed, the refactored implementation must reproduce it unchanged.
 * Regeneration is explicit and only for a deliberate behaviour change:
 * ACULTA_UPDATE_PDF_FIXTURE=1.
 */
final class PdfViewerBuilderEquivalenceTest extends UnitTestCase {

  /**
   * Fixed scenarios that cover the documented equivalence matrix.
   */
  private const SCENARIOS = [
    'public_pdf',
    'private_pdf',
    'non_pdf',
    'access_denied',
    'settings_varied',
    'site_settings',
    'scheme_mismatch',
    'non_permanent',
  ];

  /**
   * Versioned fixture path relative to the module tests directory.
   */
  private const FIXTURE_FILE = 'pdf_viewer_builder_buildfile.json';

  /**
   * Ensures buildFile() keeps the exact pre-refactor render array.
   */
  public function testBuildFileMatchesVersionedFixture(): void {
    $actual = [];
    foreach (self::SCENARIOS as $scenario) {
      $actual[$scenario] = $this->normalize($this->renderScenario($scenario));
    }

    $fixture_path = dirname(__DIR__, 3) . '/fixtures/' . self::FIXTURE_FILE;
    if (getenv('ACULTA_UPDATE_PDF_FIXTURE') === '1') {
      if (!is_dir(dirname($fixture_path))) {
        mkdir(dirname($fixture_path), 0775, TRUE);
      }
      file_put_contents(
        $fixture_path,
        json_encode($actual, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n",
      );
    }

    self::assertFileExists($fixture_path);
    $expected = json_decode((string) file_get_contents($fixture_path), TRUE, 512, JSON_THROW_ON_ERROR);
    self::assertSame($expected, $actual);
  }

  /**
   * Renders one fixed scenario against the builder.
   */
  private function renderScenario(string $scenario): array {
    $uri = 'public://documento.pdf';
    $filename = 'documento.pdf';
    $mime = 'application/pdf';
    $expected_scheme = 'public';
    $require_view_access = FALSE;
    $permanent = TRUE;
    $settings = [];
    $title = '';
    $view_access = AccessResult::allowed()->addCacheContexts(['user.permissions']);
    $download_access = AccessResult::allowed()->addCacheContexts(['user.permissions']);
    $config_factory = NULL;

    switch ($scenario) {
      case 'public_pdf':
        $title = '';
        break;

      case 'private_pdf':
        $uri = 'private://turma/trabalho.pdf';
        $filename = 'trabalho.pdf';
        $expected_scheme = 'private';
        $require_view_access = TRUE;
        break;

      case 'non_pdf':
        $uri = 'public://nota.txt';
        $filename = 'nota.txt';
        $mime = 'text/plain';
        break;

      case 'access_denied':
        $require_view_access = TRUE;
        $view_access = AccessResult::forbidden()->addCacheContexts(['user.permissions']);
        break;

      case 'settings_varied':
        $title = '  Documento variado  ';
        $settings = [
          'show_download' => FALSE,
          'show_print' => FALSE,
          'show_zoom' => FALSE,
          'show_fullscreen' => FALSE,
          'show_search' => FALSE,
          'show_page_navigation' => FALSE,
          'show_rotation' => FALSE,
          'show_outline' => FALSE,
          'show_page_thumbnails' => FALSE,
          'show_full_viewer' => FALSE,
          'default_zoom' => 'page-fit',
          'default_scroll' => 'horizontal',
          'use_site_settings' => FALSE,
          'double_page' => TRUE,
          'double_page_mobile' => TRUE,
          'language' => 'en-US',
          'link_text' => 'Baixar agora',
          'link_target' => '_self',
          'css_class' => 'minha classe!',
        ];
        break;

      case 'site_settings':
        $settings = ['default_zoom' => 'page-fit'];
        $config_factory = $this->configFactory([
          'default_zoom' => 'page-width',
          'show_print' => FALSE,
          'embed_title' => 'Título do site',
        ]);
        break;

      case 'scheme_mismatch':
        $expected_scheme = 'private';
        break;

      case 'non_permanent':
        $permanent = FALSE;
        break;

      default:
        throw new \InvalidArgumentException("Cenário desconhecido: {$scenario}");
    }

    $file = $this->createFileMock($uri, $filename, $mime, $permanent, $view_access, $download_access);
    $cacheability = CacheableMetadata::createFromObject($file);

    $builder = new PdfViewerBuilder(
      $this->fileUrlGenerator(),
      $this->urlGenerator(),
      $this->createMock(AccountInterface::class),
      $this->thumbnailBuilder(),
      $config_factory,
    );

    return $builder->buildFile($file, $title, $expected_scheme, $cacheability, $require_view_access, $settings);
  }

  /**
   * Creates a File mock for a scenario.
   */
  private function createFileMock(
    string $uri,
    string $filename,
    string $mime,
    bool $permanent,
    AccessResultInterface $view_access,
    AccessResultInterface $download_access,
  ): FileInterface {
    $file = $this->createMock(FileInterface::class);
    $file->method('access')->willReturnCallback(
      static fn(string $operation): AccessResultInterface => $operation === 'download' ? $download_access : $view_access,
    );
    $file->method('getCacheTags')->willReturn(['file:11']);
    $file->method('getCacheContexts')->willReturn([]);
    $file->method('getCacheMaxAge')->willReturn(-1);
    $file->method('getFileUri')->willReturn($uri);
    $file->method('isPermanent')->willReturn($permanent);
    $file->method('getMimeType')->willReturn($mime);
    $file->method('getFilename')->willReturn($filename);
    return $file;
  }

  /**
   * Creates a deterministic file URL generator.
   */
  private function fileUrlGenerator(): FileUrlGeneratorInterface {
    $generator = $this->createMock(FileUrlGeneratorInterface::class);
    $generator->method('generateString')->willReturnCallback(
      static fn(string $uri): string => '/sites/default/files/' . basename($uri),
    );
    return $generator;
  }

  /**
   * Creates a deterministic route URL generator.
   */
  private function urlGenerator(): UrlGeneratorInterface {
    $generator = $this->createMock(UrlGeneratorInterface::class);
    $generator->method('generateFromRoute')->willReturnCallback(
      static fn(string $route_name, array $parameters = [], array $options = []): string => '/system/files?file=' . ($options['query']['file'] ?? ''),
    );
    return $generator;
  }

  /**
   * Creates a deterministic thumbnail builder.
   */
  private function thumbnailBuilder(): PdfPreviewThumbnailBuilderInterface {
    $builder = $this->createMock(PdfPreviewThumbnailBuilderInterface::class);
    $builder->method('build')->willReturn([
      '#type' => 'html_tag',
      '#tag' => 'img',
      '#attributes' => ['src' => '/sites/default/files/thumb.png'],
    ]);
    return $builder;
  }

  /**
   * Creates a config factory backed by one immutable config object.
   */
  private function configFactory(array $raw_data): ConfigFactoryInterface {
    $config = $this->createMock(ImmutableConfig::class);
    $config->method('getRawData')->willReturn($raw_data);
    $config->method('getCacheContexts')->willReturn([]);
    $config->method('getCacheTags')->willReturn(['config:aculta_document_viewer.settings']);
    $config->method('getCacheMaxAge')->willReturn(-1);

    $factory = $this->createMock(ConfigFactoryInterface::class);
    $factory->method('get')
      ->with('aculta_document_viewer.settings')
      ->willReturn($config);
    return $factory;
  }

  /**
   * Converts render arrays to a deterministic JSON-compatible structure.
   */
  private function normalize(mixed $value): mixed {
    if ($value instanceof TranslatableMarkup) {
      return ['__translatable' => $value->getUntranslatedString()];
    }
    if (is_array($value)) {
      $normalized = [];
      foreach ($value as $key => $item) {
        $normalized[$key] = $this->normalize($item);
      }
      return $normalized;
    }
    if (is_object($value)) {
      throw new \LogicException('Unexpected object in render array: ' . $value::class);
    }
    return $value;
  }

}
