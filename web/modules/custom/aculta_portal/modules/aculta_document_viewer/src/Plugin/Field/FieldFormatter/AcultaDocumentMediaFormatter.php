<?php

declare(strict_types=1);

namespace Drupal\aculta_document_viewer\Plugin\Field\FieldFormatter;

use Drupal\aculta_document_viewer\Docx\DocxViewerBuilder;
use Drupal\aculta_document_viewer\Docx\DocxViewerBuilderInterface;
use Drupal\aculta_document_viewer\Pdf\DocumentFieldMap;
use Drupal\aculta_document_viewer\Pdf\PdfViewerBuilderInterface;
use Drupal\aculta_document_viewer\Spreadsheet\SpreadsheetViewerBuilder;
use Drupal\aculta_document_viewer\Spreadsheet\SpreadsheetViewerBuilderInterface;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Field\Attribute\FieldFormatter;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\file\FileInterface;
use Drupal\file\Plugin\Field\FieldType\FileFieldItemList;
use Drupal\media\MediaInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Dispatches authorized document Media to the local reader for its format.
 *
 * PDF retains its existing viewer settings. DOCX and spreadsheets use their
 * local, read-only builders; invalid or unsupported files keep the Core link.
 * Host, field and download access are checked before any builder is called.
 */
#[FieldFormatter(
  id: 'aculta_document_viewer_media',
  label: new TranslatableMarkup('Leitores de documentos ACULTA e link de arquivo'),
  field_types: ['file'],
)]
final class AcultaDocumentMediaFormatter extends FormatterBase implements ContainerFactoryPluginInterface {

  use DocumentViewerFormatterSettingsTrait;

  public function __construct(
    $plugin_id,
    $plugin_definition,
    $field_definition,
    array $settings,
    $label,
    $view_mode,
    array $third_party_settings,
    private readonly PdfViewerBuilderInterface $pdfViewerBuilder,
    private readonly DocxViewerBuilderInterface $docxViewerBuilder,
    private readonly SpreadsheetViewerBuilderInterface $spreadsheetViewerBuilder,
  ) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $label, $view_mode, $third_party_settings);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static(
      $plugin_id,
      $plugin_definition,
      $configuration['field_definition'],
      $configuration['settings'],
      $configuration['label'],
      $configuration['view_mode'],
      $configuration['third_party_settings'],
      $container->get('aculta_document_viewer.pdf_builder'),
      $container->get('aculta_document_viewer.doc_builder'),
      $container->get('aculta_document_viewer.sheet_builder'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode): array {
    if (!$items instanceof FileFieldItemList) {
      return [];
    }
    $media = $items->getEntity();
    if (!$media instanceof MediaInterface) {
      return [];
    }

    $cacheability = CacheableMetadata::createFromObject($media);
    $media_access = $media->access('view', NULL, TRUE);
    $field_access = $items->access('view', NULL, TRUE);
    $cacheability->addCacheableDependency($media_access);
    $cacheability->addCacheableDependency($field_access);
    if (!$media_access->isAllowed() || !$field_access->isAllowed()) {
      $denied = ['#access' => FALSE];
      $cacheability->applyTo($denied);
      return [0 => $denied];
    }

    $elements = [];
    $field_name = $items->getName();
    foreach ($items->referencedEntities() as $delta => $file) {
      if (!$file instanceof FileInterface) {
        continue;
      }
      $file_cacheability = clone $cacheability;
      $file_cacheability->addCacheableDependency($file);
      $download_access = $file->access('download', NULL, TRUE);
      $file_cacheability->addCacheableDependency($download_access);
      if (!$download_access->isAllowed()) {
        $elements[$delta] = ['#access' => FALSE];
        $file_cacheability->applyTo($elements[$delta]);
        continue;
      }

      $fallback = [
        '#theme' => 'file_link',
        '#file' => $file,
        '#description' => $items->get($delta)->description ?: NULL,
      ];
      $viewer = $this->buildViewer($media, $file, $field_name);
      if ($viewer !== NULL && (!isset($viewer['#access']) || $viewer['#access'] !== FALSE)) {
        if ($this->fileType($file) !== 'pdf') {
          $viewer['link'] = $fallback;
        }
        $file_cacheability->addCacheableDependency(CacheableMetadata::createFromRenderArray($viewer));
        $elements[$delta] = $viewer;
      }
      else {
        if ($viewer !== NULL) {
          $file_cacheability->addCacheableDependency(CacheableMetadata::createFromRenderArray($viewer));
        }
        $elements[$delta] = $fallback;
      }
      $file_cacheability->applyTo($elements[$delta]);
    }
    return $elements;
  }

  /**
   * Builds the format-specific viewer, or NULL to keep the Core file link.
   */
  private function buildViewer(MediaInterface $media, FileInterface $file, string $field_name): ?array {
    $type = $this->fileType($file);
    if ($type === 'pdf' && DocumentFieldMap::fieldForBundle($media->bundle()) === $field_name) {
      return $this->pdfViewerBuilder->build($media, $field_name, $this->getViewerSettings());
    }
    if ($type === 'docx') {
      return $this->docxViewerBuilder->build($file, (string) $media->label());
    }
    if ($type === 'spreadsheet') {
      return $this->spreadsheetViewerBuilder->build($file, (string) $media->label());
    }
    return NULL;
  }

  /**
   * Resolves a format only when MIME and filename extension agree.
   */
  private function fileType(FileInterface $file): string {
    $mime = strtolower((string) $file->getMimeType());
    $extension = strtolower(pathinfo($file->getFilename(), PATHINFO_EXTENSION));
    if ($mime === 'application/pdf' && $extension === 'pdf') {
      return 'pdf';
    }
    if ($mime === DocxViewerBuilder::DOCX_MIME && $extension === 'docx') {
      return 'docx';
    }
    return isset(SpreadsheetViewerBuilder::FORMATS[$extension])
      && in_array($mime, SpreadsheetViewerBuilder::FORMATS[$extension], TRUE)
      ? 'spreadsheet'
      : 'other';
  }

}
