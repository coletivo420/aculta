<?php

// ACULTA fork of Drupal H5P 2.0.0-beta1; upload path disabled pending image ingestion.

declare(strict_types=1);

namespace Drupal\aculta_lms_h5p\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\Attribute\FieldWidget;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\aculta_lms_h5p\H5PDrupal\H5PDrupal;
use Drupal\aculta_lms_h5p\Plugin\Field\H5PWidgetBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/** Field widget placeholder for unavailable legacy content-package uploads. */
#[FieldWidget(
  id: 'h5p_upload',
  label: new TranslatableMarkup('Envio de pacote H5P indisponível'),
  field_types: ['h5p'],
)]
final class H5PUploadWidget extends H5PWidgetBase implements ContainerFactoryPluginInterface {

  protected H5PDrupal $framework;

  public function __construct($plugin_id, $plugin_definition, $field_definition, array $settings, array $third_party_settings, H5PDrupal $framework) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $third_party_settings);
    $this->framework = $framework;
  }

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static(
      $plugin_id,
      $plugin_definition,
      $configuration['field_definition'],
      $configuration['settings'],
      $configuration['third_party_settings'] ?? [],
      $container->get('aculta_lms_h5p.framework'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state): array {
    return [
      'h5p_content' => [
        '#type' => 'item',
        '#title' => $this->t('Envio de pacote indisponível'),
        '#description' => $this->t('Para criar uma atividade, use o editor do curso. Imagens enviadas serão gerenciadas pelo sistema de imagens da ACULTA.'),
      ],
    ];
  }

}
