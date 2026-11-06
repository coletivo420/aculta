<?php

declare(strict_types=1);

namespace Drupal\aculta_magazine\Hook;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\Display\EntityViewDisplayInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\node\NodeInterface;
use Drupal\ui_styles\StylePluginManagerInterface;

/** Connects the administrator's Revista accent choice to UI Styles rendering. */
final class RevistaAccentHooks {

  private const FIELD = 'field_revista_accent';
  private const ELEMENT = 'revista_accent_styles';
  private const STYLE = 'revista_accent';

  public function __construct(
    private readonly StylePluginManagerInterface $styles,
    private readonly AccountProxyInterface $currentUser,
  ) {}

  /** Adds the UI Styles selector to the native Revista edit form. */
  #[Hook('form_alter')]
  public function alterForm(array &$form, FormStateInterface $form_state, string $form_id): void {
    if (!in_array($form_id, ['node_revista_form', 'node_revista_edit_form'], TRUE)
      || !$this->currentUser->hasPermission('administer nodes')) {
      return;
    }
    $node = $form_state->getFormObject()->getEntity();
    if (!$node instanceof NodeInterface || $node->bundle() !== 'revista' || !$node->hasField(self::FIELD)) {
      return;
    }
    $definition = $this->styles->getDefinition(self::STYLE, FALSE);
    if ($definition === NULL) {
      return;
    }
    $value = $node->get(self::FIELD)->value;
    $form[self::ELEMENT] = [
      '#type' => 'ui_styles_styles',
      '#title' => t('Faixa de acento da publicação'),
      '#description' => t('A escolha administrativa prevalece sobre o accent automático do Portal.'),
      '#default_value' => [
        'selected' => in_array($value, $this->optionValues(), TRUE) ? [self::STYLE => $value] : [],
        'extra' => '',
      ],
      '#drupal_theme' => $definition->getProvider(),
      '#after_build' => [[self::class, 'removeExtraClasses']],
      '#weight' => 80,
    ];
    $form['#entity_builders']['revista_accent'] = [$this, 'buildEntity'];
  }

  /** Removes UI Styles' free-form class field; this adapter is a closed choice. */
  public static function removeExtraClasses(array $element, FormStateInterface $form_state): array {
    unset($element['wrapper']['_ui_styles_extra']);
    return $element;
  }

  /** Saves only an allowed native field value. */
  public function buildEntity(string $entity_type, EntityInterface $entity, array &$form, FormStateInterface $form_state): void {
    if ($entity_type !== 'node' || !$entity instanceof NodeInterface || $entity->bundle() !== 'revista'
      || !$entity->hasField(self::FIELD) || !$this->currentUser->hasPermission('administer nodes')) {
      return;
    }
    $widget_value = $form_state->getValue(self::ELEMENT, []);
    $selection = is_array($widget_value) ? ($widget_value['selected'] ?? []) : [];
    $selected = is_array($selection) ? ($selection[self::STYLE] ?? '') : '';
    $entity->set(self::FIELD, in_array($selected, $this->optionValues(), TRUE) ? $selected : NULL);
  }

  /** Restricts direct editing of the native choice to node administrators. */
  #[Hook('entity_field_access')]
  public function fieldAccess(string $operation, FieldDefinitionInterface $field_definition, AccountInterface $account, ?FieldItemListInterface $items = NULL): AccessResult {
    if ($field_definition->getName() !== self::FIELD || $operation !== 'edit') {
      return AccessResult::neutral();
    }
    $access = $account->hasPermission('administer nodes')
      ? AccessResult::allowed()
      : AccessResult::forbidden();
    return $access->addCacheContexts(['user.permissions']);
  }

  /** Applies the administrator's selected UI Styles class to the teaser root. */
  #[Hook('entity_view')]
  public function applyAccent(array &$build, EntityInterface $entity, EntityViewDisplayInterface $display, string $view_mode): void {
    if (!$entity instanceof NodeInterface || $entity->bundle() !== 'revista' || $view_mode !== 'teaser'
      || !$entity->hasField(self::FIELD)) {
      return;
    }
    $selected = $entity->get(self::FIELD)->value;
    if (!in_array($selected, $this->optionValues(), TRUE)) {
      return;
    }
    $build['#attributes'] ??= [];
    $build = $this->styles->addClasses($build, [self::STYLE => $selected]);
  }

  /** Returns the option keys owned by the installed UI Styles definition. */
  private function optionValues(): array {
    $definition = $this->styles->getDefinition(self::STYLE, FALSE);
    return $definition === NULL ? [] : array_keys($definition->getOptions());
  }

}
