<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_pdf_viewer\Unit\Pdf;

use Drupal\aculta_pdf_viewer\Pdf\PdfViewerBuilder;
use Drupal\aculta_pdf_viewer\Pdf\PdfPreviewThumbnailBuilderInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\Core\Routing\UrlGeneratorInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\file\FileInterface;
use Drupal\media\MediaInterface;
use Drupal\Tests\UnitTestCase;

/**
 * Tests access handling for the shared PDF viewer builder. */
final class PdfViewerBuilderTest extends UnitTestCase {

  /**
   * Ensures a denied Media entity produces no viewer output and keeps cache. */
  public function testDeniedMediaIsNotRendered(): void {
    $media = $this->createMock(MediaInterface::class);
    $media->method('access')
      ->with('view', $this->isInstanceOf(AccountInterface::class), TRUE)
      ->willReturn(AccessResult::forbidden()->addCacheContexts(['user.permissions']));
    $media->method('getCacheTags')->willReturn([]);
    $media->method('getCacheContexts')->willReturn([]);
    $media->method('getCacheMaxAge')->willReturn(-1);

    $builder = new PdfViewerBuilder(
      $this->createMock(FileUrlGeneratorInterface::class),
      $this->createMock(UrlGeneratorInterface::class),
      $this->createMock(AccountInterface::class),
      $this->createMock(PdfPreviewThumbnailBuilderInterface::class),
    );

    $build = $builder->build($media);

    self::assertFalse($build['#access']);
    self::assertContains('user.permissions', $build['#cache']['contexts']);
    self::assertArrayNotHasKey('#attached', $build);
  }

  /**
   * Ensures a permitted public PDF receives the local viewer and fallback. */
  public function testAuthorizedPublicPdfBuildsLocalViewer(): void {
    $account = $this->createMock(AccountInterface::class);
    $media = $this->createMock(MediaInterface::class);
    $media->method('access')->willReturn(AccessResult::allowed()->addCacheContexts(['user.permissions']));
    $media->method('getCacheTags')->willReturn(['media:7']);
    $media->method('getCacheContexts')->willReturn([]);
    $media->method('getCacheMaxAge')->willReturn(-1);
    $media->method('bundle')->willReturn('document');
    $media->method('hasField')->with('field_media_document')->willReturn(TRUE);
    $media->method('label')->willReturn('Documento de teste');

    $file = $this->createMock(FileInterface::class);
    $file->method('access')->willReturn(AccessResult::allowed()->addCacheContexts(['user.permissions']));
    $file->method('getCacheTags')->willReturn(['file:11']);
    $file->method('getCacheContexts')->willReturn([]);
    $file->method('getCacheMaxAge')->willReturn(-1);
    $file->method('getFileUri')->willReturn('public://documento.pdf');
    $file->method('isPermanent')->willReturn(TRUE);
    $file->method('getMimeType')->willReturn('application/pdf');
    $file->method('getFilename')->willReturn('documento.pdf');

    $field = $this->createMock(FieldItemListInterface::class);
    $field->method('access')->willReturn(AccessResult::allowed()->addCacheContexts(['user.permissions']));
    $field->method('isEmpty')->willReturn(FALSE);
    $field->method('__get')->with('entity')->willReturn($file);
    $media->method('get')->with('field_media_document')->willReturn($field);

    $url_generator = $this->createMock(FileUrlGeneratorInterface::class);
    $url_generator->expects(self::exactly(2))
      ->method('generateString')
      ->with('public://documento.pdf')
      ->willReturn('/sites/default/files/documento.pdf');

    $thumbnail = ['#type' => 'html_tag', '#tag' => 'img'];
    $thumbnail_builder = $this->createMock(PdfPreviewThumbnailBuilderInterface::class);
    $thumbnail_builder->expects(self::exactly(2))
      ->method('build')
      ->with($file, 'Documento de teste — miniatura da primeira página')
      ->willReturn($thumbnail);

    $build = (new PdfViewerBuilder($url_generator, $this->createMock(UrlGeneratorInterface::class), $account, $thumbnail_builder))
      ->build($media, settings: [
        'default_scroll' => 'horizontal',
        'double_page' => TRUE,
        'double_page_mobile' => TRUE,
      ]);

    self::assertArrayNotHasKey('#access', $build);
    self::assertSame('true', $build['#attributes']['data-aculta-pdf-viewer']);
    self::assertSame('/sites/default/files/documento.pdf', $build['#attributes']['data-aculta-pdf-url']);
    self::assertSame(['aculta_pdf_viewer/viewer'], $build['#attached']['library']);
    self::assertContains('user.permissions', $build['#cache']['contexts']);
    self::assertContains('media:7', $build['#cache']['tags']);
    self::assertContains('file:11', $build['#cache']['tags']);
    self::assertSame('/sites/default/files/documento.pdf', $build['download']['#attributes']['href']);
    self::assertSame($build['print']['#attributes']['href'], $build['full_viewer']['#attributes']['href']);
    $viewer_query = [];
    parse_str((string) parse_url($build['full_viewer']['#attributes']['href'], PHP_URL_QUERY), $viewer_query);
    self::assertSame('/sites/default/files/documento.pdf', $viewer_query['file']);
    self::assertSame('pt-BR', $viewer_query['locale']);
    self::assertSame('horizontal', $viewer_query['scroll']);
    self::assertSame('1', $viewer_query['spread']);
    self::assertSame('1', $viewer_query['spread_mobile']);
    self::assertSame('1', $viewer_query['print']);
    self::assertSame('1', $viewer_query['download']);
    self::assertSame($thumbnail, $build['thumbnail']['image']);

    $restricted_build = (new PdfViewerBuilder($url_generator, $this->createMock(UrlGeneratorInterface::class), $account, $thumbnail_builder))
      ->build($media, settings: [
        'show_print' => FALSE,
        'show_download' => FALSE,
      ]);
    parse_str((string) parse_url($restricted_build['full_viewer']['#attributes']['href'], PHP_URL_QUERY), $restricted_query);
    self::assertSame('0', $restricted_query['print']);
    self::assertSame('0', $restricted_query['download']);
    self::assertFalse($restricted_build['print']['#access']);
    self::assertFalse($restricted_build['download']['#access']);
  }

