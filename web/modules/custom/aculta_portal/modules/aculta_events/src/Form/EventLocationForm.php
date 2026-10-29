<?php

declare(strict_types=1);

namespace Drupal\aculta_events\Form;

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

    return $form;
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
