<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\Hook;

use Drupal\aculta_portal\Account\AccountAddressForm;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;

/**
 * Leva o formulário de endereço único aos seus consumidores de entidade.
 *
 * Minha conta e checkout chamam o helper por PortalHooks e BillingAddressPane.
 */
final class AccountAddressHooks {

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * Loja, equipe e locais de evento usam endereço BR com o mesmo formulário.
   */
  #[Hook('form_alter')]
  public function formAlter(array &$form, FormStateInterface $formState, string $formId): void {
    if ($formId === 'system_regional_settings') {
      if (isset($form['locale']['site_default_country'])) {
        $form['locale']['site_default_country']['#default_value'] = 'BR';
        $form['locale']['site_default_country']['#disabled'] = TRUE;
      }
      return;
    }

    $formObject = $formState->getFormObject();
    if (!method_exists($formObject, 'getEntity')) {
      return;
    }
    $entity = $formObject->getEntity();
    if ($entity->getEntityTypeId() === 'commerce_store' && isset($form['billing_countries'])) {
      $form['billing_countries']['#access'] = FALSE;
      if (isset($form['billing_countries']['widget'])) {
        $form['billing_countries']['widget']['#default_value'] = ['BR'];
      }
    }
    $field = AccountAddressForm::fieldOf($entity);
    if ($field === NULL || !isset($form[$field])) {
      return;
    }
    $saved = $entity->isNew() ? NULL : $this->entityTypeManager->getStorage($entity->getEntityTypeId())->load($entity->id());
    $parts = AccountAddressForm::savedParts($saved, $field);
    if (!AccountAddressForm::apply($form[$field], $parts['number'], $parts['sn'])) {
      return;
    }
    $form['#attached']['library'][] = AccountAddressForm::LIBRARY;
    $form['#entity_builders'][] = [AccountAddressForm::class, 'buildStreetNumber'];
  }

  /**
   * Normaliza o país para BR no servidor ao salvar entidade com endereço.
   */
  #[Hook('entity_presave')]
  public function entityPresave(EntityInterface $entity): void {
    if ($entity->getEntityTypeId() === 'commerce_store' && $entity->hasField('billing_countries')) {
      $entity->set('billing_countries', ['BR']);
    }
    $field = AccountAddressForm::fieldOf($entity)
      ?? ($entity->hasField('field_address') ? 'field_address'
      : ($entity->hasField('field_endereco') ? 'field_endereco'
      : ($entity->hasField('address') ? 'address' : NULL)));
    if ($field !== NULL && $entity->hasField($field) && !$entity->get($field)->isEmpty()) {
      foreach ($entity->get($field) as $item) {
        if ($item->country_code !== 'BR') {
          $item->country_code = 'BR';
        }
      }
    }
  }

  /**
   * Garante que elementos de endereço usem BR e validem no servidor.
   */
  #[Hook('element_info_alter')]
  public function elementInfoAlter(array &$types): void {
    if (isset($types['address'])) {
      $types['address']['#available_countries'] = ['BR'];
      $types['address']['#process'][] = [AccountAddressForm::class, 'finish'];
      $types['address']['#element_validate'][] = [AccountAddressForm::class, 'validateAddressElement'];
    }
    if (isset($types['address_country'])) {
      $types['address_country']['#available_countries'] = ['BR'];
      $types['address_country']['#default_value'] = 'BR';
      $types['address_country']['#process'][] = [AccountAddressForm::class, 'finishCountryElement'];
      $types['address_country']['#element_validate'][] = [AccountAddressForm::class, 'validateCountryCode'];
    }
  }

}
