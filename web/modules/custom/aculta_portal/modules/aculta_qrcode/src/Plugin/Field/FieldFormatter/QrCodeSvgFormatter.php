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
 *
 * @extends \Drupal\Core\Field\FormatterBase<\Drupal\Core\Field\FieldItemListInterface<\Drupal\Core\Field\FieldItemInterface>>
 */
#[FieldFormatter(
  id: 'aculta_qr_svg',
  label: new TranslatableMarkup('Código QR (SVG)'),
  field_types: ['entity_reference'],
)]
final class QrCodeSvgFormatter extends FormatterBase {

  /**
   * {@inheritdoc}
   *
   * @param string $plugin_id
   * @param mixed $plugin_definition
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field_definition
   * @param array<string, mixed> $settings
   * @param string $label
   * @param string $view_mode
   * @param array<string, mixed> $third_party_settings
   * @param \Drupal\aculta_qrcode\Service\QrCodeExporter $exporter
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   */
  public function __construct(
    $plugin_id,
    $plugin_definition,
    $field_definition,
    array $settings,
    $label,
    $view_mode,
    array $third_party_settings,
    protected readonly QrCodeExporter $exporter,
    protected readonly \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager,
  ) {
    parent::__construct((string) $plugin_id, $plugin_definition, $field_definition, $settings, (string) $label, (string) $view_mode, $third_party_settings);
  }

  /**
   * {@inheritdoc}
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   * @param array<string, mixed> $configuration
   * @param string $plugin_id
   * @param mixed $plugin_definition
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    /** @var \Drupal\Core\Field\FieldDefinitionInterface $fieldDef */
    $fieldDef = $configuration['field_definition'];
    /** @var array<string, mixed> $settings */
    $settings = is_array($configuration['settings'] ?? NULL) ? $configuration['settings'] : [];
    /** @var array<string, mixed> $thirdParty */
    $thirdParty = is_array($configuration['third_party_settings'] ?? NULL) ? $configuration['third_party_settings'] : [];
    $labelVal = $configuration['label'] ?? '';
    $viewModeVal = $configuration['view_mode'] ?? '';
    $labelStr = is_scalar($labelVal) || $labelVal instanceof \Stringable ? (string) $labelVal : '';
    $viewModeStr = is_scalar($viewModeVal) || $viewModeVal instanceof \Stringable ? (string) $viewModeVal : '';
    return new static(
      $plugin_id,
      $plugin_definition,
      $fieldDef,
      $settings,
      $labelStr,
      $viewModeStr,
      $thirdParty,
      $container->get('aculta_qrcode.exporter'),
      $container->get('entity_type.manager'),
    );
  }

  /**
   * {@inheritdoc}
   *
   * @param \Drupal\Core\Field\FieldItemListInterface<\Drupal\Core\Field\FieldItemInterface> $items
   * @return array<int|string, array<string, mixed>>
   */
  public function viewElements(FieldItemListInterface $items, $langcode): array {
    $elements = [];
    foreach ($items as $delta => $item) {
      $entity = $item->get('entity')->getValue();
      if (!$entity instanceof QrCode) {
        $targetId = $item->get('target_id')->getValue();
        if ($targetId !== NULL && (is_int($targetId) || is_string($targetId))) {
          $loaded = $this->entityTypeManager->getStorage('aculta_qr_code')->load($targetId);
          if ($loaded instanceof QrCode) {
            $entity = $loaded;
          }
        }
      }
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