  /**
   * Ensures private PDFs use only Drupal's protected file route.
   */
  public function testAuthorizedPrivatePdfUsesProtectedRouteWithoutThumbnail(): void {
    $account = $this->createMock(AccountInterface::class);
    $media = $this->createMock(MediaInterface::class);
    $media->method('access')->willReturn(AccessResult::allowed()->addCacheContexts(['user.permissions']));
    $media->method('getCacheTags')->willReturn(['media:8']);
    $media->method('getCacheContexts')->willReturn([]);
    $media->method('getCacheMaxAge')->willReturn(-1);
    $media->method('bundle')->willReturn('course_document');
    $media->method('hasField')->with('field_media_course_document')->willReturn(TRUE);
    $media->method('label')->willReturn('Trabalho privado');

    $file = $this->createMock(FileInterface::class);
    $file->method('access')->willReturn(AccessResult::allowed()->addCacheContexts(['user.permissions']));
    $file->method('getCacheTags')->willReturn(['file:12']);
    $file->method('getCacheContexts')->willReturn([]);
    $file->method('getCacheMaxAge')->willReturn(-1);
    $file->method('getFileUri')->willReturn('private://turma/trabalho.pdf');
    $file->method('isPermanent')->willReturn(TRUE);
    $file->method('getMimeType')->willReturn('application/pdf');
    $file->method('getFilename')->willReturn('trabalho.pdf');

    $field = $this->createMock(FieldItemListInterface::class);
    $field->method('access')->willReturn(AccessResult::allowed()->addCacheContexts(['user.permissions']));
    $field->method('isEmpty')->willReturn(FALSE);
    $field->method('__get')->with('entity')->willReturn($file);
    $media->method('get')->with('field_media_course_document')->willReturn($field);

    $url_generator = $this->createMock(FileUrlGeneratorInterface::class);
    $route_url_generator = $this->createMock(UrlGeneratorInterface::class);
    $route_url_generator->expects(self::once())
      ->method('generateFromRoute')
      ->with('system.files', ['scheme' => 'private'], ['query' => ['file' => 'turma/trabalho.pdf']], FALSE)
      ->willReturn('/system/files?file=turma/trabalho.pdf');
    $thumbnail_builder = $this->createMock(PdfPreviewThumbnailBuilderInterface::class);
    $thumbnail_builder->expects(self::never())->method('build');

    $build = (new PdfViewerBuilder($url_generator, $route_url_generator, $account, $thumbnail_builder))
      ->build($media, 'field_media_course_document');

    self::assertSame('/system/files?file=turma/trabalho.pdf', $build['#attributes']['data-aculta-pdf-url']);
    self::assertSame('/system/files?file=turma/trabalho.pdf', $build['download']['#attributes']['href']);
    $viewer_query = [];
    parse_str((string) parse_url($build['full_viewer']['#attributes']['href'], PHP_URL_QUERY), $viewer_query);
    self::assertSame('/system/files?file=turma/trabalho.pdf', $viewer_query['file']);
    self::assertSame(0, $build['#cache']['max-age']);
    self::assertArrayNotHasKey('thumbnail', $build);
  }

