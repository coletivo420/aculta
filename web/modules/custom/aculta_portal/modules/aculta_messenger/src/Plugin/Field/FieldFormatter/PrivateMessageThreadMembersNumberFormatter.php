<?php

declare(strict_types=1);

namespace Drupal\aculta_messenger\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\Attribute\FieldFormatter;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Defines the private message member field formatter.
 *
 * @extends \Drupal\Core\Field\FormatterBase<\Drupal\Core\Field\FieldItemListInterface<\Drupal\Core\Field\FieldItemInterface>>
 */
#[FieldFormatter(
  id: 'private_message_thread_members_number_formatter',
  label: new TranslatableMarkup('Private Message Thread Members Number'),
  field_types: [
    'entity_reference',
  ],
)]
class PrivateMessageThreadMembersNumberFormatter extends FormatterBase implements ContainerFactoryPluginInterface {

  /**
   * {@inheritdoc}
   */
  public static function isApplicable(FieldDefinitionInterface $field_definition): bool {
    return ($field_definition->getFieldStorageDefinition()->getTargetEntityTypeId() == 'private_message_thread' && $field_definition->getFieldStorageDefinition()->getSetting('target_type') == 'user');
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary(): array {
    $summary = [];
    /** @var string $suffix_summary */
    $suffix_summary = $this->getSetting('members_number_suffix');
    $summary['members_number_suffix'] = $this->t('The members number suffix text: %members_number_suffix. Example: 5 %members_number_suffix', ['%members_number_suffix' => $suffix_summary]);
    return $summary;
  }

  /**
   * {@inheritdoc}
   *
   * @return array<string, mixed>
   */
  public static function defaultSettings(): array {
    return [
      'members_number_suffix' => 'users',
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   *
   * @param array<string, mixed> $form
   *
   * @return array<string, mixed>
   */
  public function settingsForm(array $form, FormStateInterface $form_state): array {
    $element = [];

    $element['members_number_suffix'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Multiple user suffix'),
      '#default_value' => $this->getSetting('members_number_suffix'),
    ];

    return $element;
  }

  /**
   * {@inheritdoc}
   *
   * @param \Drupal\Core\Field\FieldItemListInterface<\Drupal\Core\Field\FieldItemInterface> $items
   */
  public function viewElements(FieldItemListInterface $items, $langcode): array {
    $users_num = count($items);
    /** @var string $suffix */
    $suffix = $this->getSetting('members_number_suffix');

    return [
      0 => [
        '#prefix' => '<div class="private-message-recipients-number">',
        '#suffix' => '</div>',
        '#markup' => "$users_num $suffix",
      ],
    ];
  }

}
