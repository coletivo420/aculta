<?php

declare(strict_types=1);

namespace Drupal\aculta_magazine\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\user\Entity\Role;

/**
 * Configures the role groups that receive ACULTA Magazine capabilities. */
final class EditorialCapabilitiesForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'aculta_magazine_editorial_capabilities';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array {
    return ['aculta_magazine.settings'];
  }

  /**
   * Builds role selectors for each editorial capability.
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $config = $this->config('aculta_magazine.settings');
    $options = [];
    foreach (Role::loadMultiple() as $role) {
      if ($role->id() !== 'anonymous') {
        $options[$role->id()] = $role->label();
      }
    }
    foreach ([
      'authoring_roles' => $this->t('Autoria'),
      'review_roles' => $this->t('Revisão e publicação'),
      'comments_roles' => $this->t('Comentários e moderação'),
    ] as $key => $label) {
      $form[$key] = [
        '#type' => 'checkboxes',
        '#title' => $label,
        '#options' => $options,
        '#default_value' => $config->get($key) ?? [],
        '#description' => $this->t('A seleção concede a capacidade ACULTA. As permissões Drupal, o acesso à entidade/revisão/campo e as regras do workflow continuam obrigatórios.'),
      ];
    }
    return parent::buildForm($form, $form_state);
  }

  /**
   * Saves the selected role groups to Magazine configuration.
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $config = $this->config('aculta_magazine.settings');
    foreach (['authoring_roles', 'review_roles', 'comments_roles'] as $key) {
      $selected = array_filter($form_state->getValue($key) ?? []);
      $config->set($key, array_values($selected));
    }
    $config->save();
    parent::submitForm($form, $form_state);
  }

}
