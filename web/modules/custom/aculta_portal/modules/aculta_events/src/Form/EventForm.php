<?php

declare(strict_types=1);

namespace Drupal\aculta_events\Form;

use Drupal\aculta_events\Entity\EventDate;
use Drupal\aculta_events\Entity\EventLocation;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormStateInterface;

/**
 * Formulário nativo para criação e edição de eventos com suporte a local.
 */
final class EventForm extends ContentEntityForm {

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form = parent::buildForm($form, $form_state);
    /** @var \Drupal\aculta_events\Entity\Event $entity */
    $entity = $this->entity;

    if ($entity->isNew() && $entity->get('field_organizador')->isEmpty()) {
      $entity->set('field_organizador', $this->currentUser()->id());
    }

    // Seção integrada de Data e Local (para criação de evento).
    if ($entity->isNew()) {
      $form['date_location'] = [
        '#type' => 'details',
        '#title' => $this->t('Data e Local do evento'),
        '#open' => TRUE,
        '#weight' => 22,
      ];

      $form['date_location']['starts_at'] = [
        '#type' => 'datetime',
        '#title' => $this->t('Data e hora de início'),
        '#description' => $this->t('Quando começa a primeira sessão deste evento.'),
      ];

      $form['date_location']['ends_at'] = [
        '#type' => 'datetime',
        '#title' => $this->t('Data e hora de término'),
        '#description' => $this->t('Quando termina a primeira sessão.'),
      ];

      $form['date_location']['local_id'] = [
        '#type' => 'entity_autocomplete',
        '#title' => $this->t('Selecionar local já cadastrado'),
        '#target_type' => 'aculta_event_location',
        '#description' => $this->t('Pesquise pelo nome para associar um local reutilizável a este evento. O endereço é gerenciado exclusivamente na entidade Local.'),
      ];
    }
    else {
      // Em edição de evento existente, exibe link para adicionar novas datas.
      $form['date_location_link'] = [
        '#type' => 'item',
        '#title' => $this->t('Datas e locais associados'),
        '#markup' => '<p><a href="/painel-administrativo/eventos/datas/adicionar?event_id=' . $entity->id() . '" class="button button--secondary">' . $this->t('Adicionar nova data para este evento') . '</a></p>',
        '#weight' => 22,
      ];
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    parent::validateForm($form, $form_state);

    $selectedLocal = $form_state->getValue('local_id');
    if (!empty($selectedLocal)) {
      if (!is_numeric($selectedLocal) || (int) $selectedLocal < 1) {
        $form_state->setErrorByName('local_id', $this->t('O local selecionado é inválido.'));
      }
      else {
        $loc = $this->entityTypeManager->getStorage('aculta_event_location')->load((int) $selectedLocal);
        if (!$loc instanceof EventLocation) {
          $form_state->setErrorByName('local_id', $this->t('O local selecionado não existe.'));
        }
      }
    }

    $startsAt = $form_state->getValue('starts_at');
    $endsAt = $form_state->getValue('ends_at');
    if ($startsAt instanceof DrupalDateTime && $endsAt instanceof DrupalDateTime) {
      if ($endsAt->getTimestamp() < $startsAt->getTimestamp()) {
        $form_state->setErrorByName('ends_at', $this->t('A data/hora de término deve ser posterior à de início.'));
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state): int {
    /** @var \Drupal\aculta_events\Entity\Event $entity */
    $entity = $this->entity;
    $status = $entity->save();

    if ($status === SAVED_NEW) {
      $this->messenger()->addStatus($this->t('O evento %label foi criado com sucesso.', [
        '%label' => $entity->label(),
      ]));

      // Trata associação opcional da primeira data com local já cadastrado.
      $localId = NULL;
      $selectedId = $form_state->getValue('local_id');
      if (!empty($selectedId) && is_numeric($selectedId)) {
        $localId = (int) $selectedId;
      }

      $startsAt = $form_state->getValue('starts_at');
      $endsAt = $form_state->getValue('ends_at');
      if ($startsAt instanceof DrupalDateTime || $localId !== NULL) {
        $startStr = $startsAt instanceof DrupalDateTime
          ? $startsAt->format('Y-m-d\TH:i:s')
          : (new \DateTimeImmutable())->format('Y-m-d\TH:i:s');
        $endStr = $endsAt instanceof DrupalDateTime
          ? $endsAt->format('Y-m-d\TH:i:s')
          : (new \DateTimeImmutable($startStr))->modify('+2 hours')->format('Y-m-d\TH:i:s');

        $dateData = [
          'title' => (string) $entity->label(),
          'field_evento' => (int) $entity->id(),
          'field_quando' => [
            'value' => $startStr,
            'end_value' => $endStr,
          ],
        ];
        if ($localId !== NULL) {
          $dateData['field_local'] = $localId;
        }
        $eventDate = EventDate::create($dateData);
        $eventDate->save();
      }
    }
    else {
      $this->messenger()->addStatus($this->t('O evento %label foi atualizado com sucesso.', [
        '%label' => $entity->label(),
      ]));
    }

    $slug = (string) $entity->get('field_slug')->value;
    if ($slug !== '') {
      $form_state->setRedirect('aculta_events.page', ['slug' => $slug]);
    }
    else {
      $form_state->setRedirect('aculta_events.catalogue');
    }

    return $status;
  }

}
