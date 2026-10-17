<?php

declare(strict_types=1);

namespace Drupal\aculta_pdf_viewer\Plugin\Field\FieldFormatter;

use Drupal\aculta_pdf_viewer\Pdf\PdfViewerBuilderInterface;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Field\Attribute\FieldFormatter;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\file\FileInterface;
use Drupal\file\Plugin\Field\FieldType\FileFieldItemList;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Displays authorized public PDF file fields with the shared PDF.js viewer.
 */
#[FieldFormatter(
  id: 'aculta_pdf_file',
  label: new TranslatableMarkup('Visualizador PDF ACULTA e link de arquivo'),
  field_types: ['file'],
)]
final class AcultaPdfFileFormatter extends FormatterBase implements ContainerFactoryPluginInterface {

  public function __construct(
    $plugin_id,
    $plugin_definition,
    $field_definition,
    array $settings,
    $label,
    $view_mode,
    array $third_party_settings,
    private readonly PdfViewerBuilderInterface $viewerBuilder,
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

    $entity = $items->getEntity();
    $cacheability = CacheableMetadata::createFromObject($entity);
    $entity_access = $entity->access('view', NULL, TRUE);
    $field_access = $items->access('view', NULL, TRUE);
    $cacheability->addCacheableDependency($entity_access);
    $cacheability->addCacheableDependency($field_access);
    if (!$entity_access->isAllowed() || !$field_access->isAllowed()) {
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

      $mime = strtolower((string) $file->getMimeType());
      $extension = strtolower(pathinfo($file->getFilename(), PATHINFO_EXTENSION));
      if ($mime === 'application/pdf' && $extension === 'pdf') {
        $title = $entity->label() ?: $file->getFilename();
        $viewer = $this->viewerBuilder->buildFile($file, $title, 'public', clone $file_cacheability);
        if (!isset($viewer['#access']) || $viewer['#access'] !== FALSE) {
          $elements[$delta] = $viewer;
          continue;
        }
        $file_cacheability->addCacheableDependency(CacheableMetadata::createFromRenderArray($viewer));
      }

      // Keep the authorized Core file link for other file types or when a PDF
      // cannot be embedded safely by the shared viewer.
      $elements[$delta] = [
        '#theme' => 'file_link',
        '#file' => $file,
        '#description' => $items->get($delta)->description ?: NULL,
      ];
      $file_cacheability->applyTo($elements[$delta]);
    }

    return $elements;
  }

}
