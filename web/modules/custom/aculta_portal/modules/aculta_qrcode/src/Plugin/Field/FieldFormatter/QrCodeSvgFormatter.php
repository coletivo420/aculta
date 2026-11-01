<?php

declare(strict_types=1);

namespace Drupal\aculta_qrcode\Plugin\Field\FieldFormatter;

use Drupal\aculta_qrcode\Entity\QrCode;
use Drupal\aculta_qrcode\Service\QrCodeDataUri;
use Drupal\aculta_qrcode\Service\QrCodeExporter;
use Drupal\Core\Field\Attribute\FieldFormatter;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Mostra o código QR referenciado por um campo de entidade, como imagem SVG.
 */
#[FieldFormatter(
  id: 'aculta_qr_svg',
  label: new TranslatableMarkup('Código QR (SVG)'),
  field_types: ['entity_reference'],
)]
final class QrCodeSvgFormatter extends FormatterBase {

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
    private readonly QrCodeExporter $exporter,
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
      $container->get('aculta_qrcode.exporter'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode): array {
    $elements = [];
    foreach ($items as $delta => $item) {
      $entity = $item->entity;
      if (!$entity instanceof QrCode) {
        continue;
      }
      try {
        $src = QrCodeDataUri::svg($this->exporter->svg($entity));
      }
      catch (\RuntimeException) {
        // Falha de geração não derruba a página: o campo simplesmente não aparece.
        continue;
      }
      $elements[$delta] = [
        '#type' => 'html_tag',
        '#tag' => 'img',
        '#attributes' => [
          'src' => $src,
          'alt' => (string) $this->t('Código QR @code', ['@code' => $entity->getCode()]),
          'width' => 200,
          'height' => 200,
          'loading' => 'lazy',
        ],
        '#cache' => ['tags' => $entity->getCacheTags()],
      ];
    }
    return $elements;
  }

}
