<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\Hook;

use Drupal\aculta_portal\Account\AccountAddressForm;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;

/**
 * Leva o formulário de endereço único (AccountAddressForm) à loja e aos perfis de equipe.
 *
 * A Minha conta e o checkout usam o mesmo formulário pelos próprios caminhos (PortalHooks e BillingAddressPane).
 */
final class AccountAddressHooks {

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /** Cadastro da loja e perfis de equipe: o mesmo endereço, com CEP, Número, S/N e país fixo. */
  #[Hook('form_alter')]
  public function formAlter(array &$form, FormStateInterface $formState, string $formId): void {
    $formObject = $formState->getFormObject();
    if (!method_exists($formObject, 'getEntity')) {
      return;
    }
    $entity = $formObject->getEntity();
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

}
