<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_pdf_viewer\Unit\Pdf;

use Drupal\aculta_pdf_viewer\Pdf\PdfViewerBuilder;
use Drupal\aculta_pdf_viewer\Pdf\PdfPreviewThumbnailBuilderInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\File\FileUrlGeneratorInterface;
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
    $url_generator->expects(self::once())
      ->method('generateString')
      ->with('public://documento.pdf')
      ->willReturn('/sites/default/files/documento.pdf');

    $thumbnail = ['#type' => 'html_tag', '#tag' => 'img'];
    $thumbnail_builder = $this->createMock(PdfPreviewThumbnailBuilderInterface::class);
    $thumbnail_builder->expects(self::once())
      ->method('build')
      ->with($file, 'Documento de teste — miniatura da primeira página')
      ->willReturn($thumbnail);

    $build = (new PdfViewerBuilder($url_generator, $account, $thumbnail_builder))->build($media);

    self::assertArrayNotHasKey('#access', $build);
    self::assertSame('true', $build['#attributes']['data-aculta-pdf-viewer']);
    self::assertSame('/sites/default/files/documento.pdf', $build['#attributes']['data-aculta-pdf-url']);
    self::assertSame(['aculta_pdf_viewer/viewer'], $build['#attached']['library']);
    self::assertContains('user.permissions', $build['#cache']['contexts']);
    self::assertContains('media:7', $build['#cache']['tags']);
    self::assertContains('file:11', $build['#cache']['tags']);
    self::assertSame('/sites/default/files/documento.pdf', $build['download']['#attributes']['href']);
    self::assertSame($thumbnail, $build['thumbnail']['image']);
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
    $media->method('bundle')->willReturn('document');
    $media->method('hasField')->with('field_media_document')->willReturn(TRUE);
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
    $media->method('get')->with('field_media_document')->willReturn($field);

    $url_generator = $this->createMock(FileUrlGeneratorInterface::class);
    $url_generator->expects(self::once())
      ->method('generateString')
      ->with('private://turma/trabalho.pdf')
      ->willReturn('/system/files/turma/trabalho.pdf');
    $thumbnail_builder = $this->createMock(PdfPreviewThumbnailBuilderInterface::class);
    $thumbnail_builder->expects(self::never())->method('build');

    $build = (new PdfViewerBuilder($url_generator, $account, $thumbnail_builder))->build($media);

    self::assertSame('/system/files/turma/trabalho.pdf', $build['#attributes']['data-aculta-pdf-url']);
    self::assertSame('/system/files/turma/trabalho.pdf', $build['download']['#attributes']['href']);
    self::assertSame(0, $build['#cache']['max-age']);
    self::assertArrayNotHasKey('thumbnail', $build);
  }

}