  /**
   * Ensures Forum attachment PDFs use the private file route as well.
   */
  public function testForumAttachmentPdfUsesProtectedRoute(): void {
    $account = $this->createMock(AccountInterface::class);
    $media = $this->createMock(MediaInterface::class);
    $media->method('access')->willReturn(AccessResult::allowed());
    $media->method('getCacheTags')->willReturn(['media:12']);
    $media->method('getCacheContexts')->willReturn([]);
    $media->method('getCacheMaxAge')->willReturn(-1);
    $media->method('bundle')->willReturn('forum_attachment');
    $media->method('hasField')->with('field_media_forum_attachment')->willReturn(TRUE);
    $media->method('label')->willReturn('Anexo do fórum');

    $file = $this->createMock(FileInterface::class);
    $file->method('access')->willReturn(AccessResult::allowed());
    $file->method('getCacheTags')->willReturn(['file:16']);
    $file->method('getCacheContexts')->willReturn([]);
    $file->method('getCacheMaxAge')->willReturn(-1);
    $file->method('getFileUri')->willReturn('private://forum/anexos/anexo.pdf');
    $file->method('isPermanent')->willReturn(TRUE);
    $file->method('getMimeType')->willReturn('application/pdf');
    $file->method('getFilename')->willReturn('anexo.pdf');

    $field = $this->createMock(FieldItemListInterface::class);
    $field->method('access')->willReturn(AccessResult::allowed());
    $field->method('isEmpty')->willReturn(FALSE);
    $field->method('__get')->with('entity')->willReturn($file);
    $media->method('get')->with('field_media_forum_attachment')->willReturn($field);

    $routeUrlGenerator = $this->createMock(UrlGeneratorInterface::class);
    $routeUrlGenerator->expects(self::once())
      ->method('generateFromRoute')
      ->with('system.files', ['scheme' => 'private'], ['query' => ['file' => 'forum/anexos/anexo.pdf']], FALSE)
      ->willReturn('/system/files?file=forum/anexos/anexo.pdf');
    $thumbnailBuilder = $this->createMock(PdfPreviewThumbnailBuilderInterface::class);
    $thumbnailBuilder->expects(self::never())->method('build');

    $build = (new PdfViewerBuilder(
      $this->createMock(FileUrlGeneratorInterface::class),
      $routeUrlGenerator,
      $account,
      $thumbnailBuilder,
    ))->build($media, 'field_media_forum_attachment');

    self::assertSame('/system/files?file=forum/anexos/anexo.pdf', $build['download']['#attributes']['href']);
    self::assertSame(0, $build['#cache']['max-age']);
  }

}
