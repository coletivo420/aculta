<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\Hook;

use Drupal\aculta_portal\Account\AccountAddressForm;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
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
   *
   * @param array<mixed> $form
   */
  #[Hook('form_alter')]
  public function formAlter(array &$form, FormStateInterface $formState, string $formId): void {
    if ($formId === 'system_regional_settings') {
      if (isset($form['locale']) && is_array($form['locale'])) {
        $locale = &$form['locale'];
        if (isset($locale['site_default_country']) && is_array($locale['site_default_country'])) {
          $locale['site_default_country']['#default_value'] = 'BR';
          $locale['site_default_country']['#disabled'] = TRUE;
        }
      }
      return;
    }

    $formObject = $formState->getFormObject();
    if (!method_exists($formObject, 'getEntity')) {
      return;
    }
    $entity = $formObject->getEntity();
    if (!$entity instanceof EntityInterface) {
      return;
    }
    if ($entity->getEntityTypeId() === 'commerce_store' && isset($form['billing_countries']) && is_array($form['billing_countries'])) {
      $billingCountries = &$form['billing_countries'];
      $billingCountries['#access'] = FALSE;
      if (isset($billingCountries['widget']) && is_array($billingCountries['widget'])) {
        $billingCountries['widget']['#default_value'] = ['BR'];
      }
    }
    $field = AccountAddressForm::fieldOf($entity);
    if ($field === NULL || !isset($form[$field]) || !is_array($form[$field])) {
      return;
    }
    $entityId = $entity->id();
    $saved = $entity->isNew() || $entityId === NULL ? NULL : $this->entityTypeManager->getStorage($entity->getEntityTypeId())->load($entityId);
    $parts = AccountAddressForm::savedParts($saved, $field);
    if (!AccountAddressForm::apply($form[$field], $parts['number'], $parts['sn'])) {
      return;
    }
    if (!isset($form['#attached']) || !is_array($form['#attached'])) {
      $form['#attached'] = [];
    }
    if (!isset($form['#attached']['library']) || !is_array($form['#attached']['library'])) {
      $form['#attached']['library'] = [];
    }
    $form['#attached']['library'][] = AccountAddressForm::LIBRARY;
    if (!isset($form['#entity_builders']) || !is_array($form['#entity_builders'])) {
      $form['#entity_builders'] = [];
    }
    $form['#entity_builders'][] = [AccountAddressForm::class, 'buildStreetNumber'];
  }

  /**
   * Normaliza o país para BR no servidor ao salvar entidade com endereço.
   */
  #[Hook('entity_presave')]
  public function entityPresave(EntityInterface $entity): void {
    if (!$entity instanceof FieldableEntityInterface) {
      return;
    }
    if ($entity->getEntityTypeId() === 'commerce_store' && $entity->hasField('billing_countries')) {
      $entity->set('billing_countries', ['BR']);
    }
    $field = AccountAddressForm::fieldOf($entity)
      ?? ($entity->hasField('field_address') ? 'field_address'
      : ($entity->hasField('field_endereco') ? 'field_endereco'
      : ($entity->hasField('address') ? 'address' : NULL)));
    if ($field !== NULL && $entity->hasField($field) && !$entity->get($field)->isEmpty()) {
      foreach ($entity->get($field) as $item) {
        if ($item->get('country_code')->getString() !== 'BR') {
          $item->set('country_code', 'BR');
        }
      }
    }
  }

  /**
   * Garante que elementos de endereço usem BR e validem no servidor.
   *
   * @param array<mixed> $types
   */
  #[Hook('element_info_alter')]
  public function elementInfoAlter(array &$types): void {
    if (isset($types['address']) && is_array($types['address'])) {
      $address = &$types['address'];
      $address['#available_countries'] = ['BR'];
      if (!isset($address['#process']) || !is_array($address['#process'])) {
        $address['#process'] = [];
      }
      $address['#process'][] = [AccountAddressForm::class, 'finish'];
      if (!isset($address['#element_validate']) || !is_array($address['#element_validate'])) {
        $address['#element_validate'] = [];
      }
      $address['#element_validate'][] = [AccountAddressForm::class, 'validateAddressElement'];
    }
    if (isset($types['address_country']) && is_array($types['address_country'])) {
      $addressCountry = &$types['address_country'];
      $addressCountry['#available_countries'] = ['BR'];
      $addressCountry['#default_value'] = 'BR';
      if (!isset($addressCountry['#process']) || !is_array($addressCountry['#process'])) {
        $addressCountry['#process'] = [];
      }
      $addressCountry['#process'][] = [AccountAddressForm::class, 'finishCountryElement'];
      if (!isset($addressCountry['#element_validate']) || !is_array($addressCountry['#element_validate'])) {
        $addressCountry['#element_validate'] = [];
      }
      $addressCountry['#element_validate'][] = [AccountAddressForm::class, 'validateCountryCode'];
    }
  }

}
