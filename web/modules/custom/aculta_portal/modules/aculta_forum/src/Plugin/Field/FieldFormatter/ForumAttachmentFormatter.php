<?php

declare(strict_types=1);

namespace Drupal\aculta_forum\Plugin\Field\FieldFormatter;

use Drupal\aculta_document_viewer\Docx\DocxViewerBuilder;
use Drupal\aculta_document_viewer\Spreadsheet\SpreadsheetViewerBuilder;
use Drupal\aculta_image\ImageRenderer;
use Drupal\aculta_pdf_viewer\Pdf\PdfViewerBuilderInterface;
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
 * Renders Forum images and delegates local document previews to their owners.
 */
#[FieldFormatter(
  id: 'aculta_forum_attachment',
  label: new TranslatableMarkup('ACULTA Forum attachment'),
  field_types: ['file'],
)]
final class ForumAttachmentFormatter extends FormatterBase implements ContainerFactoryPluginInterface {

  public function __construct(
    $plugin_id,
    $plugin_definition,
    $field_definition,
    array $settings,
    $label,
    $view_mode,
    array $third_party_settings,
    private readonly ImageRenderer $imageRenderer,
    private readonly DocxViewerBuilder $docxViewerBuilder,
    private readonly SpreadsheetViewerBuilder $spreadsheetViewerBuilder,
    private readonly PdfViewerBuilderInterface $pdfViewerBuilder,
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
      $container->get('aculta_image.render'),
      $container->get('aculta_document_viewer.docx_builder'),
      $container->get('aculta_document_viewer.spreadsheet_builder'),
      $container->get('aculta_pdf_viewer.builder'),
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

      $image_field = $media->hasField('field_media_forum_image')
        ? $media->get('field_media_forum_image')
        : NULL;
      $field_marks_this_file_as_image = $image_field !== NULL
        && !$image_field->isEmpty()
        && (string) $image_field->target_id === (string) $file->id();
      if (str_starts_with((string) $file->getMimeType(), 'image/') || $field_marks_this_file_as_image) {
        $alt = $media->hasField('field_media_forum_image')
          ? (string) ($media->get('field_media_forum_image')->alt ?: $file->getFilename())
          : $file->getFilename();
        $image = $this->imageRenderer->build('galeria_visualizacao', $file->getFileUri(), $alt, ['loading' => 'lazy']);
        if ($image !== NULL) {
          $elements[$delta] = $image;
          $file_cacheability->applyTo($elements[$delta]);
          continue;
        }
      }

      $link = [
        '#theme' => 'file_link',
        '#file' => $file,
        '#description' => $items->get($delta)->description ?: NULL,
      ];
      $extension = strtolower(pathinfo($file->getFilename(), PATHINFO_EXTENSION));
      $mime = strtolower((string) $file->getMimeType());
      $viewer = NULL;
      if ($extension === 'pdf' && $mime === 'application/pdf'
        && $items->getName() === 'field_media_forum_attachment') {
        $viewer = $this->pdfViewerBuilder->build($media, 'field_media_forum_attachment');
        $file_cacheability->addCacheableDependency(CacheableMetadata::createFromRenderArray($viewer));
        if (isset($viewer['#access']) && $viewer['#access'] === FALSE) {
          $viewer = NULL;
        }
      }
      elseif ($extension === 'docx' && $mime === DocxViewerBuilder::DOCX_MIME) {
        $viewer = $this->docxViewerBuilder->build($file, (string) $file->getFilename());
      }
      elseif (isset(SpreadsheetViewerBuilder::FORMATS[$extension]) && in_array($mime, SpreadsheetViewerBuilder::FORMATS[$extension], TRUE)) {
        $viewer = $this->spreadsheetViewerBuilder->build($file, (string) $file->getFilename());
      }
      $elements[$delta] = $link;
      if ($viewer !== NULL) {
        $file_cacheability->addCacheableDependency(CacheableMetadata::createFromRenderArray($viewer));
        $viewer['link'] = $link;
        $elements[$delta] = $viewer;
      }
      $file_cacheability->applyTo($elements[$delta]);
    }
    return $elements;
  }

}
