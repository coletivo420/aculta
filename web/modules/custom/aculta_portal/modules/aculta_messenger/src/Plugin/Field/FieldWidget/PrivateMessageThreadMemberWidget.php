<?php

declare(strict_types=1);

namespace Drupal\aculta_messenger\Plugin\Field\FieldWidget;

use Symfony\Component\HttpFoundation\Request;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\Attribute\FieldWidget;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\Plugin\Field\FieldWidget\EntityReferenceAutocompleteWidget;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\aculta_messenger\Traits\PrivateMessageSettingsTrait;
use Drupal\user\UserInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Defines the private message thread member widget.
 *
 * @phpstan-consistent-constructor
 */
#[FieldWidget(
  id: 'private_message_thread_member_widget',
  label: new TranslatableMarkup('Private message members autocomplete'),
  description: new TranslatableMarkup('An autocomplete text field with tagging support.'),
  field_types: ['entity_reference'],
  multiple_values: TRUE,
)]
class PrivateMessageThreadMemberWidget extends EntityReferenceAutocompleteWidget implements ContainerFactoryPluginInterface {

  use PrivateMessageSettingsTrait;

  /**
   * Tipagem do porte Private Message.
   *
   * @param string $plugin_id
   *   O identificador do plugin.
   * @param mixed $plugin_definition
   *   A definição do plugin.
   * @param FieldDefinitionInterface $field_definition
   *   A definição do campo.
   * @param array<string, mixed> $settings
   *   As configurações do componente.
   * @param array<string, mixed> $third_party_settings
   *   As configurações de terceiros do componente.
   * @param RequestStack $requestStack
   *   A pilha de requisições HTTP.
   * @param EntityTypeManagerInterface $entityTypeManager
   *   O gerenciador de tipos de entidade.
   * @param ConfigFactoryInterface $configFactory
   *   O serviço de configuração do Drupal.
   */
  public function __construct(
    string $plugin_id,
    mixed $plugin_definition,
    FieldDefinitionInterface $field_definition,
    array $settings,
    array $third_party_settings,
    protected RequestStack $requestStack,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected ConfigFactoryInterface $configFactory,
  ) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $third_party_settings);
  }

  /**
   * {@inheritdoc}
   *
   * @param ContainerInterface $container
   *   O contêiner de serviços.
   * @param array<string, mixed> $configuration
   *   A configuração do plugin.
   * @param string $plugin_id
   *   O identificador do plugin.
   * @param mixed $plugin_definition
   *   A definição do plugin.
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    /** @var \Drupal\Core\Field\FieldDefinitionInterface $field_definition */
    $field_definition = $configuration['field_definition'];
    /** @var array<string, mixed> $settings */
    $settings = $configuration['settings'];
    /** @var array<string, mixed> $third_party_settings */
    $third_party_settings = $configuration['third_party_settings'];

    return new static(
      $plugin_id,
      $plugin_definition,
      $field_definition,
      $settings,
      $third_party_settings,
      $container->get('request_stack'),
      $container->get('entity_type.manager'),
      $container->get('config.factory')
    );
  }

  /**
   * {@inheritdoc}
   */
  public static function isApplicable(FieldDefinitionInterface $field_definition): bool {
    return $field_definition->getFieldStorageDefinition()
      ->getTargetEntityTypeId() == 'private_message_thread' && $field_definition->getFieldStorageDefinition()
      ->getSetting('target_type') == 'user';
  }

  /**
   * {@inheritdoc}
   *
   * @return array<string, mixed>
   *   A lista com os valores resultantes.
   */
  public static function defaultSettings(): array {
    return [
      'max_members' => 0,
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   *
   * The settings summary is returned empty, as the parent settings have no
   * effect on this form.
   *
   * @return array<int|string, mixed>
   *   A lista com os valores resultantes.
   */
  public function settingsSummary(): array {
    /** @var array<int|string, mixed> $summary */
    $summary = parent::settingsSummary();

    unset($summary[0]);
    /** @var int $maxMembers */
    $maxMembers = $this->getSetting('max_members');
    $summary[] = $this->t('Maximum thread members: @count', ['@count' => $maxMembers]);

    return $summary;
  }

  /**
   * {@inheritdoc}
   *
   * @param array<array-key, mixed> $form
   *   O array do formulário.
   * @param FormStateInterface $form_state
   *   O estado atual do formulário.
   *
   * @return array<string, mixed>
   *   A lista com os valores resultantes.
   */
  public function settingsForm(array $form, FormStateInterface $form_state): array {
    /** @var array<string, mixed> $form */
    $form = parent::settingsForm($form, $form_state);

    $form['max_members'] = [
      '#type' => 'number',
      '#title' => $this->t('Maximum number of thread members'),
      '#description' => $this->t('The maximum number of members that can be added to the private message conversation. Set to zero (0) to allow unlimited members'),
      '#default_value' => $this->getSetting('max_members'),
      '#min' => 0,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   *
   * @param \Drupal\Core\Field\FieldItemListInterface<\Drupal\Core\Field\FieldItemInterface> $items
   *   A lista de itens do campo.
   * @param int $delta
   *   A posição do item no campo.
   * @param array<string, mixed> $element
   *   O elemento do formulário.
   * @param array<array-key, mixed> &$form
   *   O array do formulário.
   * @param FormStateInterface $form_state
   *   O estado atual do formulário.
   *
   * @return array<string, mixed>
   *   A lista com os valores resultantes.
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state): array {
    /** @var array{target_id: array<string, mixed>, '#element_validate'?: array<mixed>, ...} $element */
    $element = parent::formElement($items, $delta, $element, $form, $form_state);

    /** @var int $maxMembers */
    $maxMembers = $this->getSetting('max_members');

    $element['target_id']['#tags'] = TRUE;
    $element['target_id']['#title'] = $this->t('To');
    $element['target_id']['#required'] = TRUE;
    $element['target_id']['#default_value'] = $items->referencedEntities();
    $element['target_id']['#selection_handler'] = 'default:user';
    $element['target_id']['#selection_settings'] = [
      'include_anonymous' => FALSE,
      // @see \private_message_entity_query_user_alter()
      'private_message' => [
        'active_users_selection' => TRUE,
      ],
    ];
    $element['target_id']['#validate_reference'] = TRUE;

    $recipient = $this->getDefaultRecipient();
    if ($recipient) {
      $element['target_id']['#default_value'] = $recipient;
    }

    if ($recipient && $this->getPrivateMessageSettings()->get('hide_recipient_field_when_prefilled')) {
      $maxMembers = 1;
    }

    if ($maxMembers) {
      $element['#element_validate'][] = [__CLASS__, 'validateFormElement'];
      $element['#max_members'] = $maxMembers;
    }

    return $element;
  }

  /**
   * Gets default recipient.
   *
   * @return \Drupal\user\UserInterface|null
   *   Recipient.
   */
  protected function getDefaultRecipient(): ?UserInterface {
    /** @var \Symfony\Component\HttpFoundation\Request $currentRequest */
    $currentRequest = $this->requestStack->getCurrentRequest();
    /** @var int|string|null $recipientId */
    $recipientId = $this->requestParameter($currentRequest, 'recipient');
    if (!$recipientId) {
      return NULL;
    }

    return $this->entityTypeManager
      ->getStorage('user')
      ->load($recipientId);
  }

  /**
   * Validates the form element for number of users.
   *
   * Validates the form element to ensure that no more than the maximum number
   * of allowed users has been entered. This is because the field itself is
   * created as an unlimited cardinality field, but the widget allows for
   * setting a maximum number of users.
   *
   * @param array<string, mixed> $element
   *   O elemento do formulário.
   * @param FormStateInterface $form_state
   *   O estado atual do formulário.
   *
   * @phpstan-param array{'#parents': array<int|string>, '#max_members': int, ...} $element
   */
  public static function validateFormElement(array $element, FormStateInterface $form_state): void {
    $inputExists = FALSE;
    /** @var array<string, mixed>|null $input */
    $input = NestedArray::getValue($form_state->getValues(), $element['#parents'], $inputExists);
    /** @var array<mixed> $values */
    $values = $input['target_id'] ?? [];
    if ($inputExists && count($values) > $element['#max_members']) {
      $form_state->setError($element, t('Private messages threads cannot have more than @count members', ['@count' => $element['#max_members']]));
    }
  }

  /**
   * {@inheritdoc}
   *
   * @param array<string, mixed> $values
   *   Os valores enviados.
   * @param array<array-key, mixed> $form
   *   O array do formulário.
   * @param FormStateInterface $form_state
   *   O estado atual do formulário.
   *
   * @return array<string, mixed>
   *   A lista com os valores resultantes.
   */
  public function massageFormValues(array $values, array $form, FormStateInterface $form_state): array {
    /** @var array<string, mixed> $targetValues */
    $targetValues = $values['target_id'] ?? [];
    return $targetValues;
  }

  /**
   * Lê um parâmetro da requisição na mesma ordem de Request::get(): rota, query e corpo.
   */
  private function requestParameter(Request $request, string $key): mixed {
    if ($request->attributes->has($key)) {
      return $request->attributes->get($key);
    }
    if ($request->query->has($key)) {
      return $request->query->get($key);
    }
    if ($request->request->has($key)) {
      return $request->request->get($key);
    }
    return NULL;
  }

}
