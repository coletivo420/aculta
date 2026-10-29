<?php

declare(strict_types=1);

namespace Drupal\aculta_document_viewer\Plugin\Field\FieldFormatter;

use Drupal\aculta_document_viewer\Document\ReadableMime;
use Drupal\Core\Field\Attribute\FieldFormatter;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\file\FileInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Renders download links enriched with human-readable MIME and size metadata.
 */
#[FieldFormatter(
  id: 'aculta_download_with_meta',
  label: new TranslatableMarkup('Download com metadados acessíveis (formato e tamanho)'),
  field_types: ['file'],
)]
final class DownloadWithMetaFormatter extends FormatterBase implements ContainerFactoryPluginInterface {

  public function __construct(
    $plugin_id,
    $plugin_definition,
    $field_definition,
    array $settings,
    $label,
    $view_mode,
    array $third_party_settings,
    private readonly ReadableMime $readableMime,
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
      $container->get('aculta_document_viewer.readable_mime', ContainerInterface::NULL_ON_INVALID_REFERENCE) ?? new ReadableMime(),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode): array {
    $elements = [];

    foreach ($items as $delta => $item) {
      $file = $item->entity;
      if (!$file instanceof FileInterface) {
        continue;
      }

      $filename = $item->description ?: $file->getFilename();
      $mimeType = (string) $file->getMimeType();
      $filesize = (int) $file->getSize();

      $formatBadge = $this->readableMime->getHumanReadableFormat($mimeType);
      $sizeFormatted = $this->readableMime->formatFileSize($filesize);
      $accessibleLabel = $this->readableMime->getAccessibleDownloadLabel((string) $filename, $mimeType, $filesize);

      $url = $file->createFileUrl(FALSE);

      $elements[$delta] = [
        '#type' => 'container',
        '#attributes' => [
          'class' => ['aculta-download-meta-item'],
          'data-download-format' => strtolower($formatBadge),
        ],
        'link' => [
          '#type' => 'link',
          '#title' => $filename,
          '#url' => Url::fromUri($url, ['attributes' => [
            'download' => '',
            'aria-label' => $accessibleLabel,
            'class' => ['download-link'],
          ]]),
        ],
        'meta' => [
          '#markup' => sprintf(
            '<span class="download-badge-format" aria-hidden="true">%s</span> <span class="download-badge-size" aria-hidden="true">%s</span>',
            htmlspecialchars($formatBadge, ENT_QUOTES, 'UTF-8'),
            htmlspecialchars($sizeFormatted, ENT_QUOTES, 'UTF-8')
          ),
        ],
      ];
    }

    return $elements;
  }

}
