<?php

declare(strict_types=1);

namespace Drupal\aculta_document_viewer\Plugin\Field\FieldFormatter;

use Drupal\aculta_document_viewer\Spreadsheet\SpreadsheetViewerBuilder;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Field\Attribute\FieldFormatter;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\file\FileInterface;
use Drupal\file\Plugin\Field\FieldType\FileFieldItemList;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Exibe planilhas XLSX autorizadas em abas (leitor somente leitura) com download.
 *
 * Mesmas checagens do formatter DOCX: entidade hospedeira, campo e download.
 * Sem leitor, o link do Core permanece.
 */
#[FieldFormatter(
  id: 'aculta_document_spreadsheet_media',
  label: new TranslatableMarkup('Leitor de planilhas ACULTA e link de documento'),
  field_types: ['file'],
)]
final class SpreadsheetMediaFormatter extends FormatterBase implements ContainerFactoryPluginInterface {

  /**
   * {@inheritdoc}
   */
  public function __construct(
    $plugin_id,
    $plugin_definition,
    $field_definition,
    array $settings,
    $label,
    $view_mode,
    array $third_party_settings,
    private readonly SpreadsheetViewerBuilder $viewerBuilder,
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
      $container->get('aculta_document_viewer.spreadsheet_builder'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode): array {
    $elements = [];
    if (!$items instanceof FileFieldItemList) {
      return $elements;
    }
    $host = $items->getEntity();
    if (!$host instanceof EntityInterface) {
      return [];
    }
    $cacheability = CacheableMetadata::createFromObject($host);
    $host_access = $host->access('view', NULL, TRUE);
    $field_access = $items->access('view', NULL, TRUE);
    $cacheability->addCacheableDependency($host_access);
    $cacheability->addCacheableDependency($field_access);
    if (!$host_access->isAllowed() || !$field_access->isAllowed()) {
      $denied = ['#access' => FALSE];
      $cacheability->applyTo($denied);
      return [0 => $denied];
    }

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

      $element = [
        '#theme' => 'file_link',
        '#file' => $file,
        '#description' => $items->get($delta)->description ?: NULL,
      ];
      if ($this->isXlsx($file)) {
        $viewer = $this->viewerBuilder->build($file, (string) $host->label());
        if ($viewer !== NULL) {
          $file_cacheability->addCacheableDependency(CacheableMetadata::createFromRenderArray($viewer));
          $viewer['link'] = $element;
          $element = $viewer;
        }
      }
      $elements[$delta] = $element;
      $file_cacheability->applyTo($elements[$delta]);
    }
    return $elements;
  }

  /**
   * Confirms MIME and extension of an XLSX candidate (structure is validated later).
   */
  private function isXlsx(FileInterface $file): bool {
    $mime = strtolower((string) $file->getMimeType());
    $extension = strtolower(pathinfo($file->getFilename(), PATHINFO_EXTENSION));
    return $mime === SpreadsheetViewerBuilder::XLSX_MIME && $extension === 'xlsx';
  }

}
