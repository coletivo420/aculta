<?php

declare(strict_types=1);

namespace Drupal\aculta_forum\Plugin\Field\FieldFormatter;

use Drupal\aculta_document_viewer\Docx\DocxViewerBuilder;
use Drupal\aculta_document_viewer\Pdf\PdfViewerBuilderInterface;
use Drupal\aculta_document_viewer\Spreadsheet\SpreadsheetViewerBuilder;
use Drupal\aculta_image\ImageRenderer;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Field\Attribute\FieldFormatter;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\file\FileInterface;
use Drupal\file\Plugin\Field\FieldType\FileFieldItemList;
use Drupal\file\Plugin\Field\FieldType\FileItem;
use Drupal\media\MediaInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Renders Forum images and delegates local document previews to their owners.
 *
 * @extends FormatterBase<\Drupal\Core\Field\FieldItemListInterface>
 */
#[FieldFormatter(
  id: 'aculta_forum_attachment',
  label: new TranslatableMarkup('ACULTA Forum attachment'),
  field_types: ['file'],
)]
final class ForumAttachmentFormatter extends FormatterBase implements ContainerFactoryPluginInterface {

  /**
   * Constructs a ForumAttachmentFormatter object.
   *
   * @param string $plugin_id
   *   The plugin ID for the formatter.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field_definition
   *   The definition of the field to which the formatter is associated.
   * @param array<string, mixed> $settings
   *   The formatter settings.
   * @param string $label
   *   The formatter label display setting.
   * @param string $view_mode
   *   The view mode.
   * @param array<string, mixed> $third_party_settings
   *   Any third party settings.
   * @param \Drupal\aculta_image\ImageRenderer $imageRenderer
   *   The image renderer service.
   * @param \Drupal\aculta_document_viewer\Docx\DocxViewerBuilder $docxViewerBuilder
   *   The docx viewer builder.
   * @param \Drupal\aculta_document_viewer\Spreadsheet\SpreadsheetViewerBuilder $spreadsheetViewerBuilder
   *   The spreadsheet viewer builder.
   * @param \Drupal\aculta_document_viewer\Pdf\PdfViewerBuilderInterface $pdfViewerBuilder
   *   The pdf viewer builder.
   */
  public function __construct(
    $plugin_id,
    $plugin_definition,
    $field_definition,
    array $settings,
    $label,
    $view_mode,
    array $third_party_settings,
    protected readonly ImageRenderer $imageRenderer,
    protected readonly DocxViewerBuilder $docxViewerBuilder,
    protected readonly SpreadsheetViewerBuilder $spreadsheetViewerBuilder,
    protected readonly PdfViewerBuilderInterface $pdfViewerBuilder,
  ) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $label, $view_mode, $third_party_settings);
  }

  /**
   * {@inheritdoc}
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   The service container.
   * @param array<string, mixed> $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin ID for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   *
   * @phpstan-param array{field_definition: \Drupal\Core\Field\FieldDefinitionInterface, settings: array<string, mixed>, label: string, view_mode: string, third_party_settings: array<string, mixed>, ...} $configuration
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
      $container->get('aculta_document_viewer.doc_builder'),
      $container->get('aculta_document_viewer.sheet_builder'),
      $container->get('aculta_document_viewer.pdf_builder'),
    );
  }

  /**
   * {@inheritdoc}
   *
   * @param \Drupal\Core\Field\FieldItemListInterface<\Drupal\Core\Field\FieldItemInterface> $items
   * @param string $langcode
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
      $targetId = $image_field !== NULL && !$image_field->isEmpty() && isset($image_field->target_id)
        ? (string) $image_field->target_id
        : '';
      $field_marks_this_file_as_image = $targetId !== '' && $targetId === (string) $file->id();
      if (str_starts_with((string) $file->getMimeType(), 'image/') || $field_marks_this_file_as_image) {
        $filename = (string) $file->getFilename();
        $altVal = '';
        if ($image_field !== NULL && !$image_field->isEmpty()) {
          $firstItem = $image_field->first();
          $rawAlt = $firstItem ? $firstItem->get('alt')->getValue() : '';
          $altVal = is_scalar($rawAlt) ? (string) $rawAlt : '';
        }
        $alt = $altVal !== '' ? $altVal : $filename;
        $fileUri = (string) $file->getFileUri();
        $image = $this->imageRenderer->build('galeria_visualizacao', $fileUri, $alt, ['loading' => 'lazy']);
        if ($image !== NULL) {
          $elements[$delta] = $image;
          $file_cacheability->applyTo($elements[$delta]);
          continue;
        }
      }

      $link = [
        '#theme' => 'file_link',
        '#file' => $file,
        '#description' => $this->fileDescription($items, $delta),
      ];
      $filename = (string) $file->getFilename();
      $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
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
        $viewer = $this->docxViewerBuilder->build($file, $filename);
      }
      elseif (isset(SpreadsheetViewerBuilder::FORMATS[$extension]) && in_array($mime, SpreadsheetViewerBuilder::FORMATS[$extension], TRUE)) {
        $viewer = $this->spreadsheetViewerBuilder->build($file, $filename);
      }
      $elements[$delta] = $link;
      if ($viewer !== NULL) {
        $file_cacheability->addCacheableDependency(CacheableMetadata::createFromRenderArray($viewer));
        // The PDF viewer supplies its own download/open actions. Office
        // viewers need the Core file link as their non-JavaScript fallback.
        if ($extension !== 'pdf') {
          $viewer['link'] = $link;
        }
        $elements[$delta] = $viewer;
      }
      $file_cacheability->applyTo($elements[$delta]);
    }
    return $elements;
  }

  /**
   * Reads the optional description from a file field item value.
   */
  private function fileDescription(FileFieldItemList $items, int|string $delta): ?string {
    $item = $items->get($delta);
    $values = $item !== NULL ? $item->getValue() : NULL;
    $description = is_array($values) ? ($values['description'] ?? NULL) : NULL;
    return is_string($description) && $description !== '' ? $description : NULL;
  }

}
