<?php

declare(strict_types=1);

namespace Drupal\aculta_events\Form;

use Drupal\aculta_portal\Account\AccountAddressForm;
use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormStateInterface;

/**
 * Formulário nativo para criação e edição de locais de evento (LOC-02).
 */
final class EventLocationForm extends ContentEntityForm {

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form = parent::buildForm($form, $form_state);
    /** @var \Drupal\aculta_events\Entity\EventLocation $entity */
    $entity = $this->entity;

    // Se o organizador estiver vazio e não for admin, preenche com
    // o usuário corrente.
    if ($entity->isNew() && $entity->get('field_organizador')->isEmpty()) {
      $entity->set('field_organizador', $this->currentUser()->id());
    }

    // Aplica o formulário central de endereço brasileiro (D-54/D-55).
    if (isset($form['field_endereco'])) {
      $parts = AccountAddressForm::savedParts($entity->isNew() ? NULL : $entity, 'field_endereco');
      AccountAddressForm::apply($form['field_endereco'], $parts['number'], $parts['sn']);
      $form['#entity_builders'][] = [AccountAddressForm::class, 'buildStreetNumber'];
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    parent::validateForm($form, $form_state);
    $values = $form_state->getValue('field_endereco');
    if (is_array($values)) {
      foreach ($values as $delta => $item) {
        $country = is_array($item) ? ($item['country_code'] ?? ($item['address']['country_code'] ?? NULL)) : NULL;
        if ($country !== NULL && $country !== '' && $country !== 'BR') {
          $form_state->setErrorByName('field_endereco', (string) $this->t('Apenas endereços no Brasil (BR) são suportados.'));
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state): int {
    /** @var \Drupal\aculta_events\Entity\EventLocation $entity */
    $entity = $this->entity;
    $status = $entity->save();

    if ($status === SAVED_NEW) {
      $this->messenger()->addStatus($this->t('O local %label foi cadastrado com sucesso.', [
        '%label' => $entity->label(),
      ]));
    }
    else {
      $this->messenger()->addStatus($this->t('O local %label foi atualizado com sucesso.', [
        '%label' => $entity->label(),
      ]));
    }

    if ($entity->access('view', $this->currentUser())) {
      $form_state->setRedirect('aculta_events.location_canonical', [
        'aculta_event_location' => $entity->id(),
      ]);
    }
    else {
      $form_state->setRedirect('aculta_events.locations');
    }

    return $status;
  }

}
